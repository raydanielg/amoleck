import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
export default function Recovery({
    records, areas, beds, handovers, wards, icuBeds, branches, branchId, statuses,
}: {
    records: { data: R[] }; areas: R[]; beds: R[]; handovers: R[];
    wards: R[]; icuBeds: R[]; branches: R[]; branchId: number; statuses: string[];
}) {
    const post = (u: string, d: R = {}) => router.post(u, d);
    const availBeds = beds.filter((b) => b.status === 'available');
    return (
        <>
            <Head title="Recovery / PACU" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading title="Recovery / PACU" description="Post-anesthesia care & handover" />
                </div>

                {handovers.length > 0 && (
                    <Card className="border-amber-300"><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">Pending handovers</h3>
                        {handovers.map((h) => (
                            <div key={h.id} className="mb-1 flex items-center justify-between border-b pb-1 text-sm last:border-0">
                                <span>{h.patient?.first_name} {h.patient?.last_name} ({h.case?.case_number}) → {h.destination_type}</span>
                                <Button size="sm" onClick={() => post(`/admin/surgery/handovers/${h.id}/acknowledge`)}>Acknowledge receipt</Button>
                            </div>
                        ))}
                    </CardContent></Card>
                )}

                <Card><CardContent className="p-4">
                    <h3 className="mb-3 text-sm font-medium">PACU configuration</h3>
                    <div className="flex flex-wrap gap-4">
                        <Form method="post" action="/admin/surgery/recovery/areas">
                            {() => (
                                <div className="flex items-end gap-1">
                                    <Input name="code" placeholder="Area code" required className="h-8 w-24" />
                                    <Input name="name" placeholder="Area name" required className="h-8 w-36" />
                                    <input type="hidden" name="branch_id" value={branchId} />
                                    <Button size="sm" type="submit">Add area</Button>
                                </div>
                            )}
                        </Form>
                        <Form method="post" action="/admin/surgery/recovery/beds">
                            {() => (
                                <div className="flex items-end gap-1">
                                    <Input name="code" placeholder="Bed code" required className="h-8 w-24" />
                                    <select name="recovery_area_id" required className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                        {areas.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                    </select>
                                    <Button size="sm" type="submit">Add bed</Button>
                                </div>
                            )}
                        </Form>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-2 text-xs">
                        {beds.map((b) => (
                            <span key={b.id} className="rounded border px-2 py-1">
                                {b.code} <Badge variant={b.status === 'available' ? 'default' : 'secondary'}>{b.status}</Badge>
                                {b.status === 'cleaning' && (
                                    <button className="ml-1 underline" onClick={() => post(`/admin/surgery/recovery-beds/${b.id}/clean`)}>clean done</button>
                                )}
                            </span>
                        ))}
                    </div>
                </CardContent></Card>

                <Card><CardContent className="p-0">
                    <table className="w-full text-sm">
                        <thead className="border-b text-left text-xs text-muted-foreground">
                            <tr><th className="p-3">Record</th><th className="p-3">Patient</th><th className="p-3">Bed</th><th className="p-3">Status</th><th className="p-3">Actions</th></tr>
                        </thead>
                        <tbody>
                            {records.data.map((r) => (
                                <tr key={r.id} className="border-b align-top last:border-0">
                                    <td className="p-3 font-mono text-xs">{r.record_number}<br />{r.case?.case_number}</td>
                                    <td className="p-3">{r.patient?.first_name} {r.patient?.last_name}<br /><span className="text-xs text-muted-foreground">{r.patient?.mrn}</span></td>
                                    <td className="p-3">{r.bed?.code ?? '—'}</td>
                                    <td className="p-3"><Badge variant="secondary">{r.status.replaceAll('_', ' ')}</Badge>
                                        {(r.assessments ?? []).length > 0 && <p className="mt-1 text-[10px] text-muted-foreground">last: {r.assessments[0].outcome ?? 'obs'} @ {r.assessments[0].assessed_at?.slice(11, 16)}</p>}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex flex-wrap gap-1">
                                            {r.status === 'awaiting_recovery' && (
                                                <Form method="post" action={`/admin/surgery/recovery/${r.id}/receive`}>
                                                    {() => (
                                                        <div className="flex gap-1">
                                                            <select name="recovery_bed_id" required className="h-7 rounded border border-input bg-transparent px-1 text-xs">
                                                                {availBeds.map((b) => <option key={b.id} value={b.id}>{b.code}</option>)}
                                                            </select>
                                                            <Button size="sm" type="submit" className="h-7">Receive</Button>
                                                        </div>
                                                    )}
                                                </Form>
                                            )}
                                            {['received', 'under_observation', 'reassessment_required', 'ready_for_review'].includes(r.status) && (
                                                <Form method="post" action={`/admin/surgery/recovery/${r.id}/assess`}>
                                                    {() => (
                                                        <div className="flex gap-1">
                                                            <select name="outcome" required className="h-7 rounded border border-input bg-transparent px-1 text-xs">
                                                                <option value="progressing">progressing</option>
                                                                <option value="reassessment_required">reassess</option>
                                                                <option value="ready_for_transfer">ready</option>
                                                                <option value="escalated">escalate</option>
                                                            </select>
                                                            <Input name="notes" placeholder="notes" className="h-7 w-28" />
                                                            <Button size="sm" type="submit" className="h-7">Assess</Button>
                                                        </div>
                                                    )}
                                                </Form>
                                            )}
                                            {['ready_for_review', 'under_observation', 'reassessment_required'].includes(r.status) && (
                                                <Button size="sm" variant="outline" className="h-7" onClick={() => post(`/admin/surgery/recovery/${r.id}/clear`, { disposition: 'ward' })}>Clear for transfer</Button>
                                            )}
                                            {r.status === 'cleared_for_transfer' && (
                                                <Form method="post" action={`/admin/surgery/recovery/${r.id}/handover`}>
                                                    {() => (
                                                        <div className="flex gap-1">
                                                            <select name="destination_type" required className="h-7 rounded border border-input bg-transparent px-1 text-xs">
                                                                <option value="ward">ward</option><option value="surgical_ward">surgical ward</option>
                                                                <option value="icu">ICU</option><option value="hdu">HDU</option><option value="other">other</option>
                                                            </select>
                                                            <select name="destination_ward_id" className="h-7 rounded border border-input bg-transparent px-1 text-xs">
                                                                <option value="">— ward —</option>
                                                                {wards.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                                                            </select>
                                                            <Button size="sm" type="submit" className="h-7">Send handover</Button>
                                                        </div>
                                                    )}
                                                </Form>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {records.data.length === 0 && (
                                <tr><td colSpan={5} className="p-6 text-center text-muted-foreground">No recovery records.</td></tr>
                            )}
                        </tbody>
                    </table>
                </CardContent></Card>
            </div>
        </>
    );
}
