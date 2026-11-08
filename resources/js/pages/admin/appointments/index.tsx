import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type A = {
    id: number;
    appointment_number: string;
    appointment_date: string;
    start_time: string;
    status: string;
    patient: { id: number; first_name: string; last_name: string };
    clinician?: { name: string };
    branch?: { name: string };
    type?: { name: string };
};

export default function Appointments({
    appointments,
    stats,
    filters,
}: {
    appointments: { data: A[] };
    stats: Record<string, number>;
    filters: Record<string, string>;
}) {
    return (
        <>
            <Head title="Appointments" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Appointments"
                        description="Hospital scheduling"
                    />
                    <div className="flex gap-2">
                        <input
                            type="date"
                            value={filters.date ?? ''}
                            onChange={(e) =>
                                router.get('/admin/appointments', {
                                    date: e.target.value,
                                })
                            }
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        />
                        <Button asChild size="sm">
                            <Link href="/admin/appointments/create">Book</Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-4">
                    {Object.entries(stats).map(([k, v]) => (
                        <Card key={k}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground capitalize">
                                    {k.replace('_', ' ')}
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {v}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">No.</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Type</th>
                                    <th className="p-3">Clinician</th>
                                    <th className="p-3">Date/Time</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {appointments.data.map((a) => (
                                    <tr
                                        key={a.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            <Link
                                                href={`/admin/appointments/${a.id}`}
                                                className="hover:underline"
                                            >
                                                {a.appointment_number}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            {a.patient.first_name}{' '}
                                            {a.patient.last_name}
                                        </td>
                                        <td className="p-3">
                                            {a.type?.name ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {a.clinician?.name ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {a.appointment_date} {a.start_time}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    a.status === 'confirmed' ||
                                                    a.status === 'completed'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {a.status.replace(/_/g, ' ')}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {appointments.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No appointments.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
