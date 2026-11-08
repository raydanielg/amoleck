<?php

namespace Modules\Foundation\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\HospitalProfile;
use Modules\Foundation\Models\Setting;
use Modules\Foundation\Support\SettingsRegistry;
use Modules\Foundation\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FoundationDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::all() as $module => $perms) {
            foreach ($perms as $name => [$desc, $risk]) {
                Permission::findOrCreate($name, 'web')->update(['description' => $desc, 'risk_level' => $risk]);
            }
        }

        $rolePerms = [
            'Super Admin' => PermissionCatalog::flat(),
            'Hospital Administrator' => ['dashboard.view', 'hospital.view', 'hospital.edit', 'settings.view', 'modules.view', 'audit_logs.view', 'login_history.view', 'branches.view', 'branches.create', 'branches.edit', 'branches.activate', 'branches.deactivate', 'users.view', 'users.create', 'users.edit', 'users.suspend', 'users.reset_password', 'users.permissions', 'roles.view', 'permissions.view', 'reports.view', 'reports.export',
            'patientcare.dashboard.view', 'patientcare.patients.view', 'patientcare.patients.view_all_branches', 'patientcare.patients.create', 'patientcare.patients.update', 'patientcare.patients.deactivate', 'patientcare.patients.archive', 'patientcare.patients.restore', 'patientcare.patients.manage_documents', 'patientcare.patients.download_documents', 'patientcare.patients.manage_consent', 'patientcare.patients.manage_branches', 'patientcare.patients.detect_duplicates', 'patientcare.patients.resolve_duplicates', 'patientcare.patients.view_history', 'patientcare.patients.export'],
            'Branch Administrator' => ['dashboard.view', 'hospital.view', 'branches.view', 'users.view', 'users.create', 'users.edit', 'users.suspend', 'users.reset_password', 'reports.view'],
            'Medical Director' => ['dashboard.view', 'hospital.view', 'branches.view', 'users.view', 'reports.view', 'reports.export'],
            'Department Manager' => ['dashboard.view', 'users.view'],
            'Staff' => ['dashboard.view'],
            'Doctor' => ['dashboard.view', 'patientcare.dashboard.view', 'patientcare.patients.view', 'patientcare.patients.create', 'patientcare.patients.update', 'patientcare.patients.view_sensitive', 'patientcare.patients.manage_medical_summary', 'patientcare.patients.manage_documents', 'patientcare.patients.download_documents', 'patientcare.patients.view_history', 'operations.appointments.view',
            'patientcare.clinical.dashboard.view', 'patientcare.clinical.encounters.view', 'patientcare.clinical.history.view',
            'patientcare.treatment_plans.view', 'patientcare.treatment_plans.create', 'patientcare.treatment_plans.review', 'patientcare.treatment_plans.approve', 'patientcare.treatment_plans.discontinue', 'patientcare.treatment_goals.manage', 'patientcare.interventions.view'],
            'Physiotherapist' => ['queue.view', 'queue.call', 'queue.complete_visit', 'appointments.view', 'appointments.check_in', 'appointments.complete', 'dashboard.view', 'patientcare.dashboard.view', 'patientcare.patients.view', 'patientcare.patients.update', 'patientcare.patients.manage_medical_summary', 'patientcare.patients.view_history', 'operations.appointments.view',
            'patientcare.clinical.dashboard.view', 'patientcare.clinical.encounters.view', 'patientcare.clinical.encounters.create', 'patientcare.clinical.encounters.update', 'patientcare.clinical.pain.manage', 'patientcare.clinical.functional.manage', 'patientcare.clinical.rom.manage', 'patientcare.clinical.strength.manage', 'patientcare.clinical.special_tests.manage', 'patientcare.clinical.red_flags.manage', 'patientcare.clinical.problems.manage', 'patientcare.clinical.body_map.manage', 'patientcare.clinical.history.view',
            'patientcare.treatment_plans.view', 'patientcare.treatment_plans.create', 'patientcare.treatment_plans.update', 'patientcare.treatment_plans.submit', 'patientcare.treatment_plans.activate', 'patientcare.treatment_plans.hold', 'patientcare.treatment_goals.manage', 'patientcare.treatment_goals.progress.manage', 'patientcare.interventions.view',
            'patientcare.clinical.sessions.view', 'patientcare.clinical.sessions.create', 'patientcare.clinical.sessions.update', 'patientcare.clinical.sessions.submit', 'patientcare.clinical.sessions.interventions.manage', 'patientcare.clinical.sessions.adverse_events.manage', 'patientcare.clinical.sessions.cancel',
            'patientcare.clinical.exercises.library.view', 'patientcare.clinical.exercises.prescriptions.create', 'patientcare.clinical.home_programs.view', 'patientcare.clinical.home_programs.manage', 'patientcare.clinical.exercise_adherence.record',
            'patientcare.clinical.outcomes.view', 'patientcare.clinical.outcomes.record', 'patientcare.clinical.reassessments.manage', 'patientcare.clinical.discharges.view', 'patientcare.clinical.discharges.manage',
            'appointments.view', 'appointments.view_all_branches', 'appointments.create', 'appointments.update', 'appointments.confirm', 'appointments.reschedule', 'appointments.cancel', 'appointments.check_in', 'appointments.mark_no_show', 'appointments.complete', 'appointments.manage_types', 'appointments.manage_clinician_schedules', 'appointments.manage_resources', 'appointments.view_reports',
            'queue.view', 'queue.view_all_branches', 'queue.create', 'queue.check_in', 'queue.call', 'queue.manage', 'queue.transfer', 'queue.change_priority', 'queue.complete_visit', 'queue.manage_types', 'queue.view_analytics', 'queue.manage_display',
            'departments.view', 'departments.manage', 'departments.assign_staff',
            'services.view', 'services.create', 'services.update', 'services.activate', 'services.archive', 'services.manage_branch_availability', 'services.assign_clinicians', 'services.manage_categories',
            'ipd.view', 'ipd.admissions.manage', 'ipd.admissions.approve', 'ipd.discharge', 'ipd.transfer', 'ipd.wards.manage', 'ipd.beds.manage', 'ipd.census.view',
            'nursing.view', 'nursing.assignments.manage', 'nursing.vitals.create', 'nursing.notes.create', 'nursing.notes.finalize', 'nursing.notes.amend', 'nursing.fluid.manage', 'nursing.escalations.manage', 'nursing.handovers.manage',
            'medications.view', 'medications.catalogue.manage', 'medications.orders.create', 'medications.orders.review', 'medications.orders.approve', 'medications.orders.modify', 'medications.administration.record', 'medications.administration.verify', 'medications.prn.manage', 'medications.reports.view',
            'finance.dashboard.view', 'finance.pricing.manage', 'finance.pricing.approve', 'finance.charges.view', 'finance.charges.manage', 'finance.charges.post', 'finance.charges.reverse', 'finance.patient_financials.view', 'finance.reports.view',
            'finance.invoices.view', 'finance.invoices.create', 'finance.invoices.approve', 'finance.invoices.issue', 'finance.invoices.cancel', 'finance.invoices.download', 'finance.credit_notes.issue', 'finance.statements.view', 'finance.billing.view_outstanding',
            'finance.payments.view', 'finance.payments.create', 'finance.payments.verify', 'finance.refunds.create', 'finance.refunds.approve', 'finance.cashier.shift.manage', 'finance.reconciliation.manage', 'finance.receipts.generate',
            'finance.insurance.view', 'finance.insurance.manage', 'finance.insurance.verify', 'finance.insurance.claims.manage', 'finance.corporate.view', 'finance.corporate.manage',
            'finance.accounting.view', 'finance.accounting.manage', 'finance.journals.post', 'finance.expenses.manage',
            'finance.budgets.view', 'finance.budgets.manage', 'finance.budgets.approve', 'finance.procurement.validate_budget', 'finance.procurement.approve_financially',
            'hr.dashboard.view', 'hr.employees.view', 'hr.employees.manage', 'hr.employees.transfer', 'hr.contracts.manage', 'hr.positions.manage', 'hr.reports.view',
            'hr.attendance.view', 'hr.attendance.record', 'hr.attendance.verify', 'hr.attendance.correct.approve', 'hr.attendance.manage_schedules',
            'hr.leave.view', 'hr.leave.request', 'hr.leave.approve', 'hr.leave.manage', 'hr.leave.adjust'],
            'Receptionist' => ['queue.view', 'queue.create', 'queue.check_in', 'queue.call', 'queue.manage', 'queue.complete_visit', 'appointments.view', 'appointments.create', 'appointments.update', 'appointments.confirm', 'appointments.reschedule', 'appointments.cancel', 'appointments.check_in', 'appointments.mark_no_show', 'dashboard.view', 'patientcare.dashboard.view', 'patientcare.patients.view', 'patientcare.patients.create', 'patientcare.patients.update', 'patientcare.patients.manage_contacts', 'patientcare.patients.manage_documents', 'patientcare.patients.detect_duplicates', 'operations.appointments.view', 'operations.appointments.manage'],
            'Accountant' => ['dashboard.view', 'finance.invoices.view', 'finance.payments.receive', 'reports.view', 'reports.export'],
            'Cashier' => ['dashboard.view', 'finance.invoices.view', 'finance.payments.receive'],
            'HR Officer' => ['dashboard.view', 'users.view', 'users.create', 'users.edit', 'users.reset_password'],
            'Inventory Manager' => ['dashboard.view'],
            'Report Analyst' => ['dashboard.view', 'reports.view', 'reports.export', 'reporting.analytics.view'],
            'General Staff' => ['dashboard.view'],
        ];

        foreach ($rolePerms as $name => $perms) {
            $role = Role::findOrCreate($name, 'web');
            $role->update(['slug' => \Illuminate\Support\Str::slug($name), 'is_protected' => $name === 'Super Admin']);
            $role->syncPermissions($perms);
        }

        HospitalProfile::current();
        $branch = Branch::firstOrCreate(
            ['code' => 'HQ'],
            ['name' => 'Main Branch', 'status' => 'active', 'opening_date' => now()->toDateString()],
        );

        foreach (\Modules\Foundation\Support\SettingsRegistry::flat() as $key => $meta) {
            if (($meta['type'] ?? '') !== 'secret' && array_key_exists('default', $meta) && ! Setting::where('key', $key)->whereNull('branch_id')->exists()) {
                Setting::set($this->categoryOf($key), $key, $meta['default']);
            }
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@amoleck.test'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'status' => 'active', 'email_verified_at' => now(), 'primary_branch_id' => $branch->id],
        );
        $admin->assignRole('Super Admin');
        $admin->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);
    }

    private function categoryOf(string $key): string
    {
        foreach (SettingsRegistry::all() as $category => $keys) {
            if (array_key_exists($key, $keys)) {
                return $category;
            }
        }
        return 'general';
    }
}
