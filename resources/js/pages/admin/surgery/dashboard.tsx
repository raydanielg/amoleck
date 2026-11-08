import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function SurgeryDashboard({
    metrics,
    analytics,
    branches,
    branchId,
}: {
    metrics: R;
    analytics: R;
    branches: R[];
    branchId: number;
}) {
    const block = (title: string, obj: R, colors?: R) => (
        <Card>
            <CardContent className="p-4">
                <h3 className="mb-3 text-sm font-medium">{title}</h3>
                <div className="grid grid-cols-3 gap-2">
                    {Object.entries(obj).map(([k, v]) => (
                        <div key={k} className="text-center">
                            <p className="text-[11px] text-muted-foreground capitalize">
                                {k.replaceAll('_', ' ')}
                            </p>
                            <p className="text-lg font-semibold">{String(v ?? '—')}</p>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
    return (
        <>
            <Head title="Theatre Dashboard" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Theatre & Surgery"
                        description="Operating theatres, surgical cases and PACU"
                    />
                    <div className="flex gap-2">
                        <select
                            className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                            defaultValue={branchId}
                            onChange={(e) =>
                                router.get('/admin/surgery', { branch_id: e.target.value })
                            }
                        >
                            {branches.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                        <Button asChild size="sm">
                            <Link href="/admin/surgery/cases/create">New case</Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href="/admin/surgery/calendar">Calendar</Link>
                        </Button>
                    </div>
                </div>
                {metrics.open_incidents > 0 && (
                    <div className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                        {metrics.open_incidents} open surgical incident(s) require review.
                    </div>
                )}
                {block('Theatre status', metrics.theatres)}
                {block("Today's operations", metrics.today)}
                {block('Surgical cases', metrics.cases)}
                {block('PACU / Recovery', metrics.pacu)}
                <Card>
                    <CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-medium">
                            Operational indicators (30 days)
                        </h3>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {[
                                ['Completed ops', analytics.operations_completed],
                                ['Utilization', analytics.theatre_utilization_pct != null ? `${analytics.theatre_utilization_pct}%` : '—'],
                                ['Avg first-case delay', analytics.avg_first_case_delay_min != null ? `${analytics.avg_first_case_delay_min}m` : '—'],
                                ['Avg turnaround', analytics.avg_turnaround_min != null ? `${analytics.avg_turnaround_min}m` : '—'],
                                ['Cancellation rate', `${analytics.cancellation_rate}%`],
                                ['Bookings', analytics.bookings_total],
                                ['Emergency', analytics.emergency_count],
                                ['Elective', analytics.elective_count],
                            ].map(([l, v]) => (
                                <div key={l} className="text-center">
                                    <p className="text-[11px] text-muted-foreground">{l}</p>
                                    <p className="text-lg font-semibold">{String(v)}</p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
                <div className="flex gap-2">
                    <Button asChild variant="outline" size="sm">
                        <Link href="/admin/surgery/cases">Cases</Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/admin/surgery/theatres">Theatres</Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/admin/surgery/operations">Operations</Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/admin/surgery/recovery">Recovery</Link>
                    </Button>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/admin/surgery/reports">Reports</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
