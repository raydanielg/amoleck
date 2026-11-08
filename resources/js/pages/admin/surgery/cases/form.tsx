import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
export default function CaseForm({
    patients,
    specialties,
    procedures,
    departments,
    staff,
    priorities,
    branches,
    branchId,
}: {
    patients: R[];
    specialties: R[];
    procedures: R[];
    departments: R[];
    staff: R[];
    priorities: string[];
    branches: R[];
    branchId: number | null;
}) {
    const sel = (n: string, l: string, opts: R[], show: (o: R) => string, req = true) => (
        <div className="min-w-[180px] flex-1">
            <label className="text-xs text-muted-foreground">{l}</label>
            <select name={n} required={req} className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                {!req && <option value="">—</option>}
                {opts.map((o) => (
                    <option key={o.id} value={o.id}>{show(o)}</option>
                ))}
            </select>
        </div>
    );
    return (
        <>
            <Head title="New Surgical Case" />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <Heading title="Register Surgical Case" description="Surgical decision & case registration" />
                <Card>
                    <CardContent className="p-5">
                        <Form action="/admin/surgery/cases" method="post">
                            {() => (
                                <div className="space-y-4">
                                    <div className="flex flex-wrap gap-3">
                                        {sel('patient_id', 'Patient', patients, (p) => `${p.first_name} ${p.last_name} (${p.mrn})`)}
                                        {sel('branch_id', 'Branch', branches, (b) => b.name)}
                                    </div>
                                    <div className="flex flex-wrap gap-3">
                                        {sel('surgical_specialty_id', 'Specialty', specialties, (s) => s.name, false)}
                                        {sel('surgical_procedure_id', 'Procedure', procedures, (p) => `${p.name} (${p.code})`, false)}
                                        {sel('department_id', 'Department', departments, (d) => d.name, false)}
                                    </div>
                                    <div className="flex flex-wrap gap-3">
                                        {sel('surgeon_id', 'Responsible surgeon', staff, (s) => `${s.name}${s.job_title ? ` — ${s.job_title}` : ''}`, false)}
                                        <div className="min-w-[160px] flex-1">
                                            <label className="text-xs text-muted-foreground">Priority</label>
                                            <select name="priority" required className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                                {priorities.map((p) => (
                                                    <option key={p} value={p}>{p}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="min-w-[160px] flex-1">
                                            <label className="text-xs text-muted-foreground">Proposed date</label>
                                            <Input type="date" name="proposed_date" className="mt-1 h-8" />
                                        </div>
                                    </div>
                                    <div>
                                        <label className="text-xs text-muted-foreground">Clinical indication *</label>
                                        <textarea name="indication" required rows={2} className="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-muted-foreground">Working diagnosis</label>
                                        <textarea name="diagnosis" rows={2} className="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-muted-foreground">Notes</label>
                                        <textarea name="notes" rows={2} className="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm" />
                                    </div>
                                    <Button type="submit">Register case</Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
