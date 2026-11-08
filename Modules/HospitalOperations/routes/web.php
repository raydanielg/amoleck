<?php

use Illuminate\Support\Facades\Route;
use Modules\HospitalOperations\Http\Controllers\AppointmentController;

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:HospitalOperations'])->prefix('admin/appointments')->name('admin.appointments.')->group(function () {
    Route::get('/', [AppointmentController::class, 'index'])->name('index')->middleware('can:appointments.view');
    Route::get('create', [AppointmentController::class, 'create'])->name('create')->middleware('can:appointments.create');
    Route::post('/', [AppointmentController::class, 'store'])->name('store')->middleware('can:appointments.create');
    Route::get('slots', [AppointmentController::class, 'slots'])->name('slots')->middleware('can:appointments.view');
    Route::get('{appointment}', [AppointmentController::class, 'show'])->name('show')->middleware('can:appointments.view');
    Route::post('{appointment}/transition', [AppointmentController::class, 'transition'])->name('transition');
    Route::post('{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('reschedule')->middleware('can:appointments.reschedule');

    Route::get('meta/types', [AppointmentController::class, 'types'])->name('types.index')->middleware('can:appointments.manage_types');
    Route::post('meta/types', [AppointmentController::class, 'storeType'])->name('types.store')->middleware('can:appointments.manage_types');
    Route::post('meta/schedules', [AppointmentController::class, 'storeSchedule'])->name('schedules.store')->middleware('can:appointments.manage_clinician_schedules');
    Route::post('meta/resources', [AppointmentController::class, 'storeResource'])->name('resources.store')->middleware('can:appointments.manage_resources');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:HospitalOperations'])->group(function () {
    Route::get('admin/departments', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'departments'])->name('admin.departments.index')->middleware('can:departments.view');
    Route::post('admin/departments', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'storeDepartment'])->name('admin.departments.store')->middleware('can:departments.manage');
    Route::put('admin/departments/{department}', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'updateDepartment'])->name('admin.departments.update')->middleware('can:departments.manage');
    Route::post('admin/departments/{department}/branches', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'assignBranches'])->name('admin.departments.branches')->middleware('can:departments.manage');
    Route::get('admin/services', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'services'])->name('admin.services.index')->middleware('can:services.view');
    Route::post('admin/services', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'storeService'])->name('admin.services.store')->middleware('can:services.create');
    Route::post('admin/services/{service}/transition', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'transitionService'])->name('admin.services.transition')->middleware('can:services.activate');
    Route::post('admin/services/{service}/availability', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'setAvailability'])->name('admin.services.availability')->middleware('can:services.manage_branch_availability');
    Route::post('admin/services/{service}/clinicians', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'assignClinician'])->name('admin.services.clinicians')->middleware('can:services.assign_clinicians');
    Route::post('admin/service-categories', [\Modules\HospitalOperations\Http\Controllers\ServiceCatalogueController::class, 'storeCategory'])->name('admin.service-categories.store')->middleware('can:services.manage_categories');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:HospitalOperations'])->prefix('admin/ipd')->name('admin.ipd.')->group(function () {
    Route::get('/', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'index'])->name('index')->middleware('can:ipd.view');
    Route::post('admissions', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'storeAdmission'])->name('admissions.store')->middleware('can:ipd.admissions.manage');
    Route::post('admissions/{admission}/transition', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'transition'])->name('admissions.transition');
    Route::post('admissions/{admission}/allocate', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'allocate'])->name('admissions.allocate')->middleware('can:ipd.beds.manage');
    Route::post('admissions/{admission}/transfer', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'transfer'])->name('admissions.transfer')->middleware('can:ipd.transfer');
    Route::post('admissions/{admission}/discharge', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'discharge'])->name('admissions.discharge')->middleware('can:ipd.discharge');
    Route::post('beds/{bed}/clean', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'cleanBed'])->name('beds.clean')->middleware('can:ipd.beds.manage');
    Route::post('wards', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'storeWard'])->name('wards.store')->middleware('can:ipd.wards.manage');
    Route::post('rooms', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'storeRoom'])->name('rooms.store')->middleware('can:ipd.wards.manage');
    Route::post('beds', [\Modules\HospitalOperations\Http\Controllers\IpdController::class, 'storeBed'])->name('beds.store')->middleware('can:ipd.wards.manage');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:HospitalOperations'])->prefix('admin/nursing')->name('admin.nursing.')->group(function () {
    Route::get('/', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'index'])->name('index')->middleware('can:nursing.view');
    Route::post('vitals', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'recordVital'])->name('vitals.store')->middleware('can:nursing.vitals.create');
    Route::get('patients/{patient}/vitals', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'vitalsHistory'])->name('vitals.history')->middleware('can:nursing.view');
    Route::post('notes', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'storeNote'])->name('notes.store')->middleware('can:nursing.notes.create');
    Route::post('notes/{note}/finalize', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'finalizeNote'])->name('notes.finalize')->middleware('can:nursing.notes.finalize');
    Route::post('notes/{note}/amend', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'amendNote'])->name('notes.amend')->middleware('can:nursing.notes.amend');
    Route::post('fluid', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'storeFluid'])->name('fluid.store')->middleware('can:nursing.fluid.manage');
    Route::get('fluid-balance', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'fluidBalance'])->name('fluid.balance')->middleware('can:nursing.view');
    Route::post('assignments', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'assign'])->name('assignments.store')->middleware('can:nursing.assignments.manage');
    Route::post('escalations', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'escalate'])->name('escalations.store')->middleware('can:nursing.escalations.manage');
    Route::post('escalations/{escalation}/acknowledge', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'acknowledgeEscalation'])->name('escalations.ack')->middleware('can:nursing.escalations.manage');
    Route::post('handovers', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'storeHandover'])->name('handovers.store')->middleware('can:nursing.handovers.manage');
    Route::post('handovers/{handover}/acknowledge', [\Modules\HospitalOperations\Http\Controllers\NursingController::class, 'acknowledgeHandover'])->name('handovers.ack')->middleware('can:nursing.handovers.manage');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:HospitalOperations'])->prefix('admin/medications')->name('admin.emar.')->group(function () {
    Route::get('/', [\Modules\HospitalOperations\Http\Controllers\MedicationController::class, 'emar'])->name('index')->middleware('can:medications.view');
    Route::post('orders', [\Modules\HospitalOperations\Http\Controllers\MedicationController::class, 'storeOrder'])->name('orders.store')->middleware('can:medications.orders.create');
    Route::post('orders/{order}/transition', [\Modules\HospitalOperations\Http\Controllers\MedicationController::class, 'transition'])->name('orders.transition');
    Route::post('orders/{order}/generate-doses', [\Modules\HospitalOperations\Http\Controllers\MedicationController::class, 'generateDoses'])->name('orders.doses')->middleware('can:medications.orders.modify');
    Route::post('orders/{order}/prn', [\Modules\HospitalOperations\Http\Controllers\MedicationController::class, 'administerPrn'])->name('orders.prn')->middleware('can:medications.prn.manage');
    Route::post('doses/{dose}/administer', [\Modules\HospitalOperations\Http\Controllers\MedicationController::class, 'administer'])->name('doses.administer')->middleware('can:medications.administration.record');
    Route::post('doses/{dose}/non-admin', [\Modules\HospitalOperations\Http\Controllers\MedicationController::class, 'recordNonAdmin'])->name('doses.nonadmin')->middleware('can:medications.administration.record');
    Route::post('catalogue', [\Modules\HospitalOperations\Http\Controllers\MedicationController::class, 'storeCatalogue'])->name('catalogue.store')->middleware('can:medications.catalogue.manage');
    Route::get('patients/{patient}/history', [\Modules\HospitalOperations\Http\Controllers\MedicationController::class, 'history'])->name('history')->middleware('can:medications.view');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:HospitalOperations'])->prefix('admin/queue')->name('admin.queue.')->group(function () {
    Route::get('/', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'index'])->name('index')->middleware('can:queue.view');
    Route::get('create', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'createVisit'])->name('create')->middleware('can:queue.create');
    Route::post('visits', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'storeVisit'])->name('visits.store')->middleware('can:queue.create');
    Route::get('visits/{visit}', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'show'])->name('show')->middleware('can:queue.view');
    Route::post('visits/{visit}/transition', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'transition'])->name('transition');
    Route::get('visits/{visit}/ticket', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'printTicket'])->name('ticket')->middleware('can:queue.view');
    Route::post('call-next', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'callNext'])->name('call-next')->middleware('can:queue.call');
    Route::post('entries/{entry}/call', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'callEntry'])->name('entries.call')->middleware('can:queue.call');
    Route::post('entries/{entry}/action', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'entryAction'])->name('entries.action')->middleware('can:queue.manage');
    Route::post('entries/{entry}/transfer', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'transfer'])->name('entries.transfer')->middleware('can:queue.transfer');
    Route::post('entries/{entry}/priority', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'setPriority'])->name('entries.priority')->middleware('can:queue.change_priority');
    Route::get('analytics', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'analytics'])->name('analytics')->middleware('can:queue.view_analytics');
    Route::post('queue-types', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'storeQueueType'])->name('queue-types.store')->middleware('can:queue.manage_types');
    Route::get('display', [\Modules\HospitalOperations\Http\Controllers\QueueController::class, 'display'])->name('display')->middleware('can:queue.view');
});


// ---- Phase 10: Theatre & Surgical Management ----
use Modules\HospitalOperations\Http\Controllers\RecoveryController;
use Modules\HospitalOperations\Http\Controllers\SurgeryController;
use Modules\HospitalOperations\Http\Controllers\SurgicalCaseController;
use Modules\HospitalOperations\Http\Controllers\SurgicalOperationController;
use Modules\HospitalOperations\Http\Controllers\SurgicalPreopController;
use Modules\HospitalOperations\Http\Controllers\TheatreController;

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:HospitalOperations'])->prefix('admin/surgery')->name('admin.surgery.')->group(function () {
    // Dashboard & configuration
    Route::get('/', [SurgeryController::class, 'dashboard'])->name('dashboard')->middleware('can:surgery.dashboard.view');
    Route::get('config', [SurgeryController::class, 'config'])->name('config')->middleware('can:surgery.catalogue.manage');
    Route::post('config/specialties', [SurgeryController::class, 'storeSpecialty'])->name('specialties.store')->middleware('can:surgery.catalogue.manage');
    Route::post('config/specialties/{specialty}', [SurgeryController::class, 'updateSpecialty'])->name('specialties.update')->middleware('can:surgery.catalogue.manage');
    Route::post('config/procedures', [SurgeryController::class, 'storeProcedure'])->name('procedures.store')->middleware('can:surgery.catalogue.manage');
    Route::post('config/procedures/{procedure}/transition', [SurgeryController::class, 'transitionProcedure'])->name('procedures.transition')->middleware('can:surgery.catalogue.manage');
    Route::post('config/templates', [SurgeryController::class, 'storeTemplate'])->name('templates.store')->middleware('can:surgery.catalogue.manage');
    Route::get('reports', [SurgeryController::class, 'reports'])->name('reports')->middleware('can:surgery.reports.view');

    // Surgical cases
    Route::get('cases', [SurgicalCaseController::class, 'index'])->name('cases.index')->middleware('can:surgery.cases.view');
    Route::get('cases/create', [SurgicalCaseController::class, 'create'])->name('cases.create')->middleware('can:surgery.cases.create');
    Route::post('cases', [SurgicalCaseController::class, 'store'])->name('cases.store')->middleware('can:surgery.cases.create');
    Route::get('cases/{case}', [SurgicalCaseController::class, 'show'])->name('cases.show')->middleware('can:surgery.cases.view');
    Route::post('cases/{case}/transition', [SurgicalCaseController::class, 'transition'])->name('cases.transition');
    Route::post('cases/{case}/consultation', [SurgicalCaseController::class, 'saveConsultation'])->name('cases.consultation');
    Route::post('cases/{case}/diagnoses', [SurgicalCaseController::class, 'addDiagnosis'])->name('cases.diagnoses');
    Route::post('cases/{case}/cancel', [SurgicalCaseController::class, 'cancel'])->name('cases.cancel');
    Route::post('cases/{case}/postpone', [SurgicalCaseController::class, 'postpone'])->name('cases.postpone');

    // Pre-operative
    Route::post('cases/{case}/preop', [SurgicalPreopController::class, 'saveAssessment'])->name('preop.save');
    Route::post('preop/{assessment}/review', [SurgicalPreopController::class, 'reviewAssessment'])->name('preop.review');
    Route::post('cases/{case}/anesthesia-pre', [SurgicalPreopController::class, 'saveAnesthesia'])->name('anesthesia_pre.save');
    Route::post('anesthesia-pre/{assessment}/review', [SurgicalPreopController::class, 'reviewAnesthesia'])->name('anesthesia_pre.review');
    Route::post('cases/{case}/consents', [SurgicalPreopController::class, 'recordConsent'])->name('consents.store');
    Route::post('consents/{consent}/withdraw', [SurgicalPreopController::class, 'withdrawConsent'])->name('consents.withdraw');
    Route::post('cases/{case}/checklist/init', [SurgicalPreopController::class, 'initChecklist'])->name('checklist.init');
    Route::post('checklist-items/{item}/complete', [SurgicalPreopController::class, 'completeItem'])->name('checklist.complete');
    Route::post('cases/{case}/blood', [SurgicalPreopController::class, 'requestBlood'])->name('blood.request');
    Route::post('blood/{requirement}/confirm', [SurgicalPreopController::class, 'confirmBlood'])->name('blood.confirm');

    // Theatres & scheduling
    Route::get('theatres', [TheatreController::class, 'theatres'])->name('theatres')->middleware('can:surgery.theatres.view');
    Route::post('theatres', [TheatreController::class, 'storeTheatre'])->name('theatres.store')->middleware('can:surgery.theatres.manage');
    Route::post('theatres/{theatre}/status', [TheatreController::class, 'theatreStatus'])->name('theatres.status')->middleware('can:surgery.theatres.manage');
    Route::post('theatres/{theatre}/hours', [TheatreController::class, 'setHours'])->name('theatres.hours')->middleware('can:surgery.theatres.manage');
    Route::post('theatres/{theatre}/exceptions', [TheatreController::class, 'addException'])->name('theatres.exceptions')->middleware('can:surgery.theatres.manage');
    Route::get('calendar', [TheatreController::class, 'calendar'])->name('calendar')->middleware('can:surgery.scheduling.view');
    Route::post('cases/{case}/book', [TheatreController::class, 'book'])->name('bookings.store')->middleware('can:surgery.scheduling.create');
    Route::post('bookings/{booking}/reschedule', [TheatreController::class, 'reschedule'])->name('bookings.reschedule')->middleware('can:surgery.scheduling.update');
    Route::post('bookings/{booking}/cancel', [TheatreController::class, 'cancelBooking'])->name('bookings.cancel')->middleware('can:surgery.scheduling.cancel');
    Route::post('cases/{case}/team', [TheatreController::class, 'assignStaff'])->name('team.assign');
    Route::post('team/{assignment}/confirm', [TheatreController::class, 'confirmAssignment'])->name('team.confirm');
    Route::post('team/{assignment}/replace', [TheatreController::class, 'replaceAssignment'])->name('team.replace');
    Route::post('team/{assignment}/remove', [TheatreController::class, 'removeAssignment'])->name('team.remove');

    // Safety & operations
    Route::get('operations', [SurgicalOperationController::class, 'index'])->name('operations.index')->middleware('can:surgery.cases.view');
    Route::get('operations/{operation}', [SurgicalOperationController::class, 'show'])->name('operations.show')->middleware('can:surgery.cases.view');
    Route::post('cases/{case}/safety/verify', [SurgicalOperationController::class, 'verifySafety'])->name('safety.verify');
    Route::post('cases/{case}/safety/exception', [SurgicalOperationController::class, 'safetyException'])->name('safety.exception');
    Route::post('cases/{case}/operations/start', [SurgicalOperationController::class, 'start'])->name('operations.start');
    Route::post('operations/{operation}/procedures', [SurgicalOperationController::class, 'recordProcedure'])->name('operations.procedures');
    Route::post('operations/{operation}/complete', [SurgicalOperationController::class, 'complete'])->name('operations.complete');
    Route::post('operations/{operation}/abort', [SurgicalOperationController::class, 'abortOp'])->name('operations.abort');
    Route::post('operations/{operation}/notes', [SurgicalOperationController::class, 'saveNote'])->name('notes.save');
    Route::post('notes/{note}/transition', [SurgicalOperationController::class, 'transitionNote'])->name('notes.transition');
    Route::post('notes/{note}/amend', [SurgicalOperationController::class, 'amendNote'])->name('notes.amend');
    Route::post('operations/{operation}/anesthesia', [SurgicalOperationController::class, 'saveAnesthesia'])->name('anesthesia.save');
    Route::post('anesthesia/{record}/link-medication', [SurgicalOperationController::class, 'linkMedication'])->name('anesthesia.link_med');
    Route::post('anesthesia/{record}/close', [SurgicalOperationController::class, 'closeAnesthesia'])->name('anesthesia.close');
    Route::post('operations/{operation}/counts', [SurgicalOperationController::class, 'recordCount'])->name('counts.store');
    Route::post('counts/{count}/resolve', [SurgicalOperationController::class, 'resolveCount'])->name('counts.resolve');
    Route::post('operations/{operation}/specimens', [SurgicalOperationController::class, 'registerSpecimen'])->name('specimens.store');
    Route::post('specimens/{specimen}/dispatch', [SurgicalOperationController::class, 'dispatchSpecimen'])->name('specimens.dispatch');
    Route::post('specimens/{specimen}/receive', [SurgicalOperationController::class, 'receiveSpecimen'])->name('specimens.receive');
    Route::post('operations/{operation}/implants', [SurgicalOperationController::class, 'recordImplant'])->name('implants.store');
    Route::post('operations/{operation}/consumables', [SurgicalOperationController::class, 'recordConsumable'])->name('consumables.store');
    Route::post('cases/{case}/incidents', [SurgicalOperationController::class, 'recordIncident'])->name('incidents.store');

    // Recovery / PACU
    Route::get('recovery', [RecoveryController::class, 'index'])->name('recovery')->middleware('can:surgery.recovery.view');
    Route::post('recovery/areas', [RecoveryController::class, 'storeArea'])->name('recovery.areas.store')->middleware('can:surgery.theatres.manage');
    Route::post('recovery/beds', [RecoveryController::class, 'storeBed'])->name('recovery.beds.store')->middleware('can:surgery.theatres.manage');
    Route::post('recovery/{record}/receive', [RecoveryController::class, 'receive'])->name('recovery.receive');
    Route::post('recovery/{record}/assess', [RecoveryController::class, 'assess'])->name('recovery.assess');
    Route::post('recovery/{record}/clear', [RecoveryController::class, 'clear'])->name('recovery.clear');
    Route::post('recovery/{record}/handover', [RecoveryController::class, 'createHandover'])->name('handover.create');
    Route::post('handovers/{handover}/acknowledge', [RecoveryController::class, 'acknowledgeHandover'])->name('handover.ack');
    Route::post('cases/{case}/postop-admission', [RecoveryController::class, 'postopAdmission'])->name('postop_admission');
    Route::post('recovery-beds/{bed}/clean', [RecoveryController::class, 'cleanBed'])->name('recovery_beds.clean');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:HospitalOperations'])->get('admin/surgery/notes/{note}/print', function (\Modules\HospitalOperations\Models\OperativeNote $note) {
    abort_unless(request()->user()->can('surgery.cases.view'), 403);
    abort_unless(\Modules\Foundation\Support\BranchContext::canAccess(request()->user(), (int) $note->case->branch_id), 403);
    \Modules\Foundation\Support\Audit::record('surgery.opnote_printed', $note->case->patient, null, ['note' => $note->id], $note->case->branch_id);
    return response()->view('surgery.operative-note', ['note' => $note->load(['case.patient', 'case.branch', 'operation.surgeon', 'operation.anesthetist', 'operation.procedures', 'author']), 'hospital' => \Modules\Foundation\Models\HospitalProfile::current()]);
})->name('admin.surgery.notes.print');
