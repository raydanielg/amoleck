import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type P = {
    id: number;
    plan_number: string;
    status: string;
    created_at: string;
    patient: { id: number; mrn: string; first_name: string; last_name: string };
    physiotherapist?: { name: string };
    branch?: { name: string };
};

export default function TreatmentPlans({
    plans,
    filters,
    statuses,
}: {
    plans: { data: P[] };
    filters: Record<string, string>;
    statuses: string[];
}) {
    return (
        <>
            <Head title="Treatment Plans" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Treatment Plans"
                        description="Physiotherapy plans and goals"
                    />
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get('/admin/treatment-plans', {
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
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Plan</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Physio</th>
                                    <th className="p-3">Branch</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {plans.data.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            <Link
                                                href={`/admin/treatment-plans/${p.id}`}
                                                className="hover:underline"
                                            >
                                                {p.plan_number}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <Link
                                                href={`/admin/patients/${p.patient.id}`}
                                                className="hover:underline"
                                            >
                                                {p.patient.first_name}{' '}
                                                {p.patient.last_name}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            {p.physiotherapist?.name}
                                        </td>
                                        <td className="p-3">
                                            {p.branch?.name}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    p.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {p.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {plans.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No treatment plans.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
