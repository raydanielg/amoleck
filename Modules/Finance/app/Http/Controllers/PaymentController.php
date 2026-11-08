<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\PaymentTransaction;
use Modules\Finance\Models\CashierShift;
use Modules\Finance\Services\PaymentService;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class PaymentController extends Controller
{
    public function index(Request $request, PaymentService $svc)
    {
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($request->user())->pluck('id')->first();
        return Inertia::render('admin/finance/payments', [
            'dashboard' => $svc->cashierDashboard($request->user()->id, $branchId),
            'payments' => PaymentTransaction::where('branch_id', $branchId)->with('patient:id,mrn,first_name,last_name', 'invoice:id,invoice_number')->latest()->paginate(20),
            'unpaid' => Invoice::where('branch_id', $branchId)->whereIn('status', ['issued','overdue','partially_paid'])->with('patient:id,mrn,first_name,last_name')->get(['id','invoice_number','patient_id','branch_id','total','status']),
            'branches' => BranchContext::allowed($request->user())->values(['id','name']),
            'methods' => ['cash','bank','mobile_money','cheque','other'],
        ]);
    }

    public function record(Request $request, PaymentService $svc, PatientAccessService $access)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'branch_id' => 'required|exists:branches,id',
            'invoice_id' => 'required|exists:invoices,id', 'method' => ['required', Rule::in(['cash','bank','mobile_money','cheque','other'])],
            'amount' => 'required|numeric|min:0.01', 'reference' => 'nullable|string|max:100', 'provider' => 'nullable|string|max:50',
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $shift = CashierShift::where('cashier_id', $request->user()->id)->where('branch_id', $data['branch_id'])->where('status', 'open')->latest()->first();
        $p = $svc->record($data + ['cashier_shift_id' => $shift?->id], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Payment {$p->payment_number}"]);
    }

    public function confirm(Request $request, PaymentTransaction $payment, PaymentService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $payment->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($request->user()->can('finance.payments.verify'), 403);
        $svc->confirm($payment, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Confirmed.']);
    }

    public function fail(Request $request, PaymentTransaction $payment, PaymentService $svc)
    {
        abort_unless($request->user()->can('finance.payments.verify'), 403);
        $svc->fail($payment, $request->input('reason', 'Failed'), $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Failed.']);
    }

    public function requestRefund(Request $request, PaymentTransaction $payment, PaymentService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $payment->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($request->user()->can('finance.refunds.create'), 403);
        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'reason' => 'required|string|max:500']);
        $svc->requestRefund($payment, (float) $data['amount'], $data['reason'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Refund requested.']);
    }

    public function processRefund(Request $request, \Modules\Finance\Models\PaymentRefund $refund, PaymentService $svc)
    {
        abort_unless($request->user()->can('finance.refunds.approve'), 403);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])]]);
        $svc->processRefund($refund, $data['action'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Refund {$data['action']}d."]);
    }

    public function openShift(Request $request, PaymentService $svc)
    {
        abort_unless($request->user()->can('finance.cashier.shift.manage'), 403);
        $data = $request->validate(['branch_id' => 'required|exists:branches,id', 'opening_balance' => 'nullable|numeric|min:0']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $svc->openShift($request->user()->id, $data['branch_id'], $data['opening_balance'] ?? 0);
        return back()->with('toast', ['type' => 'success', 'message' => 'Shift opened.']);
    }

    public function closeShift(Request $request, CashierShift $shift, PaymentService $svc)
    {
        abort_unless($request->user()->can('finance.cashier.shift.manage'), 403);
        $data = $request->validate(['actual_closing' => 'required|numeric|min:0']);
        $svc->closeShift($shift, (float) $data['actual_closing'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Shift closed.']);
    }

    public function receipt(Request $request, PaymentTransaction $payment, PatientAccessService $access)
    {
        abort_unless(($p = $payment->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($payment->status === 'successful', 422, 'Receipt requires confirmed payment.');
        return view('finance::receipt', ['payment' => $payment->load('patient', 'invoice', 'recordedBy', 'branch'), 'hospital' => \Modules\Foundation\Models\HospitalProfile::first()]);
    }

    public function storeMethod(Request $request)
    {
        abort_unless($request->user()->can('finance.payments.create'), 403);
        \Modules\Finance\Models\PaymentMethod::create($request->validate(['code' => 'required|string|max:20', 'name' => 'required', 'type' => 'required|string', 'branch_id' => 'nullable|exists:branches,id']));
        return back()->with('toast', ['type' => 'success', 'message' => 'Method added.']);
    }
}
