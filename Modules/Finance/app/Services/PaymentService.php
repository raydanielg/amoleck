<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\CashierShift;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\PaymentRefund;
use Modules\Finance\Models\PaymentTransaction;
use Modules\Foundation\Support\Audit;

class PaymentService
{
    public const STATUSES = ['initiated','pending','processing','successful','failed','cancelled','expired','refunded','partially_refunded','reversed','under_review'];

    private function nextNumber(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "finance.{$prefix}_sequence";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'finance', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    /** Record a payment against an invoice — does NOT mark it paid until confirmed. */
    public function record(array $data, int $userId): PaymentTransaction
    {
        abort_if($data['invoice_id'] && ! Invoice::find($data['invoice_id']), 422, 'Invalid invoice.');
        if ($inv = $data['invoice_id'] ? Invoice::find($data['invoice_id']) : null) {
            abort_unless(in_array($inv->status, ['issued', 'overdue', 'partially_paid']), 422, 'Invoice not payable.');
            abort_unless(bccomp((string) $data['amount'], '0', 2) > 0, 422, 'Amount must be positive.');
            abort_if(bccomp((string) $data['amount'], (string) (new InvoiceService)->outstanding($inv), 2) > 0, 422, 'Amount exceeds outstanding.');
        }
        $p = PaymentTransaction::create($data + [
            'payment_number' => $this->nextNumber('PAY', 'payment_transactions', 'payment_number'),
            'status' => 'initiated', 'initiated_at' => now(), 'recorded_by' => $userId,
        ]);
        Audit::record('finance.payment_recorded', $p->patient, null, ['pay' => $p->payment_number], $p->branch_id);
        return $p;
    }

    /** Confirm: initiated/pending→successful + allocate to invoice + update invoice status. */
    public function confirm(PaymentTransaction $p, int $userId): PaymentTransaction
    {
        abort_unless(in_array($p->status, ['initiated', 'pending', 'processing']), 422, 'Cannot confirm.');
        return DB::transaction(function () use ($p, $userId) {
            $p->update(['status' => 'successful', 'confirmed_at' => now(), 'verified_by' => $userId]);
            if ($inv = $p->invoice) {
                $p->allocations()->create(['invoice_id' => $inv->id, 'amount' => $p->amount, 'allocated_by' => $userId]);
                $outstanding = (new InvoiceService)->outstanding($inv->fresh());
                $inv->update(['status' => $outstanding <= 0 ? 'paid' : 'partially_paid']);
            }
            Audit::record('finance.payment_confirmed', $p->patient, null, ['pay' => $p->payment_number], $p->branch_id);
            return $p->fresh();
        });
    }

    public function fail(PaymentTransaction $p, string $reason, int $userId): void
    {
        abort_unless(in_array($p->status, ['initiated', 'pending', 'processing']), 422);
        $p->update(['status' => 'failed']);
        Audit::record('finance.payment_failed', $p->patient, null, ['reason' => $reason], $p->branch_id);
    }

    /** Refund capped at the paid amount minus already-refunded. */
    public function requestRefund(PaymentTransaction $p, float $amount, string $reason, int $userId): PaymentRefund
    {
        abort_unless($p->status === 'successful', 422, 'Only successful payments are refundable.');
        $refunded = (float) $p->refunds()->where('status', 'successful')->sum('amount');
        $refundable = (float) $p->amount - $refunded;
        abort_if(bccomp((string) $amount, (string) $refundable, 2) > 0, 422, 'Exceeds refundable amount.');
        $r = $p->refunds()->create([
            'refund_number' => $this->nextNumber('RFN', 'payment_refunds', 'refund_number'),
            'invoice_id' => $p->invoice_id, 'patient_id' => $p->patient_id, 'branch_id' => $p->branch_id,
            'amount' => $amount, 'reason' => $reason, 'status' => 'pending_approval', 'requested_by' => $userId,
        ]);
        Audit::record('finance.refund_requested', $p->patient, null, ['rf' => $r->refund_number], $p->branch_id);
        return $r;
    }

    public function processRefund(PaymentRefund $r, string $action, int $userId): void
    {
        if ($action === 'reject') { $r->update(['status' => 'rejected']); return; }
        abort_unless($r->status === 'pending_approval', 422);
        DB::transaction(function () use ($r, $action, $userId) {
            $r->update(['status' => 'successful', 'approved_by' => $userId, 'completed_at' => now()]);
            // reverse the original payment allocation if fully refunded
            $p = $r->payment;
            $totalRefunded = (float) $p->refunds()->where('status', 'successful')->sum('amount');
            if (bccomp((string) $totalRefunded, (string) $p->amount, 2) >= 0) {
                $p->update(['status' => 'refunded']);
                if ($inv = $p->invoice) { $inv->update(['status' => 'issued']); }
            }
        });
        Audit::record('finance.refund_completed', $r->patient, null, ['rf' => $r->refund_number], $r->branch_id);
    }

    public function openShift(int $cashierId, int $branchId, float $opening = 0): CashierShift
    {
        abort_if(CashierShift::where('cashier_id', $cashierId)->where('branch_id', $branchId)->where('status', 'open')->exists(), 422, 'Shift already open.');
        return CashierShift::create(['cashier_id' => $cashierId, 'branch_id' => $branchId, 'opening_balance' => $opening, 'status' => 'open', 'opened_at' => now()]);
    }

    /** Close: expected = opening + cash payments received; variance = actual - expected. */
    public function closeShift(CashierShift $shift, float $actual, int $userId): CashierShift
    {
        abort_unless($shift->status === 'open', 422);
        $received = PaymentTransaction::where('cashier_shift_id', $shift->id)->where('status', 'successful')->where('method', 'cash')->sum('amount');
        $expected = (float) $shift->opening_balance + (float) $received;
        $shift->update(['expected_closing' => $expected, 'actual_closing' => $actual, 'variance' => $actual - $expected, 'status' => 'closed', 'closed_at' => now()]);
        Audit::record('finance.shift_closed', null, null, ['shift' => $shift->id, 'variance' => (string) $shift->variance], $shift->branch_id);
        return $shift->fresh();
    }

    public function cashierDashboard(int $cashierId, int $branchId): array
    {
        $shift = CashierShift::where('cashier_id', $cashierId)->where('branch_id', $branchId)->where('status', 'open')->latest()->first();
        $payments = PaymentTransaction::where('recorded_by', $cashierId)->where('branch_id', $branchId)->whereDate('created_at', today());
        return [
            'shift' => $shift,
            'received_today' => (float) (clone $payments)->where('status', 'successful')->sum('amount'),
            'count_today' => (clone $payments)->count(),
            'by_method' => (clone $payments)->where('status', 'successful')->groupBy('method')->selectRaw('method, SUM(amount) as total')->pluck('total', 'method'),
            'pending' => PaymentTransaction::where('recorded_by', $cashierId)->where('branch_id', $branchId)->whereIn('status', ['initiated','pending'])->count(),
        ];
    }
}
