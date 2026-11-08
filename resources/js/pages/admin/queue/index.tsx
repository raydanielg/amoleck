import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function QueueIndex({
    queues,
    entries,
    analytics,
}: {
    queues: R[];
    entries: R[];
    analytics: Record<number, R>;
}) {
    const byQueue = (qid: number) =>
        entries.filter((e) => e.queue_type_id === qid);
    const callNext = (qid: number) =>
        router.post('/admin/queue/call-next', { queue_type_id: qid });
    return (
        <>
            <Head title="Queue" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Waiting Queue"
                        description="Live patient queue by department"
                    />
                    <Button asChild size="sm">
                        <Link href="/admin/queue/create">New visit</Link>
                    </Button>
                </div>
                {queues.map((q) => {
                    const qs = byQueue(q.id);
                    const a = analytics[q.id] ?? {};
                    return (
                        <Card key={q.id}>
                            <CardContent className="p-5">
                                <div className="mb-3 flex items-center justify-between">
                                    <h2 className="font-medium">
                                        {q.name}{' '}
                                        <span className="text-sm text-muted-foreground">
                                            {q.department}
                                        </span>
                                    </h2>
                                    <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                        {a.avg_wait_min != null && (
                                            <span>
                                                avg wait {a.avg_wait_min}m
                                            </span>
                                        )}
                                        <span>{qs.length} in queue</span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => callNext(q.id)}
                                        >
                                            Call next
                                        </Button>
                                    </div>
                                </div>
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="p-2">Ticket</th>
                                            <th className="p-2">Patient</th>
                                            <th className="p-2">Priority</th>
                                            <th className="p-2">Status</th>
                                            <th className="p-2">Wait</th>
                                            <th className="p-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {qs.map((e) => (
                                            <tr
                                                key={e.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="p-2 font-mono text-xs">
                                                    <Link
                                                        href={`/admin/queue/visits/${e.patient_visit_id}`}
                                                        className="hover:underline"
                                                    >
                                                        {e.ticket_number}
                                                    </Link>
                                                </td>
                                                <td className="p-2">
                                                    {
                                                        e.visit?.patient
                                                            ?.first_name
                                                    }{' '}
                                                    {
                                                        e.visit?.patient
                                                            ?.last_name
                                                    }
                                                </td>
                                                <td className="p-2">
                                                    <Badge
                                                        variant={
                                                            e.priority ===
                                                            'emergency'
                                                                ? 'destructive'
                                                                : e.priority ===
                                                                    'urgent'
                                                                  ? 'default'
                                                                  : 'secondary'
                                                        }
                                                    >
                                                        {e.priority}
                                                    </Badge>
                                                </td>
                                                <td className="p-2">
                                                    {e.status.replace('_', ' ')}
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    {Math.round(
                                                        (Date.now() -
                                                            new Date(
                                                                e.created_at,
                                                            ).getTime()) /
                                                            60000,
                                                    )}
                                                    m
                                                </td>
                                                <td className="space-x-1 p-2 text-right">
                                                    {e.status === 'waiting' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
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
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/queue/visits/${e.patient_visit_id}/transition`,
                                                                    {
                                                                        status: 'in_service',
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            Start
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {qs.length === 0 && (
                                    <p className="py-4 text-center text-sm text-muted-foreground">
                                        Queue empty
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </>
    );
}
