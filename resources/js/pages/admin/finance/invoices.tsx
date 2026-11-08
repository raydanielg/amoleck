import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function Invoices({
    invoices,
    postable,
}: {
    invoices: { data: R[] };
    postable: R[];
    branches: R[];
    patients: R[];
}) {
    const money = (n: number | string) =>
        new Intl.NumberFormat('en-TZ', {
            style: 'currency',
            currency: 'TZS',
            maximumFractionDigits: 0,
        }).format(Number(n));
    const generate = () => {
        const pid = prompt('Patient ID');
        const ids = prompt('Charge IDs (comma)');
        if (pid && ids)
            router.post('/admin/billing/invoices', {
                patient_id: pid,
                branch_id: postable[0]?.branch_id ?? 1,
                charge_ids: ids.split(',').map(Number),
            });
    };
    return (
        <>
            <Head title="Invoices" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Billing"
                        description="Invoices from posted charges"
                    />
                    <Button size="sm" onClick={generate}>
                        Generate invoice
                    </Button>
                </div>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Invoice</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Total</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Due</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.data.map((i) => (
                                    <tr
                                        key={i.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            <Link
                                                href={`/admin/billing/invoices/${i.id}`}
                                                className="hover:underline"
                                            >
                                                {i.invoice_number}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            {i.patient.first_name}{' '}
                                            {i.patient.last_name}
                                        </td>
                                        <td className="p-3 font-medium">
                                            {money(i.total)}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    i.status === 'issued'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {i.status.replace(/_/g, ' ')}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-xs text-muted-foreground">
                                            {i.due_date ?? '—'}
                                        </td>
                                        <td className="p-3 text-right">
                                            {i.status === 'approved' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/billing/invoices/${i.id}/transition`,
                                                            {
                                                                status: 'issued',
                                                            },
                                                        )
                                                    }
                                                >
                                                    Issue
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {invoices.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No invoices.
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">
                            Uninvoiced posted charges ({postable.length})
                        </h2>
                        <table className="w-full text-sm">
                            <tbody>
                                {postable.map((c) => (
                                    <tr
                                        key={c.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-2 font-mono text-xs">
                                            {c.charge_number}
                                        </td>
                                        <td className="p-2">
                                            {c.patient.first_name}{' '}
                                            {c.patient.last_name}
                                        </td>
                                        <td className="p-2">
                                            {c.service?.name ?? '—'}
                                        </td>
                                        <td className="p-2">
                                            {money(c.total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {postable.length === 0 && (
                            <p className="py-3 text-sm text-muted-foreground">
                                All posted charges are invoiced.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
