import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type S = {
    id: number;
    session_number: string;
    type: string;
    status: string;
    session_at: string;
    patient: { id: number; mrn: string; first_name: string; last_name: string };
    treating_physiotherapist?: { name: string };
    branch?: { name: string };
};

export default function Sessions({
    sessions,
    stats,
    filters,
    statuses,
}: {
    sessions: { data: S[] };
    stats: Record<string, number>;
    filters: Record<string, string>;
    statuses: string[];
}) {
    return (
        <>
            <Head title="Treatment Sessions" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Treatment Sessions"
                        description="Documented physiotherapy sessions"
                    />
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get('/admin/sessions', {
                                status: e.target.value,
                            })
                        }
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All</option>
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {s.replace('_', ' ')}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-4 sm:grid-cols-4">
                    {Object.entries(stats).map(([k, v]) => (
                        <Card key={k}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground capitalize">
                                    {k === 'pending' ? 'Pending review' : k}
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
                                    <th className="p-3">Session</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Type</th>
                                    <th className="p-3">Physio</th>
                                    <th className="p-3">Date</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sessions.data.map((s) => (
                                    <tr
                                        key={s.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            <Link
                                                href={`/admin/sessions/${s.id}`}
                                                className="hover:underline"
                                            >
                                                {s.session_number}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <Link
                                                href={`/admin/patients/${s.patient.id}`}
                                                className="hover:underline"
                                            >
                                                {s.patient.first_name}{' '}
                                                {s.patient.last_name}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            {s.type.replace('_', ' ')}
                                        </td>
                                        <td className="p-3">
                                            {s.treating_physiotherapist?.name}
                                        </td>
                                        <td className="p-3">
                                            {new Date(
                                                s.session_at,
                                            ).toLocaleDateString()}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    s.status === 'finalized'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {s.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {sessions.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No sessions recorded.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
