import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function InvoiceShow({
    invoice,
    outstanding,
    aging,
}: {
    invoice: R;
    outstanding: number;
    aging: string;
}) {
    const money = (n: number | string) =>
        new Intl.NumberFormat('en-TZ', {
            style: 'currency',
            currency: 'TZS',
            maximumFractionDigits: 0,
        }).format(Number(n));
    const i = invoice;
    const post = (status: string, extra = {}) =>
        router.post(`/admin/billing/invoices/${i.id}/transition`, {
            status,
            ...extra,
        });
    return (
        <>
            <Head title={i.invoice_number} />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={i.invoice_number}
                        description={`${i.patient.first_name} ${i.patient.last_name} · ${i.patient.mrn} · ${i.branch.name}`}
                    />
                    <Badge>{i.status.replace(/_/g, ' ')}</Badge>
                </div>
                <Card>
                    <CardContent className="p-5">
                        <table className="w-full text-sm">
                            <tbody>
                                {i.items.map((it: R) => (
                                    <tr
                                        key={it.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-2">
                                            {it.service_name}
                                        </td>
                                        <td className="p-2">
                                            {it.quantity}×{money(it.unit_price)}
                                        </td>
                                        <td className="p-2 text-right">
                                            {money(it.line_total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="mt-3 text-right text-sm">
                            <p>
                                Subtotal: {money(i.subtotal)} · Tax:{' '}
                                {money(i.tax)}
                            </p>
                            <p className="font-semibold">
                                Total: {money(i.total)} · Outstanding:{' '}
                                {money(outstanding)}{' '}
                                {aging !== 'n/a' && (
                                    <Badge variant="outline">{aging}</Badge>
                                )}
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <div className="flex flex-wrap gap-2">
                    {i.status === 'draft' && (
                        <Button
                            size="sm"
                            onClick={() => post('pending_review')}
                        >
                            Submit
                        </Button>
                    )}
                    {i.status === 'pending_review' && (
                        <Button size="sm" onClick={() => post('approved')}>
                            Approve
                        </Button>
                    )}
                    {i.status === 'approved' && (
                        <Button size="sm" onClick={() => post('issued')}>
                            Issue
                        </Button>
                    )}
                    {['issued', 'overdue', 'partially_paid'].includes(
                        i.status,
                    ) && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                const a = prompt('Credit amount');
                                const r = prompt('Reason');
                                if (a && r)
                                    router.post(
                                        `/admin/billing/invoices/${i.id}/credit-note`,
                                        {
                                            amount: a,
                                            reason: r,
                                            type: 'partial',
                                        },
                                    );
                            }}
                        >
                            Credit note
                        </Button>
                    )}
                    {!['issued', 'paid', 'cancelled', 'credited'].includes(
                        i.status,
                    ) && (
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => {
                                const r = prompt('Cancel reason');
                                if (r) post('cancelled', { reason: r });
                            }}
                        >
                            Cancel
                        </Button>
                    )}
                    <Button size="sm" variant="outline" asChild>
                        <a
                            href={`/admin/billing/invoices/${i.id}/print`}
                            target="_blank"
                        >
                            Print
                        </a>
                    </Button>
                </div>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-2 font-medium">History</h2>
                        <ul className="space-y-1 text-sm">
                            {i.status_histories.map((h: R) => (
                                <li key={h.id}>
                                    {h.from_status ?? '—'} → {h.to_status} ·{' '}
                                    {h.changed_by?.name} ·{' '}
                                    {new Date(h.created_at).toLocaleString()}
                                    {h.reason ? ` · ${h.reason}` : ''}
                                </li>
                            ))}
                            {i.credit_notes.map((c: R) => (
                                <li key={c.id}>
                                    {c.credit_number} · {money(c.amount)} ·{' '}
                                    {c.reason}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
