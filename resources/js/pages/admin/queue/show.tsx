import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function VisitShow({ visit }: { visit: R }) {
    const v = visit;
    const post = (status: string, extra = {}) =>
        router.post(`/admin/queue/visits/${v.id}/transition`, {
            status,
            ...extra,
        });
    const CAN: Record<string, string[]> = {
        arrived: ['registration_pending', 'checked_in'],
        registration_pending: ['checked_in'],
        checked_in: ['waiting'],
        waiting: ['called', 'on_hold'],
        called: ['in_service', 'on_hold', 'waiting'],
        in_service: ['completed', 'referred'],
        on_hold: ['waiting'],
    };
    const can = CAN[v.status] ?? [];
    return (
        <>
            <Head title={v.visit_number} />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={v.visit_number}
                        description={`${v.patient.first_name} ${v.patient.last_name} · ${v.patient.mrn} · ${v.source.replace(/_/g, ' ')}`}
                    />
                    <Badge>{v.status.replace(/_/g, ' ')}</Badge>
                </div>
                <Card>
                    <CardContent className="space-y-1 p-5 text-sm">
                        <p>
                            <strong>Branch:</strong> {v.branch?.name} ·{' '}
                            <strong>Reason:</strong> {v.reason ?? '—'}
                        </p>
                        {v.appointment && (
                            <p>
                                <strong>Appointment:</strong>{' '}
                                {v.appointment.appointment_number}
                            </p>
                        )}
                        {v.arrived_at && (
                            <p>
                                <strong>Arrived:</strong>{' '}
                                {new Date(v.arrived_at).toLocaleString()}
                            </p>
                        )}
                        {v.service_started_at && (
                            <p>
                                <strong>Service start:</strong>{' '}
                                {new Date(
                                    v.service_started_at,
                                ).toLocaleString()}
                            </p>
                        )}
                        {v.completed_at && (
                            <p>
                                <strong>Completed:</strong>{' '}
                                {new Date(v.completed_at).toLocaleString()}
                            </p>
                        )}
                    </CardContent>
                </Card>
                {v.queue_entries.map((e: R) => (
                    <Card key={e.id}>
                        <CardContent className="p-5">
                            <div className="mb-2 flex items-center justify-between">
                                <span className="font-mono font-semibold">
                                    {e.ticket_number}
                                </span>
                                <Badge
                                    variant={
                                        e.priority === 'emergency'
                                            ? 'destructive'
                                            : 'secondary'
                                    }
                                >
                                    {e.priority}
                                </Badge>
                            </div>
                            <p className="text-sm">
                                {e.queue_type?.name} ·{' '}
                                {e.status.replace(/_/g, ' ')}
                                {e.clinician ? ` · ${e.clinician.name}` : ''}
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {e.status === 'waiting' && (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                `/admin/queue/entries/${e.id}/call`,
                                            )
                                        }
                                    >
                                        Call
                                    </Button>
                                )}
                                {e.status === 'called' && (
                                    <Button
                                        size="sm"
                                        onClick={() => post('in_service')}
                                    >
                                        Start service
                                    </Button>
                                )}
                                {e.status === 'waiting' && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            const r = prompt('Skip reason');
                                            if (r)
                                                router.post(
                                                    `/admin/queue/entries/${e.id}/action`,
                                                    {
                                                        action: 'skip',
                                                        reason: r,
                                                    },
                                                );
                                        }}
                                    >
                                        Skip
                                    </Button>
                                )}
                                {['skipped', 'hold'].includes(e.status) && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/admin/queue/entries/${e.id}/action`,
                                                { action: 'requeue' },
                                            )
                                        }
                                    >
                                        Requeue
                                    </Button>
                                )}
                            </div>
                            <details className="mt-3 text-xs text-muted-foreground">
                                <summary>History</summary>
                                <ul className="mt-1 space-y-1">
                                    {e.status_histories?.map((h: R) => (
                                        <li key={h.id}>
                                            {h.from_status ?? '—'} →{' '}
                                            {h.to_status} · {h.changed_by?.name}{' '}
                                            ·{' '}
                                            {new Date(
                                                h.created_at,
                                            ).toLocaleTimeString()}
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        </CardContent>
                    </Card>
                ))}
                <div className="flex flex-wrap gap-2">
                    {can.includes('checked_in') && (
                        <Button size="sm" onClick={() => post('checked_in')}>
                            Check in
                        </Button>
                    )}
                    {can.includes('waiting') && (
                        <Button size="sm" onClick={() => post('waiting')}>
                            Enter queue
                        </Button>
                    )}
                    {can.includes('called') && (
                        <Button size="sm" onClick={() => post('called')}>
                            Call
                        </Button>
                    )}
                    {can.includes('in_service') && (
                        <Button size="sm" onClick={() => post('in_service')}>
                            Start service
                        </Button>
                    )}
                    {can.includes('completed') && (
                        <Button size="sm" onClick={() => post('completed')}>
                            Complete
                        </Button>
                    )}
                    {can.includes('on_hold') && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => post('on_hold')}
                        >
                            Hold
                        </Button>
                    )}
                    {can.includes('referred') && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => post('referred')}
                        >
                            Refer
                        </Button>
                    )}
                    {!['completed', 'cancelled', 'no_show'].includes(
                        v.status,
                    ) && (
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
                    <Button size="sm" variant="outline" asChild>
                        <a
                            href={`/admin/queue/visits/${v.id}/ticket`}
                            target="_blank"
                        >
                            Print ticket
                        </a>
                    </Button>
                </div>
            </div>
        </>
    );
}
