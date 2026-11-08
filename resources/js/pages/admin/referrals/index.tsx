import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type R = {
    id: number;
    ref_number: string;
    type: string;
    status: string;
    referral_date: string;
    patient: { id: number; mrn: string; first_name: string; last_name: string };
    receiving_branch?: { name: string };
};

export default function Referrals({
    referrals,
    filters,
    statuses,
}: {
    referrals: { data: R[] };
    filters: Record<string, string>;
    statuses: string[];
}) {
    return (
        <>
            <Head title="Referrals" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Referrals"
                        description="Internal and external patient referrals"
                    />
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get('/admin/referrals', {
                                status: e.target.value,
                            })
                        }
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All statuses</option>
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
                                    <th className="p-3">Ref #</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Type</th>
                                    <th className="p-3">Date</th>
                                    <th className="p-3">Branch</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {referrals.data.map((r) => (
                                    <tr
                                        key={r.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {r.ref_number}
                                        </td>
                                        <td className="p-3">
                                            <Link
                                                href={`/admin/patients/${r.patient.id}`}
                                                className="hover:underline"
                                            >
                                                {r.patient.first_name}{' '}
                                                {r.patient.last_name}
                                            </Link>
                                        </td>
                                        <td className="p-3">{r.type}</td>
                                        <td className="p-3">
                                            {r.referral_date}
                                        </td>
                                        <td className="p-3">
                                            {r.receiving_branch?.name}
                                        </td>
                                        <td className="p-3">
                                            <Badge>
                                                {r.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {referrals.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No referrals.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
