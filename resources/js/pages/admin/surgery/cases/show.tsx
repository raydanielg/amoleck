import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
const TABS = [
    'Overview', 'Consultation', 'Pre-op', 'Anesthesia', 'Consent',
    'Scheduling', 'Team', 'Checklist', 'Safety', 'Blood', 'History',
] as const;

const ta = (name: string, label: string, val?: string | null, rows = 2) => (
    <div key={name}>
        <label className="text-xs text-muted-foreground">{label}</label>
        <textarea name={name} defaultValue={val ?? ''} rows={rows} className="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm" />
    </div>
);
const sel = (n: string, l: string, opts: { id: number; name?: string }[], req = true, def?: string | number) => (
    <div className="min-w-[150px] flex-1" key={n}>
        <label className="text-xs text-muted-foreground">{l}</label>
        <select name={n} required={req} defaultValue={def ?? ''} className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
            {!req && <option value="">—</option>}
            {opts.map((o) => (
                <option key={o.id} value={o.id}>{o.name}</option>
            ))}
        </select>
    </div>
);
const V = ({ l, v }: { l: string; v: any }) => (
    <div>
        <p className="text-[11px] text-muted-foreground">{l}</p>
        <p className="text-sm whitespace-pre-wrap">{v ?? '—'}</p>
    </div>
);

export default function CaseShow({
    case: c,
    blockers,
    transitions,
    priorities,
    users,
}: {
    case: R;
    blockers: string[];
    transitions: string[];
    priorities: string[];
    users: R[];
    statuses: string[];
}) {
    const [tab, setTab] = useState<(typeof TABS)[number]>('Overview');
    const post = (url: string, data: R = {}) => router.post(url, data);
    const transition = (status: string) => {
        const reason = ['cancelled', 'rejected', 'postponed'].includes(status)
            ? prompt('Reason (required)') : undefined;
        if (reason === null) return;
        post(`/admin/surgery/cases/${c.id}/transition`, { status, reason });
    };
    const preop = c.preop_assessment;
    const apre = c.anesthesia_preassessment;
    const op = c.operations?.find((o: R) => o.status === 'in_progress') ?? c.operations?.[0];
    const consentFor = (t: string) => c.consents?.find((x: R) => x.type === t);
    const itemsFor = (stage: string) => (c.checklist_items ?? []).filter((i: R) => i.stage === stage);
    const verFor = (stage: string) => (c.safety_verifications ?? []).find((v: R) => v.stage === stage);

    return (
        <>
            <Head title={`Case ${c.case_number}`} />
            <div className="space-y-5 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title={`${c.case_number} — ${c.patient?.first_name} ${c.patient?.last_name}`}
                        description={`${c.patient?.mrn} · ${c.specialty?.name ?? 'No specialty'} · ${c.procedure?.name ?? 'No procedure'}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge variant={c.priority === 'emergency' ? 'destructive' : 'secondary'}>{c.priority}</Badge>
                        <Badge>{c.status.replaceAll('_', ' ')}</Badge>
                    </div>
                </div>

                {blockers.length > 0 && ['approved', 'awaiting_preoperative_assessment', 'awaiting_consent'].includes(c.status) && (
                    <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                        Readiness blockers: {blockers.map((b) => b.replaceAll('_', ' ')).join(', ')}
                    </div>
                )}

                <div className="flex flex-wrap gap-1 border-b">
                    {TABS.map((t) => (
                        <button
                            key={t}
                            onClick={() => setTab(t)}
                            className={`px-3 py-1.5 text-xs ${tab === t ? 'border-b-2 border-primary font-medium' : 'text-muted-foreground'}`}
                        >
                            {t}
                        </button>
                    ))}
                </div>

                {tab === 'Overview' && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card><CardContent className="space-y-3 p-4">
                            <h3 className="text-sm font-medium">Case details</h3>
                            <div className="grid grid-cols-2 gap-3">
                                <V l="Branch" v={c.branch?.name} />
                                <V l="Ordering clinician" v={c.ordering_clinician?.name} />
                                <V l="Surgeon" v={c.surgeon?.name} />
                                <V l="Proposed date" v={c.proposed_date} />
                                <V l="Indication" v={c.indication} />
                                <V l="Diagnosis" v={c.diagnosis} />
                            </div>
                        </CardContent></Card>
                        <Card><CardContent className="p-4">
                            <h3 className="mb-2 text-sm font-medium">Workflow</h3>
                            <div className="flex flex-wrap gap-2">
                                {transitions.map((t) => (
                                    <Button key={t} size="sm" variant={['cancelled', 'rejected'].includes(t) ? 'destructive' : 'outline'} onClick={() => transition(t)}>
                                        → {t.replaceAll('_', ' ')}
                                    </Button>
                                ))}
                                {transitions.length === 0 && <p className="text-sm text-muted-foreground">No transitions available.</p>}
                            </div>
                            <h3 className="mt-4 mb-2 text-sm font-medium">Diagnoses</h3>
                            {(c.diagnoses ?? []).map((d: R) => (
                                <p key={d.id} className="text-xs"><Badge variant="outline" className="mr-1">{d.type}</Badge>{d.description}</p>
                            ))}
                            <Form method="post" action={`/admin/surgery/cases/${c.id}/diagnoses`}>
                                {() => (
                                    <div className="mt-2 flex gap-2">
                                        <select name="type" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                            <option value="working">working</option><option value="preop">preop</option><option value="postop">postop</option>
                                        </select>
                                        <Input name="description" placeholder="Diagnosis" required className="h-8" />
                                        <Button size="sm" type="submit">Add</Button>
                                    </div>
                                )}
                            </Form>
                        </CardContent></Card>
                        <Card><CardContent className="p-4">
                            <h3 className="mb-2 text-sm font-medium">Status history</h3>
                            <div className="space-y-1 text-xs">
                                {(c.status_histories ?? []).slice().reverse().map((h: R) => (
                                    <div key={h.id} className="flex justify-between border-b pb-1 last:border-0">
                                        <span>{h.from_status ?? '∅'} → <b>{h.to_status}</b>{h.reason ? ` (${h.reason})` : ''}</span>
                                        <span className="text-muted-foreground">{h.actor?.name} · {h.created_at?.slice(0, 16).replace('T', ' ')}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent></Card>
                    </div>
                )}

                {tab === 'Consultation' && (
                    <Card><CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-medium">Surgical consultation {c.consultation && <Badge variant="secondary" className="ml-2">{c.consultation.decision}</Badge>}</h3>
                        <Form method="post" action={`/admin/surgery/cases/${c.id}/consultation`}>
                            {() => (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {ta('presenting_complaint', 'Presenting complaint', c.consultation?.presenting_complaint)}
                                    {ta('clinical_history', 'Relevant history', c.consultation?.clinical_history)}
                                    {ta('previous_treatment', 'Previous treatment', c.consultation?.previous_treatment)}
                                    {ta('examination_summary', 'Examination summary', c.consultation?.examination_summary)}
                                    {ta('indication', 'Clinical indication', c.consultation?.indication)}
                                    {ta('proposed_procedure', 'Proposed procedure', c.consultation?.proposed_procedure)}
                                    {ta('alternatives', 'Alternatives documented', c.consultation?.alternatives)}
                                    {ta('risks', 'Risks & considerations', c.consultation?.risks)}
                                    {ta('expected_benefits', 'Expected benefits', c.consultation?.expected_benefits)}
                                    {ta('recommendation', 'Surgeon recommendation', c.consultation?.recommendation)}
                                    <div>
                                        <label className="text-xs text-muted-foreground">Decision</label>
                                        <select name="decision" defaultValue={c.consultation?.decision ?? 'recommended'} className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                            {['recommended', 'accepted', 'deferred', 'rejected', 'further_assessment', 'referred'].map((d) => (
                                                <option key={d} value={d}>{d.replaceAll('_', ' ')}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="self-end"><Button size="sm" type="submit">Save consultation</Button></div>
                                </div>
                            )}
                        </Form>
                        {(c.consultation?.versions?.length ?? 0) > 0 && (
                            <p className="mt-3 text-xs text-muted-foreground">{c.consultation.versions.length} version(s) preserved.</p>
                        )}
                    </CardContent></Card>
                )}

                {tab === 'Pre-op' && (
                    <Card><CardContent className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-medium">Pre-operative assessment {preop && <Badge variant="secondary" className="ml-2">{preop.status.replaceAll('_', ' ')} · v{preop.version}</Badge>}</h3>
                            {preop && ['in_progress', 'pending_review', 'requires_further_assessment'].includes(preop.status) && (
                                <div className="flex gap-1">
                                    {['cleared', 'cleared_with_conditions', 'not_cleared', 'requires_further_assessment'].map((s) => (
                                        <Button key={s} size="sm" variant={s.includes('not') ? 'destructive' : 'outline'} onClick={() => post(`/admin/surgery/preop/${preop.id}/review`, { status: s })}>
                                            {s.replaceAll('_', ' ')}
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <Form method="post" action={`/admin/surgery/cases/${c.id}/preop`}>
                            {() => (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {ta('medical_history', 'Medical history', preop?.medical_history)}
                                    {ta('previous_surgery', 'Previous surgery', preop?.previous_surgery)}
                                    {ta('current_medications', 'Current medications', preop?.current_medications)}
                                    {ta('allergies_summary', 'Known allergies', preop?.allergies_summary)}
                                    {ta('anesthesia_history', 'Previous anesthesia', preop?.anesthesia_history)}
                                    {ta('clinical_condition', 'Clinical condition', preop?.clinical_condition)}
                                    {ta('examination_findings', 'Examination findings', preop?.examination_findings)}
                                    {ta('functional_status', 'Functional status', preop?.functional_status)}
                                    {ta('investigations_summary', 'Investigations', preop?.investigations_summary)}
                                    {ta('outstanding_investigations', 'Outstanding investigations', preop?.outstanding_investigations)}
                                    {ta('precautions', 'Precautions', preop?.precautions)}
                                    {ta('outstanding_actions', 'Outstanding actions', preop?.outstanding_actions)}
                                    {ta('readiness_recommendation', 'Readiness recommendation', preop?.readiness_recommendation)}
                                    {ta('clinical_notes', 'Clinical notes', preop?.clinical_notes)}
                                    <div className="self-end"><Button size="sm" type="submit">Save assessment</Button></div>
                                </div>
                            )}
                        </Form>
                    </CardContent></Card>
                )}

                {tab === 'Anesthesia' && (
                    <Card><CardContent className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-medium">Anesthesia pre-assessment {apre && <Badge variant="secondary" className="ml-2">{apre.status.replaceAll('_', ' ')} · v{apre.version}</Badge>}</h3>
                            {apre && ['in_progress', 'pending_review', 'requires_further_assessment'].includes(apre.status) && (
                                <div className="flex gap-1">
                                    {['approved', 'not_approved', 'requires_further_assessment'].map((s) => (
                                        <Button key={s} size="sm" variant={s === 'not_approved' ? 'destructive' : 'outline'} onClick={() => post(`/admin/surgery/anesthesia-pre/${apre.id}/review`, { status: s })}>
                                            {s.replaceAll('_', ' ')}
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <Form method="post" action={`/admin/surgery/cases/${c.id}/anesthesia-pre`}>
                            {() => (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {ta('medical_history', 'Medical history', apre?.medical_history)}
                                    {ta('previous_anesthesia', 'Previous anesthesia', apre?.previous_anesthesia)}
                                    {ta('allergies', 'Allergies', apre?.allergies)}
                                    {ta('current_medications', 'Medications', apre?.current_medications)}
                                    {ta('examination', 'Examination', apre?.examination)}
                                    {ta('investigations', 'Investigations', apre?.investigations)}
                                    {ta('airway_assessment', 'Airway assessment', apre?.airway_assessment)}
                                    {ta('considerations', 'Anesthesia considerations', apre?.considerations)}
                                    <div>
                                        <label className="text-xs text-muted-foreground">Proposed technique</label>
                                        <select name="proposed_technique" defaultValue={apre?.proposed_technique ?? ''} className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                            <option value="">—</option>
                                            {['general', 'regional', 'local', 'sedation', 'other'].map((t) => (
                                                <option key={t} value={t}>{t}</option>
                                            ))}
                                        </select>
                                    </div>
                                    {ta('clinical_notes', 'Notes', apre?.clinical_notes)}
                                    <div className="self-end"><Button size="sm" type="submit">Save</Button></div>
                                </div>
                            )}
                        </Form>
                    </CardContent></Card>
                )}

                {tab === 'Consent' && (
                    <div className="space-y-4">
                        {['procedure', 'anesthesia', 'blood_product', 'additional', 'other'].map((t) => {
                            const con = consentFor(t);
                            return (
                                <Card key={t}><CardContent className="p-4">
                                    <div className="mb-2 flex items-center justify-between">
                                        <h3 className="text-sm font-medium capitalize">{t.replaceAll('_', ' ')} consent</h3>
                                        <div className="flex items-center gap-2">
                                            {con ? <Badge variant={con.status === 'consented' ? 'default' : 'secondary'}>{con.status.replaceAll('_', ' ')}</Badge> : <Badge variant="outline">not started</Badge>}
                                            {con?.status === 'consented' && (
                                                <Button size="sm" variant="destructive" onClick={() => { const r = prompt('Withdrawal reason'); if (r) post(`/admin/surgery/consents/${con.id}/withdraw`, { reason: r }); }}>
                                                    Withdraw
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                    <Form method="post" action={`/admin/surgery/cases/${c.id}/consents`}>
                                        {() => (
                                            <div className="grid gap-3 sm:grid-cols-3">
                                                <input type="hidden" name="type" value={t} />
                                                <div>
                                                    <label className="text-xs text-muted-foreground">Status</label>
                                                    <select name="status" defaultValue={con?.status ?? 'pending'} className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                                        {['pending', 'consented', 'declined', 'requires_reconfirmation', 'not_applicable'].map((s) => (
                                                            <option key={s} value={s}>{s.replaceAll('_', ' ')}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="text-xs text-muted-foreground">Decision</label>
                                                    <select name="decision" defaultValue={con?.decision ?? ''} className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                                        <option value="">—</option><option value="consented">consented</option><option value="declined">declined</option>
                                                    </select>
                                                </div>
                                                {sel('clinician_id', 'Responsible clinician', users, false, con?.clinician_id)}
                                                {ta('information_explained', 'Information explained', con?.information_explained, 1)}
                                                {ta('risks_alternatives', 'Risks & alternatives', con?.risks_alternatives, 1)}
                                                <div className="grid grid-cols-3 gap-2">
                                                    <Input name="representative_name" placeholder="Representative" defaultValue={con?.representative_name ?? ''} className="h-8" />
                                                    <Input name="witness_name" placeholder="Witness" defaultValue={con?.witness_name ?? ''} className="h-8" />
                                                    <Input name="interpreter_name" placeholder="Interpreter" defaultValue={con?.interpreter_name ?? ''} className="h-8" />
                                                </div>
                                                <div className="self-end"><Button size="sm" type="submit">Save {t.replaceAll('_', ' ')}</Button></div>
                                            </div>
                                        )}
                                    </Form>
                                </CardContent></Card>
                            );
                        })}
                    </div>
                )}

                {tab === 'Scheduling' && (
                    <div className="space-y-4">
                        <Card><CardContent className="p-4">
                            <div className="mb-2 flex items-center justify-between">
                                <h3 className="text-sm font-medium">Bookings</h3>
                                {['ready_for_scheduling'].includes(c.status) || (c.priority === 'emergency' && !['completed', 'cancelled', 'scheduled'].includes(c.status)) ? (
                                    <Button asChild size="sm"><Link href={`/admin/surgery/calendar?case_id=${c.id}`}>Book theatre</Link></Button>
                                ) : null}
                            </div>
                            {(c.bookings ?? []).map((b: R) => (
                                <div key={b.id} className="mb-2 flex items-center justify-between rounded border p-2 text-sm">
                                    <span>
                                        <span className="font-mono text-xs">{b.booking_number}</span> — {b.theatre?.name} · {b.scheduled_date} {b.start_time}–{b.end_time}
                                        <Badge variant="secondary" className="ml-2">{b.status}</Badge>
                                    </span>
                                    {b.status === 'scheduled' && (
                                        <Button size="sm" variant="outline" onClick={() => { const r = prompt('Cancellation reason'); if (r) post(`/admin/surgery/bookings/${b.id}/cancel`, { reason: r }); }}>Cancel booking</Button>
                                    )}
                                </div>
                            ))}
                            {(c.bookings ?? []).length === 0 && <p className="text-sm text-muted-foreground">No bookings yet.</p>}
                        </CardContent></Card>
                    </div>
                )}

                {tab === 'Team' && (
                    <Card><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">Surgical team</h3>
                        {(c.team_assignments ?? []).map((a: R) => (
                            <div key={a.id} className="mb-1 flex items-center justify-between border-b pb-1 text-sm last:border-0">
                                <span>{a.user?.name} — <span className="capitalize">{a.role.replaceAll('_', ' ')}</span> <Badge variant="outline" className="ml-1">{a.status}</Badge></span>
                                <div className="flex gap-1">
                                    {a.status === 'assigned' && (
                                        <Button size="sm" variant="outline" onClick={() => post(`/admin/surgery/team/${a.id}/confirm`)}>Confirm</Button>
                                    )}
                                    {['assigned', 'confirmed'].includes(a.status) && (
                                        <Button size="sm" variant="destructive" onClick={() => { const r = prompt('Reason'); if (r) post(`/admin/surgery/team/${a.id}/remove`, { reason: r }); }}>Remove</Button>
                                    )}
                                </div>
                            </div>
                        ))}
                        <Form method="post" action={`/admin/surgery/cases/${c.id}/team`}>
                            {() => (
                                <div className="mt-3 flex flex-wrap items-end gap-2">
                                    {sel('user_id', 'Staff member', users)}
                                    <div className="min-w-[160px] flex-1">
                                        <label className="text-xs text-muted-foreground">Role</label>
                                        <select name="role" required className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                            {['lead_surgeon', 'assistant_surgeon', 'anesthetist', 'anesthesia_assistant', 'scrub_nurse', 'circulating_nurse', 'theatre_nurse', 'recovery_nurse', 'other'].map((r) => (
                                                <option key={r} value={r}>{r.replaceAll('_', ' ')}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <Button size="sm" type="submit">Assign</Button>
                                </div>
                            )}
                        </Form>
                    </CardContent></Card>
                )}

                {tab === 'Checklist' && (
                    <Card><CardContent className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-medium">Pre-operative preparation checklist</h3>
                            <Button size="sm" variant="outline" onClick={() => post(`/admin/surgery/cases/${c.id}/checklist/init`, { stage: 'preparation' })}>Load template</Button>
                        </div>
                        {itemsFor('preparation').length === 0 && <p className="text-sm text-muted-foreground">No checklist items — load the active template.</p>}
                        {itemsFor('preparation').map((i: R) => (
                            <div key={i.id} className="mb-1 flex items-center justify-between border-b pb-1 text-sm last:border-0">
                                <span>{i.label} {i.mandatory && <Badge variant="destructive" className="ml-1">mandatory</Badge>}</span>
                                <div className="flex items-center gap-1">
                                    <Badge variant="outline">{i.status}</Badge>
                                    {['pending', 'blocked', 'requires_review'].includes(i.status) && (
                                        <>
                                            <Button size="sm" variant="outline" onClick={() => post(`/admin/surgery/checklist-items/${i.id}/complete`, { status: 'completed' })}>Done</Button>
                                            <Button size="sm" variant="ghost" onClick={() => post(`/admin/surgery/checklist-items/${i.id}/complete`, { status: 'not_applicable' })}>N/A</Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent></Card>
                )}

                {tab === 'Safety' && (
                    <div className="grid gap-4 lg:grid-cols-3">
                        {['sign_in', 'time_out', 'sign_out'].map((stage) => {
                            const v = verFor(stage);
                            return (
                                <Card key={stage}><CardContent className="p-4">
                                    <div className="mb-2 flex items-center justify-between">
                                        <h3 className="text-sm font-medium">{stage.replaceAll('_', ' ').toUpperCase()}</h3>
                                        {v ? <Badge>{v.status}</Badge> : <Badge variant="outline">pending</Badge>}
                                    </div>
                                    {v && (
                                        <div className="space-y-1 text-xs">
                                            {(v.items ?? []).map((i: R) => (
                                                <p key={i.id}>{i.checked ? '✓' : '✗'} {i.label}{i.value ? ` — ${i.value}` : ''}</p>
                                            ))}
                                            <p className="text-muted-foreground">by {v.performer?.name ?? v.performed_by} at {v.performed_at?.slice(0, 16).replace('T', ' ')}</p>
                                        </div>
                                    )}
                                </Card>
                            );
                        })}
                        <Card className="lg:col-span-3"><CardContent className="p-4">
                            <h3 className="mb-2 text-sm font-medium">Safety exceptions</h3>
                            {(c.safety_exceptions ?? []).map((e: R) => (
                                <p key={e.id} className="text-xs">{e.stage}/{e.item_key}: {e.reason}</p>
                            ))}
                            {(c.safety_exceptions ?? []).length === 0 && <p className="text-xs text-muted-foreground">None recorded.</p>}
                        </CardContent></Card>
                    </div>
                )}

                {tab === 'Blood' && (
                    <Card><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">Blood product requirements <Badge variant="outline" className="ml-1">Phase 12 boundary</Badge></h3>
                        {(c.blood_requirements ?? []).map((r: R) => (
                            <div key={r.id} className="mb-1 flex items-center justify-between border-b pb-1 text-sm last:border-0">
                                <span>{r.product_type} × {r.units}</span>
                                <div className="flex items-center gap-1">
                                    <Badge variant="outline">{r.status}</Badge>
                                    {r.status === 'requested' && (
                                        <Button size="sm" variant="outline" onClick={() => post(`/admin/surgery/blood/${r.id}/confirm`, { status: 'confirmed' })}>Confirm</Button>
                                    )}
                                </div>
                            </div>
                        ))}
                        <Form method="post" action={`/admin/surgery/cases/${c.id}/blood`}>
                            {() => (
                                <div className="mt-3 flex items-end gap-2">
                                    <Input name="product_type" placeholder="Product (e.g. packed cells)" required className="h-8" />
                                    <Input name="units" type="number" min={1} defaultValue={1} required className="h-8 w-20" />
                                    <Button size="sm" type="submit">Request</Button>
                                </div>
                            )}
                        </Form>
                    </CardContent></Card>
                )}

                {tab === 'History' && (
                    <Card><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">Documents & operations</h3>
                        {(c.operations ?? []).map((o: R) => (
                            <div key={o.id} className="mb-2 flex items-center justify-between rounded border p-2 text-sm">
                                <span className="font-mono text-xs">{o.operation_number} — {o.status}</span>
                                <Button asChild size="sm" variant="outline"><Link href={`/admin/surgery/operations/${o.id}`}>Open workspace</Link></Button>
                            </div>
                        ))}
                        {c.recovery_record && (
                            <p className="text-sm">Recovery: {c.recovery_record.record_number} — {c.recovery_record.status.replaceAll('_', ' ')}</p>
                        )}
                        {c.handover && (
                            <p className="text-sm">Handover → {c.handover.destination_type}: <Badge variant="outline">{c.handover.status}</Badge></p>
                        )}
                    </CardContent></Card>
                )}
            </div>
        </>
    );
}
