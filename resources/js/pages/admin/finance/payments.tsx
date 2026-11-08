import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
export default function Payments({ dashboard, payments, unpaid, methods }: { dashboard: R; payments: { data: R[] }; unpaid: R[]; branches: R[]; methods: string[] }) {
    const money = (n: number | string) => new Intl.NumberFormat('en-TZ', { style: 'currency', currency: 'TZS', maximumFractionDigits: 0 }).format(Number(n));
    return (
        <>
            <Head title="Payments" />
            <div className="space-y-6 p-6">
                <Heading title="Payments & Cashier" description="Collection and receipts" />
                {dashboard.shift ? <Card><CardContent className="p-4 flex items-center justify-between text-sm"><span>Shift open · Opening {money(dashboard.shift.opening_balance)} · Received {money(dashboard.received_today)} · {dashboard.count_today} txns</span><Button size="sm" variant="outline" onClick={() => { const a = prompt('Actual counted cash'); if (a) router.post(`/admin/payments/shifts/${dashboard.shift.id}/close`, { actual_closing: a }); }}>Close shift</Button></CardContent></Card>
                : <Card><CardContent className="p-4 flex items-center justify-between text-sm"><span className="text-muted-foreground">No open shift</span><Button size="sm" onClick={() => { const b = prompt('Opening balance (TZS)', '0'); if (b !== null) router.post('/admin/payments/shifts/open', { branch_id: 1, opening_balance: b }); }}>Open shift</Button></CardContent></Card>}
                <Card><CardContent className="p-5"><h2 className="mb-3 font-medium">Record payment</h2>
                    <Form action="/admin/payments/transactions" method="post">{() => (<div className="grid gap-3 sm:grid-cols-5"><select name="invoice_id" required className="h-9 rounded-md border border-input bg-transparent px-2 text-sm"><option value="">Invoice</option>{unpaid.map((i) => <option key={i.id} value={i.id}>{i.invoice_number} · {i.patient.first_name} · {money(i.total)}</option>)}</select>
                    <Input name="patient_id" placeholder="Patient ID" required /><Input name="branch_id" placeholder="Branch ID" required />
                    <select name="method" required className="h-9 rounded-md border border-input bg-transparent px-2 text-sm">{methods.map((m) => <option key={m} value={m}>{m.replace(/_/g,' ')}</option>)}</select>
                    <Input name="amount" type="number" step="0.01" placeholder="Amount" required /><Input name="reference" placeholder="Ref (optional)" />
                    <Button type="submit">Record</Button></div>)}</Form>
                </CardContent></Card>
                <Card><CardContent className="p-0"><table className="w-full text-sm"><thead><tr className="border-b text-left text-muted-foreground"><th className="p-3">Payment</th><th className="p-3">Patient</th><th className="p-3">Invoice</th><th className="p-3">Amount</th><th className="p-3">Method</th><th className="p-3">Status</th><th className="p-3"></th></tr></thead>
                <tbody>{payments.data.map((p) => <tr key={p.id} className="border-b last:border-0"><td className="p-3 font-mono text-xs">{p.payment_number}</td><td className="p-3">{p.patient.first_name} {p.patient.last_name}</td><td className="p-3 font-mono text-xs">{p.invoice?.invoice_number ?? '—'}</td><td className="p-3 font-medium">{money(p.amount)}</td><td className="p-3">{p.method.replace(/_/g,' ')}</td><td className="p-3"><Badge variant={p.status === 'successful' ? 'default' : 'secondary'}>{p.status.replace(/_/g,' ')}</Badge></td>
                <td className="p-3 text-right space-x-1">{['initiated','pending'].includes(p.status) && <Button size="sm" onClick={() => router.post(`/admin/payments/transactions/${p.id}/confirm`)}>Confirm</Button>}
                {p.status === 'successful' && <Button size="sm" variant="outline" asChild><a href={`/admin/payments/transactions/${p.id}/receipt`} target="_blank">Receipt</a></Button>}
                {p.status === 'successful' && <Button size="sm" variant="outline" onClick={() => { const a = prompt('Refund amount'); const r = prompt('Reason'); if (a && r) router.post(`/admin/payments/transactions/${p.id}/refund`, { amount: a, reason: r }); }}>Refund</Button>}</td></tr>)}</tbody></table>
                {payments.data.length === 0 && <p className="p-6 text-center text-sm text-muted-foreground">No payments.</p>}</CardContent></Card>
            </div>
        </>
    );
}
