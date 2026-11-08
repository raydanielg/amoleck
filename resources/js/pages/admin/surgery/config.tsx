import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
export default function SurgeryConfig({
    specialties, procedures, templates, departments, branches, categories, urgencies, techniques,
}: {
    specialties: R[]; procedures: R[]; templates: R[]; departments: R[];
    branches: R[]; categories: string[]; urgencies: string[]; techniques: string[]; theatres: R[]; branchId: number | null;
}) {
    const [tplItems, setTplItems] = useState([{ key: '', label: '', mandatory: true, role: '' }]);
    return (
        <>
            <Head title="Surgery Configuration" />
            <div className="space-y-6 p-6">
                <Heading title="Surgical Configuration" description="Specialties, procedure catalogue & checklist templates" />
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card><CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-medium">Surgical specialties</h3>
                        {specialties.map((s) => (
                            <div key={s.id} className="mb-1 flex items-center justify-between border-b pb-1 text-sm last:border-0">
                                <span>{s.name} <span className="font-mono text-xs text-muted-foreground">{s.code}</span></span>
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span>{s.department?.name}</span>
                                    <Badge variant={s.status === 'active' ? 'default' : 'secondary'}>{s.status}</Badge>
                                </div>
                            </div>
                        ))}
                        <Form method="post" action="/admin/surgery/config/specialties">
                            {() => (
                                <div className="mt-3 flex flex-wrap items-end gap-2">
                                    <Input name="code" placeholder="Code" required className="h-8 w-24" />
                                    <Input name="name" placeholder="Name" required className="h-8 w-48" />
                                    <select name="department_id" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                        <option value="">— dept —</option>
                                        {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                                    </select>
                                    <Button size="sm" type="submit">Add specialty</Button>
                                </div>
                            )}
                        </Form>
                    </CardContent></Card>
                    <Card><CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-medium">Procedure catalogue</h3>
                        <div className="max-h-64 space-y-1 overflow-y-auto">
                            {procedures.map((p) => (
                                <div key={p.id} className="flex items-center justify-between border-b pb-1 text-sm last:border-0">
                                    <span>{p.name} <span className="font-mono text-xs text-muted-foreground">{p.code} · v{p.version}</span></span>
                                    <div className="flex items-center gap-1">
                                        <Badge variant={p.status === 'active' ? 'default' : 'secondary'}>{p.status}</Badge>
                                        {p.status !== 'active' && (
                                            <Button size="sm" variant="ghost" className="h-6 px-1 text-xs" onClick={() => router.post(`/admin/surgery/config/procedures/${p.id}/transition`, { status: 'active' })}>activate</Button>
                                        )}
                                        {p.status === 'active' && (
                                            <Button size="sm" variant="ghost" className="h-6 px-1 text-xs" onClick={() => router.post(`/admin/surgery/config/procedures/${p.id}/transition`, { status: 'inactive' })}>deactivate</Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <Form method="post" action="/admin/surgery/config/procedures" transformData={(d) => ({ ...d, consent_required: d.consent_required ? true : false, anesthesia_options: techniques.filter((t) => d[`tech_${t}`]) })}>
                            {() => (
                                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                    <Input name="code" placeholder="Code" required className="h-8" />
                                    <Input name="name" placeholder="Procedure name" required className="h-8" />
                                    <select name="surgical_specialty_id" required className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                        {specialties.filter((s) => s.status === 'active').map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                    </select>
                                    <select name="category" required className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                        {categories.map((cat) => <option key={cat} value={cat}>{cat}</option>)}
                                    </select>
                                    <select name="urgency_classification" required className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                        {urgencies.map((u) => <option key={u} value={u}>{u}</option>)}
                                    </select>
                                    <Input name="expected_duration_minutes" type="number" placeholder="Duration (min)" defaultValue={60} className="h-8" />
                                    <div className="flex flex-wrap items-center gap-2 text-xs sm:col-span-2">
                                        {techniques.map((t) => (
                                            <label key={t} className="flex items-center gap-1"><input type="checkbox" name={`tech_${t}`} />{t}</label>
                                        ))}
                                        <label className="ml-2 flex items-center gap-1"><input type="checkbox" name="consent_required" defaultChecked />consent required</label>
                                    </div>
                                    <Button size="sm" type="submit" className="sm:col-span-2">Add procedure</Button>
                                </div>
                            )}
                        </Form>
                    </CardContent></Card>
                    <Card className="lg:col-span-2"><CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-medium">Checklist templates (hospital-approved)</h3>
                        {templates.map((t) => (
                            <div key={t.id} className="mb-1 flex items-center justify-between border-b pb-1 text-sm last:border-0">
                                <span>{t.name} <Badge variant="outline" className="ml-1">{t.stage}</Badge> <span className="text-xs text-muted-foreground">v{t.version} · {t.items?.length ?? 0} items</span></span>
                                <Badge variant={t.active ? 'default' : 'secondary'}>{t.active ? 'active' : 'inactive'}</Badge>
                            </div>
                        ))}
                        <Form method="post" action="/admin/surgery/config/templates" transformData={(d) => ({ code: d.code, name: d.name, stage: d.stage, items: tplItems.filter((i) => i.key && i.label) })}>
                            {() => (
                                <div className="mt-3 space-y-2">
                                    <div className="flex flex-wrap gap-2">
                                        <Input name="code" placeholder="Code" required className="h-8 w-32" />
                                        <Input name="name" placeholder="Template name" required className="h-8 w-56" />
                                        <select name="stage" required className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                            {['preparation', 'sign_in', 'time_out', 'sign_out'].map((s) => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                    </div>
                                    {tplItems.map((it, ix) => (
                                        <div key={ix} className="flex gap-2">
                                            <Input placeholder="key" value={it.key} onChange={(e) => setTplItems(tplItems.map((x, i) => i === ix ? { ...x, key: e.target.value } : x))} className="h-8 w-40" />
                                            <Input placeholder="Label" value={it.label} onChange={(e) => setTplItems(tplItems.map((x, i) => i === ix ? { ...x, label: e.target.value } : x))} className="h-8 flex-1" />
                                            <label className="flex items-center gap-1 text-xs"><input type="checkbox" checked={it.mandatory} onChange={(e) => setTplItems(tplItems.map((x, i) => i === ix ? { ...x, mandatory: e.target.checked } : x))} />mandatory</label>
                                            <Input placeholder="role" value={it.role} onChange={(e) => setTplItems(tplItems.map((x, i) => i === ix ? { ...x, role: e.target.value } : x))} className="h-8 w-28" />
                                        </div>
                                    ))}
                                    <div className="flex gap-2">
                                        <Button size="sm" variant="outline" type="button" onClick={() => setTplItems([...tplItems, { key: '', label: '', mandatory: true, role: '' }])}>+ item</Button>
                                        <Button size="sm" type="submit">Save template (new version)</Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </CardContent></Card>
                </div>
            </div>
        </>
    );
}
