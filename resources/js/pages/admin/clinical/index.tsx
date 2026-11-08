import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type E = {
    id: number;
    encounter_number: string;
    type: string;
    status: string;
    encounter_at: string;
    patient: { id: number; mrn: string; first_name: string; last_name: string };
    physiotherapist?: { name: string };
    branch?: { name: string };
};

export default function Encounters({
    encounters,
    filters,
    statuses,
}: {
    encounters: { data: E[] };
    filters: Record<string, string>;
    statuses: string[];
}) {
    return (
        <>
            <Head title="Clinical Encounters" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Clinical Encounters"
                        description="Physiotherapy assessments"
                    />
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get('/admin/clinical/encounters', {
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
                                    <th className="p-3">Encounter</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Type</th>
                                    <th className="p-3">Physio</th>
                                    <th className="p-3">Branch</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {encounters.data.map((e) => (
                                    <tr
                                        key={e.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            <Link
                                                href={`/admin/patients/clinical/${e.id}`}
                                                className="hover:underline"
                                            >
                                                {e.encounter_number}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <Link
                                                href={`/admin/patients/${e.patient.id}`}
                                                className="hover:underline"
                                            >
                                                {e.patient.first_name}{' '}
                                                {e.patient.last_name}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            {e.type.replace('_', ' ')}
                                        </td>
                                        <td className="p-3">
                                            {e.physiotherapist?.name}
                                        </td>
                                        <td className="p-3">
                                            {e.branch?.name}
                                        </td>
                                        <td className="p-3">
                                            <Badge>
                                                {e.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {encounters.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No encounters.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
