import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function AppointmentShow({ appointment }: { appointment: R }) {
    const a = appointment;
    const post = (status: string, extra = {}) =>
        router.post(`/admin/appointments/${a.id}/transition`, {
            status,
            ...extra,
        });
    const CAN: Record<string, string[]> = {
        draft: ['pending_confirmation'],
        pending_confirmation: ['confirmed', 'cancelled'],
        confirmed: ['checked_in', 'rescheduled', 'cancelled', 'no_show'],
        checked_in: ['in_progress'],
        in_progress: ['completed'],
    };
    const can = CAN[a.status] ?? [];
    return (
        <>
            <Head title={a.appointment_number} />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={a.appointment_number}
                        description={`${a.patient.first_name} ${a.patient.last_name} · ${a.patient.mrn}`}
                    />
                    <Badge>{a.status.replace(/_/g, ' ')}</Badge>
                </div>
                <Card>
                    <CardContent className="space-y-1 p-5 text-sm">
                        <p>
                            <strong>Type:</strong> {a.type?.name ?? '—'} ·{' '}
                            <strong>Branch:</strong> {a.branch.name}
                        </p>
                        <p>
                            <strong>When:</strong> {a.appointment_date}{' '}
                            {a.start_time}–{a.end_time} ·{' '}
                            <strong>Clinician:</strong>{' '}
                            {a.clinician?.name ?? '—'}
                        </p>
                        <p>
                            <strong>Resource:</strong> {a.resource?.name ?? '—'}{' '}
                            · <strong>Reason:</strong> {a.reason ?? '—'}
                        </p>
                        {a.checked_in_at && (
                            <p>
                                <strong>Checked in:</strong>{' '}
                                {new Date(a.checked_in_at).toLocaleString()}
                            </p>
                        )}
                    </CardContent>
                </Card>
                <div className="flex flex-wrap gap-2">
                    {can.includes('pending_confirmation') && (
                        <Button
                            size="sm"
                            onClick={() => post('pending_confirmation')}
                        >
                            Submit
                        </Button>
                    )}
                    {can.includes('confirmed') && (
                        <Button size="sm" onClick={() => post('confirmed')}>
                            Confirm
                        </Button>
                    )}
                    {can.includes('checked_in') && (
                        <Button size="sm" onClick={() => post('checked_in')}>
                            Check in
                        </Button>
                    )}
                    {can.includes('in_progress') && (
                        <Button size="sm" onClick={() => post('in_progress')}>
                            Start
                        </Button>
                    )}
                    {can.includes('completed') && (
                        <Button size="sm" onClick={() => post('completed')}>
                            Complete
                        </Button>
                    )}
                    {can.includes('rescheduled') && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                const d = prompt('New date YYYY-MM-DD');
                                const s = prompt('Start HH:MM');
                                const e = prompt('End HH:MM');
                                const r = prompt('Reason');
                                if (d && s && e && r)
                                    router.post(
                                        `/admin/appointments/${a.id}/reschedule`,
                                        {
                                            appointment_date: d,
                                            start_time: s,
                                            end_time: e,
                                            reason: r,
                                        },
                                    );
                            }}
                        >
                            Reschedule
                        </Button>
                    )}
                    {can.includes('cancelled') && (
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => {
                                const r = prompt('Cancel reason');
                                if (r) post('cancelled', { reason: r });
                            }}
                        >
                            Cancel
                        </Button>
                    )}
                    {can.includes('no_show') && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                const r = prompt('Reason');
                                if (r) post('no_show', { reason: r });
                            }}
                        >
                            No show
                        </Button>
                    )}
                </div>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-2 font-medium">History</h2>
                        <ul className="space-y-1 text-sm">
                            {a.status_histories.map((h: R) => (
                                <li key={h.id}>
                                    {h.from_status ?? '—'} → {h.to_status} ·{' '}
                                    {h.changed_by?.name} ·{' '}
                                    {new Date(h.created_at).toLocaleString()}
                                    {h.reason ? ` · ${h.reason}` : ''}
                                </li>
                            ))}
                            {a.reschedule_histories.map((h: R) => (
                                <li key={h.id}>
                                    resched {h.previous_date}→{h.new_date} ·{' '}
                                    {h.changed_by?.name}
                                    {h.reason ? ` · ${h.reason}` : ''}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
