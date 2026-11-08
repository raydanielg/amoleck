<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Models\CreditNote;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\PatientCharge;
use Modules\Finance\Services\InvoiceService;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class BillingController extends Controller
{
    public function index(Request $request, InvoiceService $svc)
    {
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($request->user())->pluck('id')->first();
        $invs = Invoice::where('branch_id', $branchId)->with('patient:id,mrn,first_name,last_name')->latest()->paginate(20);
        return Inertia::render('admin/finance/invoices', [
            'invoices' => $invs,
            'postable' => PatientCharge::where('branch_id', $branchId)->where('status', 'posted')->whereDoesntHave('invoiceItem')->with('patient:id,mrn,first_name,last_name', 'service:id,name')->get(),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
            'patients' => Patient::select('id', 'mrn', 'first_name', 'last_name')->limit(50)->get(),
        ]);
    }

    public function generate(Request $request, InvoiceService $svc, PatientAccessService $access)
    {
        abort_unless($request->user()->can('finance.invoices.create'), 403);
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'branch_id' => 'required|exists:branches,id',
            'charge_ids' => 'required|array|min:1', 'charge_ids.*' => 'exists:patient_charges,id',
            'admission_id' => 'nullable|exists:admissions,id', 'type' => 'nullable|string|max:30',
        ]);
        abort_unless($access->canAccess($request->user(), Patient::findOrFail($data['patient_id'])), 403);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $inv = $svc->generate($data['patient_id'], $data['branch_id'], $data['charge_ids'], $request->user()->id, $data['admission_id'] ?? null, $data['type'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Invoice {$inv->invoice_number}"]);
    }

    public function show(Request $request, Invoice $invoice, InvoiceService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $invoice->patient) && $access->canAccess($request->user(), $p), 403);
        return Inertia::render('admin/finance/invoice-show', [
            'invoice' => $invoice->load(['items.charge:id,charge_number', 'creditNotes', 'statusHistories.changedBy', 'patient', 'branch']),
            'outstanding' => $svc->outstanding($invoice),
            'aging' => $svc->agingBucket($invoice),
        ]);
    }

    public function transition(Request $request, Invoice $invoice, InvoiceService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $invoice->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(InvoiceService::TRANSITIONS))], 'reason' => 'nullable|string|max:500']);
        $perm = match ($data['status']) { 'approved' => 'finance.invoices.approve', 'issued' => 'finance.invoices.issue', 'cancelled' => 'finance.invoices.cancel', default => 'finance.invoices.create' };
        abort_unless($request->user()->can($perm), 403);
        $svc->transition($invoice, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Invoice {$data['status']}"]);
    }

    public function creditNote(Request $request, Invoice $invoice, InvoiceService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $invoice->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($request->user()->can('finance.credit_notes.issue'), 403);
        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'reason' => 'required|string|max:500', 'type' => 'nullable|in:full,partial,correction']);
        $svc->issueCreditNote($invoice, (float) $data['amount'], $data['reason'], $data['type'] ?? 'partial', $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Credit note issued.']);
    }

    public function print(Request $request, Invoice $invoice, PatientAccessService $access)
    {
        abort_unless($request->user()->can('finance.invoices.download'), 403);
        abort_unless(($p = $invoice->patient) && $access->canAccess($request->user(), $p), 403);
        return view('finance::invoice-print', ['invoice' => $invoice->load('items', 'patient', 'branch', 'creditNotes'), 'hospital' => \Modules\Foundation\Models\HospitalProfile::first()]);
    }

    public function statement(Request $request, Patient $patient, InvoiceService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        abort_unless($request->user()->can('finance.statements.view'), 403);
        $branchId = $request->integer('branch_id') ?: $patient->primary_branch_id;
        abort_unless(BranchContext::canAccess($request->user(), $branchId), 403);
        return view('finance::statement-print', $svc->statement($patient->id, $branchId) + ['patient' => $patient, 'branch' => \Modules\Foundation\Models\Branch::find($branchId), 'hospital' => \Modules\Foundation\Models\HospitalProfile::first()]);
    }
}
