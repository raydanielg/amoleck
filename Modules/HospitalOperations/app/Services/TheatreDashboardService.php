<?php

namespace Modules\HospitalOperations\Services;

use Carbon\Carbon;
use Modules\HospitalOperations\Models\OperatingTheatre;
use Modules\HospitalOperations\Models\SurgicalBooking;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalIncident;
use Modules\HospitalOperations\Models\SurgicalOperation;
use Modules\HospitalOperations\Models\SurgicalRecoveryRecord;

/**
 * All metrics derive from persisted records. Incomplete records are excluded
 * from duration-based metrics (documented per-metric).
 */
class TheatreDashboardService
{
    public function dashboard(int $branchId, ?string $date = null): array
    {
        $date = $date ?? today()->toDateString();
        $theatres = OperatingTheatre::where('branch_id', $branchId)->get();
        $todayBookings = SurgicalBooking::where('branch_id', $branchId)->where('scheduled_date', $date)->get();
        $cases = SurgicalCase::where('branch_id', $branchId);

        return [
            'theatres' => [
                'total' => $theatres->count(),
                'available' => $theatres->where('status', 'available')->count(),
                'occupied' => $theatres->where('status', 'occupied')->count(),
                'reserved' => $theatres->where('status', 'reserved')->count(),
                'cleaning' => $theatres->where('status', 'cleaning')->count(),
                'maintenance' => $theatres->whereIn('status', ['maintenance', 'inspection_required', 'out_of_service'])->count(),
            ],
            'today' => [
                'scheduled' => $todayBookings->where('status', 'scheduled')->count(),
                'in_progress' => $todayBookings->where('status', 'in_progress')->count(),
                'completed' => $todayBookings->where('status', 'completed')->count(),
                'cancelled' => $todayBookings->where('status', 'cancelled')->count(),
                'emergency' => $todayBookings->where('priority', 'emergency')->count(),
            ],
            'cases' => [
                'pending_approvals' => (clone $cases)->whereIn('status', ['submitted', 'pending_clinical_review', 'pending_surgical_approval'])->count(),
                'awaiting_preop' => (clone $cases)->where('status', 'awaiting_preoperative_assessment')->count(),
                'awaiting_consent' => (clone $cases)->where('status', 'awaiting_consent')->count(),
                'ready_for_scheduling' => (clone $cases)->where('status', 'ready_for_scheduling')->count(),
                'in_theatre' => (clone $cases)->where('status', 'in_theatre')->count(),
                'in_recovery' => (clone $cases)->where('status', 'recovery')->count(),
            ],
            'pacu' => [
                'in_pacu' => SurgicalRecoveryRecord::where('branch_id', $branchId)->whereIn('status', ['received', 'under_observation', 'reassessment_required', 'ready_for_review'])->count(),
                'awaiting' => SurgicalRecoveryRecord::where('branch_id', $branchId)->where('status', 'awaiting_recovery')->count(),
                'ready_transfer' => SurgicalRecoveryRecord::where('branch_id', $branchId)->where('status', 'cleared_for_transfer')->count(),
            ],
            'open_incidents' => SurgicalIncident::where('branch_id', $branchId)->where('status', 'open')->count(),
        ];
    }

    /** Utilization = completed op time / scheduled booking time, over a range. */
    public function analytics(int $branchId, string $from, string $to): array
    {
        $ops = SurgicalOperation::where('branch_id', $branchId)->where('status', 'completed')
            ->whereBetween('procedure_end_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->with(['booking:id,scheduled_date,start_time,end_time,prep_minutes', 'case:id,surgical_specialty_id,surgeon_id,priority'])->get();

        $bookings = SurgicalBooking::where('branch_id', $branchId)->whereBetween('scheduled_date', [$from, $to])->get();

        // First-case start delay: patient_in_at minus scheduled start, completed ops only
        $firstCaseDelays = [];
        $turnarounds = [];
        foreach ($ops->groupBy(fn ($o) => $o->operating_theatre_id.'|'.$o->booking?->scheduled_date?->toDateString()) as $key => $group) {
            $sorted = $group->sortBy('patient_in_at')->values();
            $first = $sorted->first();
            if ($first?->booking) {
                $scheduled = Carbon::parse($first->booking->scheduled_date->toDateString().' '.$first->booking->start_time);
                $firstCaseDelays[] = $first->patient_in_at->diffInMinutes($scheduled, false);
            }
            for ($i = 1; $i < $sorted->count(); $i++) {
                if ($sorted[$i]->patient_in_at && $sorted[$i - 1]->patient_out_at) {
                    $turnarounds[] = $sorted[$i - 1]->patient_out_at->diffInMinutes($sorted[$i]->patient_in_at, false);
                }
            }
        }

        $actualMinutes = $ops->sum(fn ($o) => $o->procedure_start_at && $o->procedure_end_at ? $o->procedure_start_at->diffInMinutes($o->procedure_end_at) : 0);
        $scheduledMinutes = $bookings->where('status', 'completed')->sum(fn ($b) => Carbon::parse($b->start_time)->diffInMinutes(Carbon::parse($b->end_time)));

        return [
            'operations_completed' => $ops->count(),
            'bookings_total' => $bookings->count(),
            'bookings_cancelled' => $bookings->where('status', 'cancelled')->count(),
            'cancellation_rate' => $bookings->count() ? round($bookings->where('status', 'cancelled')->count() / $bookings->count() * 100, 1) : 0,
            'theatre_utilization_pct' => $scheduledMinutes > 0 ? round($actualMinutes / $scheduledMinutes * 100, 1) : null,
            'avg_first_case_delay_min' => count($firstCaseDelays) ? round(array_sum($firstCaseDelays) / count($firstCaseDelays), 1) : null,
            'avg_turnaround_min' => count($turnarounds) ? round(array_sum($turnarounds) / count($turnarounds), 1) : null,
            'emergency_count' => $ops->where('case.priority', 'emergency')->count(),
            'elective_count' => $ops->where('case.priority', 'elective')->count(),
            'by_specialty' => $ops->groupBy('case.surgical_specialty_id')->map->count(),
            'by_surgeon' => $ops->groupBy('surgeon_id')->map->count(),
        ];
    }
}
