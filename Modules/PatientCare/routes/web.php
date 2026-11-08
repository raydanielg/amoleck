<?php

use Illuminate\Support\Facades\Route;
use Modules\PatientCare\Http\Controllers\PatientController;
use Modules\PatientCare\Http\Controllers\PatientDashboardController;

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:PatientCare'])->prefix('admin/clinical')->name('admin.clinical.')->group(function () {
    Route::get('dashboard', [\Modules\PatientCare\Http\Controllers\ClinicalEncounterController::class, 'dashboard'])->name('dashboard')->middleware('can:patientcare.clinical.dashboard.view');
    Route::get('encounters', [\Modules\PatientCare\Http\Controllers\ClinicalEncounterController::class, 'index'])->name('index')->middleware('can:patientcare.clinical.encounters.view');
    Route::post('encounters/{encounter}/transition', [\Modules\PatientCare\Http\Controllers\ClinicalEncounterController::class, 'transition'])->name('transition')->middleware('can:patientcare.clinical.encounters.update');
    Route::post('encounters/{encounter}/amend', [\Modules\PatientCare\Http\Controllers\ClinicalEncounterController::class, 'amend'])->name('amend')->middleware('can:patientcare.clinical.assessments.amend');
    Route::get('encounters/{encounter}/print', [\Modules\PatientCare\Http\Controllers\ClinicalEncounterController::class, 'print'])->name('print')->middleware('can:patientcare.clinical.reports.view');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:PatientCare'])->prefix('admin/patients')->name('admin.patients.')->group(function () {
    Route::get('{patient}/clinical/create', [\Modules\PatientCare\Http\Controllers\ClinicalEncounterController::class, 'create'])->name('clinical.create')->middleware('can:patientcare.clinical.encounters.create');
    Route::post('{patient}/clinical', [\Modules\PatientCare\Http\Controllers\ClinicalEncounterController::class, 'store'])->name('clinical.store')->middleware('can:patientcare.clinical.encounters.create');
    Route::get('clinical/{encounter}', [\Modules\PatientCare\Http\Controllers\ClinicalEncounterController::class, 'show'])->name('clinical.show')->middleware('can:patientcare.clinical.encounters.view');
    Route::put('clinical/{encounter}', [\Modules\PatientCare\Http\Controllers\ClinicalEncounterController::class, 'update'])->name('clinical.update')->middleware('can:patientcare.clinical.encounters.update');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:PatientCare'])->prefix('admin/treatment-plans')->name('admin.treatment-plans.')->group(function () {
    Route::get('/', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'index'])->name('index')->middleware('can:patientcare.treatment_plans.view');
    Route::get('{plan}', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'show'])->name('show')->middleware('can:patientcare.treatment_plans.view');
    Route::put('{plan}', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'update'])->name('update')->middleware('can:patientcare.treatment_plans.update');
    Route::post('{plan}/transition', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'transition'])->name('transition');
    Route::get('{plan}/print', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'print'])->name('print')->middleware('can:patientcare.clinical.reports.view');
    Route::post('goals/{goal}/progress', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'goalProgress'])->name('goals.progress')->middleware('can:patientcare.treatment_goals.progress.manage');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:PatientCare'])->prefix('admin/interventions')->name('admin.interventions.')->group(function () {
    Route::get('/', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'catalogue'])->name('index')->middleware('can:patientcare.interventions.view');
    Route::post('/', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'storeCatalogue'])->name('store')->middleware('can:patientcare.interventions.manage_catalogue');
    Route::put('{intervention}', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'updateCatalogue'])->name('update')->middleware('can:patientcare.interventions.manage_catalogue');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:PatientCare'])->prefix('admin/sessions')->name('admin.sessions.')->group(function () {
    Route::get('/', [\Modules\PatientCare\Http\Controllers\TreatmentSessionController::class, 'index'])->name('index')->middleware('can:patientcare.clinical.sessions.view');
    Route::get('{session}', [\Modules\PatientCare\Http\Controllers\TreatmentSessionController::class, 'show'])->name('show')->middleware('can:patientcare.clinical.sessions.view');
    Route::put('{session}', [\Modules\PatientCare\Http\Controllers\TreatmentSessionController::class, 'update'])->name('update')->middleware('can:patientcare.clinical.sessions.update');
    Route::post('{session}/transition', [\Modules\PatientCare\Http\Controllers\TreatmentSessionController::class, 'transition'])->name('transition');
    Route::post('{session}/amend', [\Modules\PatientCare\Http\Controllers\TreatmentSessionController::class, 'amend'])->name('amend')->middleware('can:patientcare.clinical.sessions.amend');
    Route::post('{session}/goals/{goal}/progress', [\Modules\PatientCare\Http\Controllers\TreatmentSessionController::class, 'goalProgress'])->name('goals.progress')->middleware('can:patientcare.clinical.sessions.interventions.manage');
    Route::get('{session}/print', [\Modules\PatientCare\Http\Controllers\TreatmentSessionController::class, 'print'])->name('print')->middleware('can:patientcare.clinical.reports.view');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:PatientCare'])->group(function () {
    Route::get('admin/exercises', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'library'])->name('admin.exercises.index')->middleware('can:patientcare.clinical.exercises.library.view');
    Route::post('admin/exercises', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'storeExercise'])->name('admin.exercises.store')->middleware('can:patientcare.clinical.exercises.library.manage');
    Route::post('admin/exercises/{exercise}/media', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'uploadMedia'])->name('admin.exercises.media')->middleware('can:patientcare.clinical.exercises.library.manage');
    Route::post('admin/patients/{patient}/exercise-prescriptions', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'storePrescription'])->name('admin.patients.exercise-prescriptions.store')->middleware('can:patientcare.clinical.exercises.prescriptions.create');
    Route::post('admin/patients/{patient}/home-programs', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'storeProgram'])->name('admin.patients.home-programs.store')->middleware('can:patientcare.clinical.home_programs.manage');
    Route::get('admin/exercise-programs/{program}', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'show'])->name('admin.exercise-programs.show')->middleware('can:patientcare.clinical.home_programs.view');
    Route::put('admin/exercise-programs/{program}', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'update'])->name('admin.exercise-programs.update')->middleware('can:patientcare.clinical.home_programs.manage');
    Route::post('admin/exercise-programs/{program}/transition', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'transition'])->name('admin.exercise-programs.transition');
    Route::post('admin/exercise-programs/{program}/adherence', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'recordAdherence'])->name('admin.exercise-programs.adherence')->middleware('can:patientcare.clinical.exercise_adherence.record');
    Route::get('admin/exercise-programs/{program}/print', [\Modules\PatientCare\Http\Controllers\ExerciseController::class, 'print'])->name('admin.exercise-programs.print')->middleware('can:patientcare.clinical.reports.view');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:PatientCare'])->group(function () {
    Route::get('admin/outcome-measures', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'measures'])->name('admin.outcome-measures.index')->middleware('can:patientcare.clinical.outcomes.view');
    Route::post('admin/outcome-measures', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'storeMeasure'])->name('admin.outcome-measures.store')->middleware('can:patientcare.clinical.outcomes.manage_library');
    Route::post('admin/patients/{patient}/outcome-records', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'recordOutcome'])->name('admin.patients.outcome-records.store')->middleware('can:patientcare.clinical.outcomes.record');
    Route::get('admin/patients/{patient}/outcome-comparison', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'comparison'])->name('admin.patients.outcome-comparison')->middleware('can:patientcare.clinical.outcomes.view');
    Route::post('admin/patients/{patient}/reassessments', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'storeReassessment'])->name('admin.patients.reassessments.store')->middleware('can:patientcare.clinical.reassessments.manage');
    Route::post('admin/patients/{patient}/discharges', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'storeDischarge'])->name('admin.patients.discharges.store')->middleware('can:patientcare.clinical.discharges.manage');
    Route::get('admin/reassessments/{reassessment}', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'showReassessment'])->name('admin.reassessments.show')->middleware('can:patientcare.clinical.reassessments.manage');
    Route::put('admin/reassessments/{reassessment}', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'updateReassessment'])->name('admin.reassessments.update')->middleware('can:patientcare.clinical.reassessments.manage');
    Route::post('admin/reassessments/{reassessment}/transition', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'transitionReassessment'])->name('admin.reassessments.transition');
    Route::get('admin/discharges/{discharge}', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'showDischarge'])->name('admin.discharges.show')->middleware('can:patientcare.clinical.discharges.view');
    Route::put('admin/discharges/{discharge}', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'updateDischarge'])->name('admin.discharges.update')->middleware('can:patientcare.clinical.discharges.manage');
    Route::post('admin/discharges/{discharge}/transition', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'transitionDischarge'])->name('admin.discharges.transition');
    Route::get('admin/discharges/{discharge}/print', [\Modules\PatientCare\Http\Controllers\OutcomeDischargeController::class, 'printDischarge'])->name('admin.discharges.print')->middleware('can:patientcare.clinical.reports.view');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:PatientCare', 'can:patientcare.referrals.view'])->prefix('admin/referrals')->name('admin.referrals.')->group(function () {
    Route::get('/', [\Modules\PatientCare\Http\Controllers\ReferralController::class, 'index'])->name('index');
});

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:PatientCare'])->prefix('admin/patients')->name('admin.patients.')->group(function () {
    Route::get('dashboard', [PatientDashboardController::class, 'index'])->name('dashboard')->middleware('can:patientcare.dashboard.view');

    Route::get('/', [PatientController::class, 'index'])->name('index')->middleware('can:patientcare.patients.view');
    Route::get('create', [PatientController::class, 'create'])->name('create')->middleware('can:patientcare.patients.create');
    Route::post('/', [PatientController::class, 'store'])->name('store')->middleware('can:patientcare.patients.create');
    Route::get('duplicates', [PatientController::class, 'duplicates'])->name('duplicates')->middleware('can:patientcare.patients.detect_duplicates');
    Route::post('duplicates/{review}/resolve', [PatientController::class, 'resolveDuplicate'])->name('duplicates.resolve')->middleware('can:patientcare.patients.resolve_duplicates');

    Route::get('{patient}/timeline', [PatientController::class, 'timeline'])->name('timeline')->middleware('can:patientcare.timeline.view');
    Route::post('{patient}/transfer', [PatientController::class, 'transferBranch'])->name('transfer')->middleware('can:patientcare.branches.transfer');
    Route::post('{patient}/history/{kind}', [\Modules\PatientCare\Http\Controllers\PatientHistoryController::class, 'store'])->name('history.store')->middleware('can:patientcare.history.create');
    Route::post('{patient}/history/{kind}/{id}/amend', [\Modules\PatientCare\Http\Controllers\PatientHistoryController::class, 'amend'])->name('history.amend')->middleware('can:patientcare.history.amend');
    Route::post('{patient}/allergy-status', [\Modules\PatientCare\Http\Controllers\PatientHistoryController::class, 'allergyStatus'])->name('allergy-status')->middleware('can:patientcare.allergies.manage');
    Route::get('{patient}/referrals/create', [\Modules\PatientCare\Http\Controllers\ReferralController::class, 'create'])->name('referrals.create')->middleware('can:patientcare.referrals.create');
    Route::post('{patient}/referrals', [\Modules\PatientCare\Http\Controllers\ReferralController::class, 'store'])->name('referrals.store')->middleware('can:patientcare.referrals.create');
    Route::post('referrals/{referral}/transition', [\Modules\PatientCare\Http\Controllers\ReferralController::class, 'transition'])->name('referrals.transition');
    Route::post('{patient}/documents/{document}/verify', [PatientController::class, 'verifyDocument'])->name('documents.verify')->middleware('can:patientcare.documents.verify');
    Route::post('{patient}/documents/{document}/replace', [PatientController::class, 'replaceDocument'])->name('documents.replace')->middleware('can:patientcare.patients.manage_documents');

    Route::get('{patient}', [PatientController::class, 'show'])->name('show')->middleware('can:patientcare.patients.view');
    Route::get('{patient}/edit', [PatientController::class, 'edit'])->name('edit')->middleware('can:patientcare.patients.update');
    Route::put('{patient}', [PatientController::class, 'update'])->name('update')->middleware('can:patientcare.patients.update');
    Route::post('{patient}/status', [PatientController::class, 'updateStatus'])->name('status');
    Route::post('{patient}/branches', [PatientController::class, 'associateBranch'])->name('branches.associate')->middleware('can:patientcare.patients.manage_branches');

    Route::post('{patient}/medical-entries', [PatientController::class, 'storeMedicalEntry'])->name('medical.store')->middleware('can:patientcare.patients.manage_medical_summary');
    Route::get('{patient}/sessions/create', [\Modules\PatientCare\Http\Controllers\TreatmentSessionController::class, 'create'])->name('sessions.create')->middleware('can:patientcare.clinical.sessions.create');
    Route::post('{patient}/sessions', [\Modules\PatientCare\Http\Controllers\TreatmentSessionController::class, 'store'])->name('sessions.store')->middleware('can:patientcare.clinical.sessions.create');
    Route::get('{patient}/treatment-plans/create', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'create'])->name('treatment-plans.create')->middleware('can:patientcare.treatment_plans.create');
    Route::post('{patient}/treatment-plans', [\Modules\PatientCare\Http\Controllers\TreatmentPlanController::class, 'store'])->name('treatment-plans.store')->middleware('can:patientcare.treatment_plans.create');
    Route::post('{patient}/consents', [PatientController::class, 'storeConsent'])->name('consents.store')->middleware('can:patientcare.patients.manage_consent');
    Route::post('{patient}/consents/{consent}/withdraw', [PatientController::class, 'withdrawConsent'])->name('consents.withdraw')->middleware('can:patientcare.patients.manage_consent');
    Route::post('{patient}/documents', [PatientController::class, 'storeDocument'])->name('documents.store')->middleware('can:patientcare.patients.manage_documents');
    Route::get('{patient}/documents/{document}/download', [PatientController::class, 'downloadDocument'])->name('documents.download')->middleware('can:patientcare.patients.download_documents');
});
