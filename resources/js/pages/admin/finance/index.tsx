import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function Finance({
    dashboard,
    charges,
    prices,
}: {
    dashboard: R;
    charges: { data: R[] };
    prices: { data: R[] };
}) {
    const money = (n: number | string) =>
        new Intl.NumberFormat('en-TZ', {
            style: 'currency',
            currency: 'TZS',
            maximumFractionDigits: 0,
        }).format(Number(n));
    return (
        <>
            <Head title="Finance" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Finance"
                    description="Charges, pricing & revenue"
                />
                <div className="grid gap-4 sm:grid-cols-6">
                    {Object.entries(dashboard).map(([k, v]) => (
                        <Card key={k}>
                            <CardContent className="p-3 text-center">
                                <p className="text-xs text-muted-foreground capitalize">
                                    {k.replace(/_/g, ' ')}
                                </p>
                                <p className="text-xl font-semibold">
                                    {k.includes('total') ? money(v) : v}
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
                                    <th className="p-3">Charge</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Service</th>
                                    <th className="p-3">Total</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {charges.data.map((c) => (
                                    <tr
                                        key={c.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {c.charge_number}
                                        </td>
                                        <td className="p-3">
                                            {c.patient.first_name}{' '}
                                            {c.patient.last_name}
                                        </td>
                                        <td className="p-3">
                                            {c.service?.name ?? '—'}
                                        </td>
                                        <td className="p-3 font-medium">
                                            {money(c.total)}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    c.status === 'posted'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {c.status.replace(/_/g, ' ')}
                                            </Badge>
                                        </td>
                                        <td className="space-x-1 p-3 text-right">
                                            {c.status === 'approved' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/finance/charges/${c.id}/transition`,
                                                            {
                                                                status: 'posted',
                                                            },
                                                        )
                                                    }
                                                >
                                                    Post
                                                </Button>
                                            )}
                                            {c.status === 'posted' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        const r =
                                                            prompt(
                                                                'Reversal reason',
                                                            );
                                                        if (r)
                                                            router.post(
                                                                `/admin/finance/charges/${c.id}/reverse`,
                                                                { reason: r },
                                                            );
                                                    }}
                                                >
                                                    Reverse
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {charges.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No charges.
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Service</th>
                                    <th className="p-3">Branch</th>
                                    <th className="p-3">Price</th>
                                    <th className="p-3">Effective</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {prices.data.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3">
                                            {p.service_name}
                                        </td>
                                        <td className="p-3">
                                            {p.branch_id ?? 'All'}
                                        </td>
                                        <td className="p-3 font-medium">
                                            {money(p.price)}
                                        </td>
                                        <td className="p-3 text-xs text-muted-foreground">
                                            {p.effective_from ?? '—'}
                                            {p.effective_to
                                                ? ` → ${p.effective_to}`
                                                : ''}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    p.status === 'approved'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {p.status}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-right">
                                            {p.status === 'draft' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/finance/prices/${p.id}/transition`,
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
                        {prices.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No prices configured.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
