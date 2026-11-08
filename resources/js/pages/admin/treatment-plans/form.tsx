import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

export default function PlanForm({
    patient,
    branches,
    encounters,
}: {
    patient: { id: number; mrn: string; first_name: string; last_name: string };
    branches: { id: number; name: string }[];
    encounters: { id: number; encounter_number: string; type: string }[];
}) {
    return (
        <>
            <Head title="New treatment plan" />
            <div className="mx-auto max-w-xl space-y-6 p-6">
                <Heading
                    title="New treatment plan"
                    description={`${patient.first_name} ${patient.last_name} · ${patient.mrn}`}
                />
                <Card>
                    <CardContent className="p-6">
                        <Form
                            action={`/admin/patients/${patient.id}/treatment-plans`}
                            method="post"
                        >
                            {({ processing, errors }) => (
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel>Branch</FieldLabel>
                                        <select
                                            name="branch_id"
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {branches.map((b) => (
                                                <option key={b.id} value={b.id}>
                                                    {b.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field>
                                        <FieldLabel>
                                            Linked assessment
                                        </FieldLabel>
                                        <select
                                            name="clinical_encounter_id"
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            <option value="">—</option>
                                            {encounters.map((e) => (
                                                <option key={e.id} value={e.id}>
                                                    {e.encounter_number} (
                                                    {e.type.replace('_', ' ')})
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field>
                                        <FieldLabel>
                                            Clinical indication *
                                        </FieldLabel>
                                        <textarea
                                            name="clinical_indication"
                                            required
                                            className="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                        />
                                        <InputError
                                            message={errors.clinical_indication}
                                        />
                                    </Field>
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <Field>
                                            <FieldLabel>
                                                Sessions/week
                                            </FieldLabel>
                                            <Input
                                                name="sessions_per_week"
                                                type="number"
                                                min="1"
                                                max="14"
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel>
                                                Expected sessions
                                            </FieldLabel>
                                            <Input
                                                name="expected_sessions"
                                                type="number"
                                                min="1"
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel>Review date</FieldLabel>
                                            <Input
                                                name="review_at"
                                                type="date"
                                            />
                                        </Field>
                                    </div>
                                    <Field>
                                        <FieldLabel>
                                            Expected outcomes
                                        </FieldLabel>
                                        <textarea
                                            name="expected_outcomes"
                                            className="min-h-12 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel>Precautions</FieldLabel>
                                        <textarea
                                            name="precautions"
                                            className="min-h-12 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                        />
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Create draft
                                        plan
                                    </Button>
                                </FieldGroup>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
