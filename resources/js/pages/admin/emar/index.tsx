import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function Emar({ dashboard, orders, dueDoses }: { dashboard: R; orders: { data: R[] }; dueDoses: R[]; catalogue: R[]; admissions: R[]; types: string[]; routes: string[] }) {
    return (
        <>
            <Head title="eMAR" />
            <div className="space-y-6 p-6">
                <Heading title="eMAR" description="Electronic Medication Administration Record" />
                <div className="grid gap-4 sm:grid-cols-6">{Object.entries(dashboard).map(([k, v]) => <Card key={k}><CardContent className="p-3 text-center"><p className="text-xs text-muted-foreground capitalize">{k.replace(/_/g,' ')}</p><p className="text-xl font-semibold">{v}</p></CardContent></Card>)}</div>
                <Card><CardContent className="p-5"><h2 className="mb-3 font-medium">Due now ({dueDoses.length})</h2>
                    <table className="w-full text-sm"><tbody>{dueDoses.map((d) => <tr key={d.id} className="border-b last:border-0"><td className="p-2 font-mono text-xs">{d.order.generic_name}</td><td className="p-2">{d.order.dose} {d.order.route}</td><td className="p-2">{d.patient.first_name} {d.patient.last_name}</td><td className="p-2 text-muted-foreground">{new Date(d.scheduled_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</td>
                    <td className="p-2 text-right space-x-1"><Button size="sm" onClick={() => router.post(`/admin/medications/doses/${d.id}/administer`, { actual_dose: d.order.dose, actual_route: d.order.route })}>Give</Button><Button size="sm" variant="outline" onClick={() => { const s = prompt('Status: missed/refused/withheld/omitted'); const r = prompt('Reason'); if (s && r) router.post(`/admin/medications/doses/${d.id}/non-admin`, { status: s, reason: r }); }}>Skip</Button></td></tr>)}</tbody></table>
                    {dueDoses.length === 0 && <p className="py-4 text-center text-sm text-muted-foreground">No doses due.</p>}
                </CardContent></Card>
                <Card><CardContent className="p-0"><table className="w-full text-sm"><thead><tr className="border-b text-left text-muted-foreground"><th className="p-3">Order</th><th className="p-3">Patient</th><th className="p-3">Medication</th><th className="p-3">Dose</th><th className="p-3">Status</th><th className="p-3"></th></tr></thead>
                <tbody>{orders.data.map((o) => <tr key={o.id} className="border-b last:border-0"><td className="p-3 font-mono text-xs">{o.order_number}</td><td className="p-3">{o.patient.first_name} {o.patient.last_name}</td><td className="p-3">{o.generic_name}</td><td className="p-3">{o.dose} {o.dose_unit} {o.route}</td><td className="p-3"><Badge>{o.status}</Badge></td>
                <td className="p-3 text-right">{o.status === 'active' && <Button size="sm" variant="outline" onClick={() => router.post(`/admin/medications/orders/${o.id}/generate-doses`, { days: 3 })}>Gen doses</Button>}</td></tr>)}</tbody></table></CardContent></Card>
            </div>
        </>
    );
}
