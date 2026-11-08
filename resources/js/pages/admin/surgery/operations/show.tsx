import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
const TABS = ['Safety', 'Procedures', 'Op Note', 'Anesthesia', 'Counts', 'Specimens', 'Materials'] as const;
const ta = (name: string, label: string, val?: string | null, rows = 2) => (
    <div key={name}>
        <label className="text-xs text-muted-foreground">{label}</label>
        <textarea name={name} defaultValue={val ?? ''} rows={rows} className="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm" />
    </div>
);

export default function OperationShow({
    operation: o, templates, batches, medications,
}: {
    operation: R; templates: R; batches: R[]; medications: R[];
}) {
    const [tab, setTab] = useState<(typeof TABS)[number]>('Safety');
    const post = (url: string, data: R = {}) => router.post(url, data);
    const c = o.case;
    const note = o.operative_note;
    const an = o.anesthesia_record;
    const ver = (s: string) => (c?.safety_verifications ?? []).find((v: R) => v.stage === s);
    const live = o.status === 'in_progress';

    return (
        <>
            <Head title={`Op ${o.operation_number}`} />
            <div className="space-y-5 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title={`${o.operation_number} — ${o.patient?.first_name} ${o.patient?.last_name}`}
                        description={`${o.patient?.mrn} · ${o.theatre?.name ?? 'no theatre'} · ${c?.case_number}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge variant={live ? 'default' : 'secondary'}>{o.status.replaceAll('_', ' ')}</Badge>
                        {live && (
                            <>
                                <Button size="sm" onClick={() => post(`/admin/surgery/operations/${o.id}/complete`, {
                                    postoperative_destination: 'pacu',
                                    postop_diagnosis: note?.postop_diagnosis, findings: note?.findings, narrative: note?.narrative,
                                })}>Complete operation</Button>
                                <Button size="sm" variant="destructive" onClick={() => { const r = prompt('Abort reason'); if (r) post(`/admin/surgery/operations/${o.id}/abort`, { reason: r }); }}>Abort</Button>
                            </>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-3 text-center sm:grid-cols-6">
                    {[['Patient in', o.patient_in_at], ['Anesthesia start', o.anesthesia_start_at], ['Proc start', o.procedure_start_at], ['Proc end', o.procedure_end_at], ['Anesthesia end', o.anesthesia_end_at], ['Patient out', o.patient_out_at]].map(([l, v]) => (
                        <Card key={l as string}><CardContent className="p-2">
                            <p className="text-[10px] text-muted-foreground">{l}</p>
                            <p className="text-xs font-medium">{v ? String(v).slice(11, 16) : '—'}</p>
                        </CardContent></Card>
                    ))}
                </div>

                <div className="flex flex-wrap gap-1 border-b">
                    {TABS.map((t) => (
                        <button key={t} onClick={() => setTab(t)} className={`px-3 py-1.5 text-xs ${tab === t ? 'border-b-2 border-primary font-medium' : 'text-muted-foreground'}`}>{t}</button>
                    ))}
                </div>

                {tab === 'Safety' && (
                    <div className="grid gap-4 lg:grid-cols-3">
                        {(['sign_in', 'time_out', 'sign_out'] as const).map((stage) => {
                            const v = ver(stage);
                            const items = templates[stage] ?? [];
                            return (
                                <Card key={stage}><CardContent className="p-4">
                                    <div className="mb-2 flex items-center justify-between">
                                        <h3 className="text-sm font-medium">{stage.replaceAll('_', ' ').toUpperCase()}</h3>
                                        {v ? <Badge>{v.status}</Badge> : <Badge variant="outline">pending</Badge>}
                                    </div>
                                    {v ? (
                                        <div className="space-y-1 text-xs">
                                            {(v.items ?? []).map((i: R) => <p key={i.id}>{i.checked ? '✓' : '✗'} {i.label}</p>)}
                                        </div>
                                    ) : (
                                        <Form method="post" action={`/admin/surgery/cases/${c.id}/safety/verify`} transformData={(d) => ({
                                            stage, notes: d.notes,
                                            items: items.map((it: R, ix: number) => ({ key: it.key, checked: d[`chk_${ix}`] ? true : false, value: d[`val_${ix}`] ?? null })),
                                        })}>
                                            {() => (
                                                <div className="space-y-1 text-sm">
                                                    <input type="hidden" name="stage" value={stage} />
                                                    {items.map((it: R, ix: number) => (
                                                        <div key={it.key} className="flex items-center gap-2">
                                                            <input type="checkbox" name={`chk_${ix}`} />
                                                            <span className="flex-1 text-xs">{it.label}{it.mandatory ? ' *' : ''}</span>
                                                        </div>
                                                    ))}
                                                    <Button size="sm" type="submit" className="mt-2">Verify {stage.replaceAll('_', ' ')}</Button>
                                                </div>
                                            )}
                                        </Form>
                                    )}
                                </Card>
                            );
                        })}
                        <Card className="lg:col-span-3"><CardContent className="p-4">
                            <h3 className="mb-2 text-sm font-medium">Documented exception</h3>
                            <Form method="post" action={`/admin/surgery/cases/${c.id}/safety/exception`}>
                                {() => (
                                    <div className="flex flex-wrap items-end gap-2">
                                        <select name="stage" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                            {['sign_in', 'time_out', 'sign_out'].map((s) => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                        <Input name="item_key" placeholder="item key" required className="h-8 w-40" />
                                        <Input name="reason" placeholder="Reason" required className="h-8 w-64" />
                                        <select name="authorized_by" required className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                            <option value="">Authorized by…</option>
                                            {(c.team_assignments ?? []).map((a: R) => <option key={a.user_id} value={a.user_id}>{a.user?.name}</option>)}
                                        </select>
                                        <Button size="sm" type="submit">Record exception</Button>
                                    </div>
                                )}
                            </Form>
                        </CardContent></Card>
                    </div>
                )}

                {tab === 'Procedures' && (
                    <Card><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">Actual procedures performed</h3>
                        {(o.procedures ?? []).map((p: R) => (
                            <div key={p.id} className="mb-1 flex justify-between border-b pb-1 text-sm last:border-0">
                                <span>{p.procedure_name}</span><Badge variant="outline">{p.kind}</Badge>
                            </div>
                        ))}
                        {live && (
                            <Form method="post" action={`/admin/surgery/operations/${o.id}/procedures`}>
                                {() => (
                                    <div className="mt-3 flex items-end gap-2">
                                        <Input name="procedure_name" placeholder="Procedure performed" required className="h-8 w-64" />
                                        <select name="kind" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                            {['performed', 'additional', 'changed', 'incomplete', 'aborted'].map((k) => <option key={k} value={k}>{k}</option>)}
                                        </select>
                                        <Button size="sm" type="submit">Record</Button>
                                    </div>
                                )}
                            </Form>
                        )}
                    </CardContent></Card>
                )}

                {tab === 'Op Note' && (
                    <Card><CardContent className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-medium">Operative note {note && <Badge variant="secondary" className="ml-2">{note.status} · v{note.version}</Badge>}</h3>
                            {note && note.status !== 'finalized' && note.status !== 'amended' && (
                                <Button size="sm" onClick={() => post(`/admin/surgery/notes/${note.id}/transition`, { status: 'finalized' })}>Finalize</Button>
                            )}
                        </div>
                        {note?.status === 'finalized' ? (
                            <div className="space-y-3 text-sm">
                                {[['Pre-op diagnosis', note.preop_diagnosis], ['Post-op diagnosis', note.postop_diagnosis], ['Findings', note.findings], ['Technique', note.technique], ['Narrative', note.narrative], ['Instructions', note.instructions]].map(([l, v]) => (
                                    <div key={l as string}><p className="text-[11px] text-muted-foreground">{l}</p><p className="whitespace-pre-wrap">{v as string ?? '—'}</p></div>
                                ))}
                                <p className="text-xs text-muted-foreground">Finalized {note.finalized_at?.slice(0, 16).replace('T', ' ')} · {note.versions?.length} version(s)</p>
                                <Form method="post" action={`/admin/surgery/notes/${note.id}/amend`}>
                                    {() => (
                                        <div className="space-y-2">
                                            <Input name="reason" placeholder="Amendment reason (required)" required className="h-8" />
                                            <textarea name="narrative" placeholder="Corrected narrative" rows={2} className="w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm" />
                                            <Button size="sm" variant="outline" type="submit">Amend (preserves original)</Button>
                                        </div>
                                    )}
                                </Form>
                            </div>
                        ) : (
                            <Form method="post" action={`/admin/surgery/operations/${o.id}/notes`}>
                                {() => (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {ta('preop_diagnosis', 'Pre-op diagnosis', note?.preop_diagnosis)}
                                        {ta('postop_diagnosis', 'Post-op diagnosis', note?.postop_diagnosis)}
                                        {ta('findings', 'Operative findings', note?.findings)}
                                        {ta('technique', 'Technique', note?.technique)}
                                        {ta('narrative', 'Procedure narrative', note?.narrative, 4)}
                                        {ta('instructions', 'Post-op instructions', note?.instructions)}
                                        <div className="self-end"><Button size="sm" type="submit">Save note</Button></div>
                                    </div>
                                )}
                            </Form>
                        )}
                    </CardContent></Card>
                )}

                {tab === 'Anesthesia' && (
                    <Card><CardContent className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-medium">Anesthesia record {an && <Badge variant="secondary" className="ml-2">{an.status}</Badge>}</h3>
                            {an?.status === 'open' && <Button size="sm" variant="outline" onClick={() => post(`/admin/surgery/anesthesia/${an.id}/close`)}>Close record</Button>}
                        </div>
                        <Form method="post" action={`/admin/surgery/operations/${o.id}/anesthesia`}>
                            {() => (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="text-xs text-muted-foreground">Technique</label>
                                        <select name="technique" defaultValue={an?.technique ?? ''} className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                            <option value="">—</option>
                                            {['general', 'regional', 'local', 'sedation', 'other'].map((t) => <option key={t} value={t}>{t}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-xs text-muted-foreground">Ended</label>
                                        <Input type="datetime-local" name="ended_at" className="mt-1 h-8" />
                                    </div>
                                    {ta('observations', 'Observations', an?.observations)}
                                    {ta('airway_management', 'Airway management', an?.airway_management)}
                                    {ta('fluids', 'Fluids', an?.fluids)}
                                    {ta('interventions', 'Interventions', an?.interventions)}
                                    {ta('adverse_events', 'Adverse events', an?.adverse_events)}
                                    {ta('handover_notes', 'Handover notes', an?.handover_notes)}
                                    <div className="self-end"><Button size="sm" type="submit">Save</Button></div>
                                </div>
                            )}
                        </Form>
                        {an && (
                            <div className="mt-4">
                                <h4 className="mb-1 text-xs font-medium">eMAR administrations linked ({an.medication_refs?.length ?? 0})</h4>
                                {(an.medication_refs ?? []).map((r: R) => (
                                    <p key={r.id} className="text-xs">#{r.medication_administration_id} — {r.administration?.actual_dose} {r.administration?.actual_route} at {r.administration?.administered_at?.slice(11, 16)}</p>
                                ))}
                                <Form method="post" action={`/admin/surgery/anesthesia/${an.id}/link-medication`}>
                                    {() => (
                                        <div className="mt-1 flex items-end gap-2">
                                            <select name="medication_administration_id" required className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                                <option value="">— eMAR administration —</option>
                                                {medications.map((m) => <option key={m.id} value={m.id}>#{m.id} {m.actual_dose} {m.actual_route} {m.administered_at?.slice(0, 16).replace('T', ' ')}</option>)}
                                            </select>
                                            <Button size="sm" type="submit">Link</Button>
                                        </div>
                                    )}
                                </Form>
                            </div>
                        )}
                    </CardContent></Card>
                )}

                {tab === 'Counts' && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {(o.counts ?? []).map((cn: R) => (
                            <Card key={cn.id}><CardContent className="p-4">
                                <div className="mb-2 flex items-center justify-between">
                                    <h3 className="text-sm font-medium capitalize">{cn.type} — {cn.phase}</h3>
                                    <Badge variant={cn.status === 'discrepancy' ? 'destructive' : 'secondary'}>{cn.status}</Badge>
                                </div>
                                {(cn.items ?? []).map((i: R) => (
                                    <p key={i.id} className="text-xs">{i.item_name}: expected {i.expected_qty} / counted {i.counted_qty}{i.difference !== 0 ? ` (${i.difference > 0 ? '+' : ''}${i.difference})` : ''}</p>
                                ))}
                                {cn.status === 'discrepancy' && (
                                    <Form method="post" action={`/admin/surgery/counts/${cn.id}/resolve`}>
                                        {() => (
                                            <div className="mt-2 flex gap-2">
                                                <Input name="resolution" placeholder="Resolution" required className="h-8" />
                                                <Button size="sm" type="submit">Resolve</Button>
                                            </div>
                                        )}
                                    </Form>
                                )}
                            </CardContent></Card>
                        ))}
                        {live && (
                            <Card><CardContent className="p-4">
                                <h3 className="mb-2 text-sm font-medium">New count</h3>
                                <Form method="post" action={`/admin/surgery/operations/${o.id}/counts`} transformData={(d) => ({
                                    type: d.type, phase: d.phase, discrepancy_notes: d.discrepancy_notes,
                                    items: [{ item_name: d.item_name, expected_qty: Number(d.expected_qty), counted_qty: Number(d.counted_qty) }],
                                })}>
                                    {() => (
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <select name="type" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                                {['instrument', 'swab', 'needle', 'other'].map((t) => <option key={t} value={t}>{t}</option>)}
                                            </select>
                                            <select name="phase" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                                {['initial', 'additional', 'final'].map((p) => <option key={p} value={p}>{p}</option>)}
                                            </select>
                                            <Input name="item_name" placeholder="Item" required className="h-8" />
                                            <div className="flex gap-1">
                                                <Input name="expected_qty" type="number" placeholder="Expected" required className="h-8" />
                                                <Input name="counted_qty" type="number" placeholder="Counted" required className="h-8" />
                                            </div>
                                            <Input name="discrepancy_notes" placeholder="Discrepancy notes" className="h-8 sm:col-span-2" />
                                            <Button size="sm" type="submit">Record count</Button>
                                        </div>
                                    )}
                                </Form>
                            </CardContent></Card>
                        )}
                    </div>
                )}

                {tab === 'Specimens' && (
                    <Card><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">Specimens</h3>
                        {(o.specimens ?? []).map((s: R) => (
                            <div key={s.id} className="mb-1 flex items-center justify-between border-b pb-1 text-sm last:border-0">
                                <span><span className="font-mono text-xs">{s.specimen_number}</span> — {s.description} ({s.anatomical_source ?? '—'}{s.laterality ? `/${s.laterality}` : ''})</span>
                                <div className="flex items-center gap-1">
                                    <Badge variant="outline">{s.status}</Badge>
                                    {s.status === 'collected' && <Button size="sm" variant="outline" onClick={() => post(`/admin/surgery/specimens/${s.id}/dispatch`, {})}>Dispatch</Button>}
                                    {s.status === 'dispatched' && <Button size="sm" variant="outline" onClick={() => post(`/admin/surgery/specimens/${s.id}/receive`, {})}>Receive</Button>}
                                </div>
                            </div>
                        ))}
                        {live && (
                            <Form method="post" action={`/admin/surgery/operations/${o.id}/specimens`}>
                                {() => (
                                    <div className="mt-3 grid gap-2 sm:grid-cols-4">
                                        <Input name="description" placeholder="Description" required className="h-8 sm:col-span-2" />
                                        <Input name="anatomical_source" placeholder="Source" className="h-8" />
                                        <select name="laterality" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                            <option value="">laterality</option>{['left', 'right', 'bilateral', 'midline', 'na'].map((l) => <option key={l} value={l}>{l}</option>)}
                                        </select>
                                        <select name="destination" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                            <option value="">destination</option>{['laboratory', 'pathology', 'other'].map((d) => <option key={d} value={d}>{d}</option>)}
                                        </select>
                                        <Button size="sm" type="submit" className="sm:col-span-1">Register</Button>
                                    </div>
                                )}
                            </Form>
                        )}
                    </CardContent></Card>
                )}

                {tab === 'Materials' && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card><CardContent className="p-4">
                            <h3 className="mb-2 text-sm font-medium">Implants & devices</h3>
                            {(o.implants ?? []).map((i: R) => (
                                <p key={i.id} className="mb-1 border-b pb-1 text-xs last:border-0">{i.item_name} · batch {i.batch_number ?? '—'} · SN {i.serial_number ?? '—'} · exp {i.expiry_date ?? '—'}</p>
                            ))}
                            {live && (
                                <Form method="post" action={`/admin/surgery/operations/${o.id}/implants`}>
                                    {() => (
                                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                            <Input name="item_name" placeholder="Implant name" required className="h-8 sm:col-span-2" />
                                            <Input name="batch_number" placeholder="Batch" className="h-8" />
                                            <Input name="serial_number" placeholder="Serial" className="h-8" />
                                            <Input name="expiry_date" type="date" className="h-8" />
                                            <Input name="manufacturer" placeholder="Manufacturer" className="h-8" />
                                            <Button size="sm" type="submit" className="sm:col-span-2">Record implant</Button>
                                        </div>
                                    )}
                                </Form>
                            )}
                        </CardContent></Card>
                        <Card><CardContent className="p-4">
                            <h3 className="mb-2 text-sm font-medium">Consumables (stock ledger)</h3>
                            {(o.consumables ?? []).map((u: R) => (
                                <p key={u.id} className="mb-1 border-b pb-1 text-xs last:border-0">
                                    {u.item?.name} · {u.qty_used} used{Number(u.qty_returned) > 0 ? ` · ${u.qty_returned} returned` : ''}{Number(u.qty_wasted) > 0 ? ` · ${u.qty_wasted} wasted` : ''} · issue #{u.stock_issue_id}
                                </p>
                            ))}
                            {live && (
                                <Form method="post" action={`/admin/surgery/operations/${o.id}/consumables`}>
                                    {() => (
                                        <div className="mt-2 space-y-2">
                                            <select name="inventory_batch_id" required className="h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm"
                                                onChange={(e) => {
                                                    const f = e.target.closest('form');
                                                    const b = batches.find((x) => String(x.id) === e.target.value);
                                                    const hid = f?.querySelector<HTMLInputElement>('[name=inventory_item_id]');
                                                    if (hid && b) hid.value = String(b.inventory_item_id);
                                                }}>
                                                <option value="">— batch —</option>
                                                {batches.map((b) => <option key={b.id} value={b.id}>{b.item?.name} · {b.batch_number} · qty {b.qty}{b.expiry_date ? ` · exp ${b.expiry_date}` : ''}</option>)}
                                            </select>
                                            <input type="hidden" name="inventory_item_id" />
                                            <div className="flex gap-2">
                                                <Input name="qty_used" type="number" step="0.001" min="0.001" placeholder="Qty used" required className="h-8" />
                                                <Button size="sm" type="submit">Issue</Button>
                                            </div>
                                        </div>
                                    )}
                                </Form>
                            )}
                        </CardContent></Card>
                    </div>
                )}
            </div>
        </>
    );
}
