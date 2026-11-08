import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function Insurance({
    dashboard,
    claims,
    memberships,
}: {
    dashboard: R;
    providers: R[];
    claims: { data: R[] };
    memberships: R[];
    patients: R[];
}) {
    const money = (n: number | string) =>
        new Intl.NumberFormat('en-TZ', {
            style: 'currency',
            currency: 'TZS',
            maximumFractionDigits: 0,
        }).format(Number(n));
    return (
        <>
            <Head title="Insurance" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Insurance & Claims"
                    description="Providers, memberships, pre-auth & claims"
                />
                <div className="grid gap-4 sm:grid-cols-5">
                    {Object.entries(dashboard).map(([k, v]) => (
                        <Card key={k}>
                            <CardContent className="p-3 text-center">
                                <p className="text-xs text-muted-foreground capitalize">
                                    {k.replace(/_/g, ' ')}
                                </p>
                                <p className="text-xl font-semibold">
                                    {k.includes('receivable') ? money(v) : v}
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
                                    <th className="p-3">Claim</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Invoice</th>
                                    <th className="p-3">Insurer share</th>
                                    <th className="p-3">Patient share</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {claims.data.map((c) => (
                                    <tr
                                        key={c.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {c.claim_number}
                                        </td>
                                        <td className="p-3">
                                            {c.patient.first_name}{' '}
                                            {c.patient.last_name}
                                        </td>
                                        <td className="p-3 font-mono text-xs">
                                            {c.invoice?.invoice_number}
                                        </td>
                                        <td className="p-3">
                                            {money(c.insurer_share)}
                                        </td>
                                        <td className="p-3">
                                            {money(c.patient_share)}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    c.status === 'paid'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {c.status.replace(/_/g, ' ')}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-right">
                                            {c.status === 'draft' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/insurance/claims/${c.id}/transition`,
                                                            {
                                                                status: 'submitted',
                                                                method: 'manual',
                                                            },
                                                        )
                                                    }
                                                >
                                                    Submit
                                                </Button>
                                            )}
                                            {[
                                                'acknowledged',
                                                'under_review',
                                            ].includes(c.status) && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/insurance/claims/${c.id}/transition`,
                                                            {
                                                                status: 'approved',
                                                            },
                                                        )
                                                    }
                                                >
                                                    Approve
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {claims.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No claims.
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">
                            Memberships ({memberships.length})
                        </h2>
                        <table className="w-full text-sm">
                            <tbody>
                                {memberships.map((m) => (
                                    <tr
                                        key={m.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-2">
                                            {m.patient.first_name}{' '}
                                            {m.patient.last_name}
                                        </td>
                                        <td className="p-2">
                                            {m.provider?.name} · {m.plan?.name}
                                        </td>
                                        <td className="p-2 font-mono text-xs">
                                            {m.member_number}
                                        </td>
                                        <td className="p-2">
                                            <Badge
                                                variant={
                                                    m.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {m.status}
                                            </Badge>
                                        </td>
                                        <td className="p-2 text-right">
                                            {m.status === 'pending' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/insurance/memberships/${m.id}/verify`,
                                                        )
                                                    }
                                                >
                                                    Verify
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
