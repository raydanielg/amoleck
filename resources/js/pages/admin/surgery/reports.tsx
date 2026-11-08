import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
export default function SurgeryReports({ analytics, filters, branches, specialties }: { analytics: R; filters: R; branches: R[]; specialties: R[] }) {
    const row = (l: string, v: any) => (
        <div className="flex justify-between border-b py-1 text-sm last:border-0" key={l}>
            <span className="text-muted-foreground">{l}</span><span className="font-medium">{String(v ?? '—')}</span>
        </div>
    );
    return (
        <>
            <Head title="Surgical Reports" />
            <div className="space-y-6 p-6">
                <Heading title="Surgical Reports & Analytics" description="Derived from persisted records only" />
                <Form method="get" action="/admin/surgery/reports" className="flex flex-wrap gap-2">
                    <Input type="date" name="from" defaultValue={filters.from} className="h-8 w-40" />
                    <Input type="date" name="to" defaultValue={filters.to} className="h-8 w-40" />
                    <select name="branch_id" defaultValue={filters.branchId} className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                        {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                    </select>
                    <Button size="sm" type="submit">Run</Button>
                </Form>
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">Workload</h3>
                        {row('Operations completed', analytics.operations_completed)}
                        {row('Bookings total', analytics.bookings_total)}
                        {row('Bookings cancelled', analytics.bookings_cancelled)}
                        {row('Cancellation rate', `${analytics.cancellation_rate}%`)}
                        {row('Emergency surgeries', analytics.emergency_count)}
                        {row('Elective surgeries', analytics.elective_count)}
                    </CardContent></Card>
                    <Card><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">Efficiency</h3>
                        {row('Theatre utilization', analytics.theatre_utilization_pct != null ? `${analytics.theatre_utilization_pct}%` : 'n/a')}
                        {row('Avg first-case start delay', analytics.avg_first_case_delay_min != null ? `${analytics.avg_first_case_delay_min} min` : 'n/a')}
                        {row('Avg turnaround between cases', analytics.avg_turnaround_min != null ? `${analytics.avg_turnaround_min} min` : 'n/a')}
                    </CardContent></Card>
                    <Card><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">By specialty</h3>
                        {Object.entries(analytics.by_specialty ?? {}).map(([k, v]) =>
                            row(specialties.find((s) => String(s.id) === String(k))?.name ?? `#${k}`, v))}
                    </CardContent></Card>
                    <Card><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">Definitions</h3>
                        <p className="text-xs text-muted-foreground">
                            Utilization = sum(actual procedure minutes) / sum(scheduled booking minutes) for completed
                            bookings. First-case delay = patient_in_at − scheduled start of the day's first operation.
                            Turnaround = patient_out → next patient_in in the same theatre/day. Cancelled bookings are
                            excluded from utilization; incomplete operations are excluded from all duration metrics.
                        </p>
                    </CardContent></Card>
                </div>
            </div>
        </>
    );
}
