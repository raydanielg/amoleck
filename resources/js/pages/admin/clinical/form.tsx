import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

export default function EncounterForm({
    patient,
    branches,
}: {
    patient: { id: number; mrn: string; first_name: string; last_name: string };
    branches: { id: number; name: string }[];
}) {
    return (
        <>
            <Head title="New clinical encounter" />
            <div className="mx-auto max-w-xl space-y-6 p-6">
                <Heading
                    title="New clinical encounter"
                    description={`${patient.first_name} ${patient.last_name} · ${patient.mrn}`}
                />
                <Card>
                    <CardContent className="p-6">
                        <Form
                            action={`/admin/patients/${patient.id}/clinical`}
                            method="post"
                        >
                            {({ processing }) => (
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel>Type</FieldLabel>
                                        <select
                                            name="type"
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {[
                                                'initial_assessment',
                                                'follow_up',
                                                'reassessment',
                                                'review',
                                            ].map((t) => (
                                                <option key={t} value={t}>
                                                    {t.replace('_', ' ')}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field>
                                        <FieldLabel>Date & time</FieldLabel>
                                        <Input
                                            name="encounter_at"
                                            type="datetime-local"
                                            required
                                        />
                                    </Field>
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
                                        <FieldLabel>Physiotherapist</FieldLabel>
                                        <Input
                                            name="physiotherapist_id"
                                            type="number"
                                            placeholder="User ID (self if blank)"
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel>
                                            Referring professional
                                        </FieldLabel>
                                        <Input name="referring_professional" />
                                    </Field>
                                    <Field>
                                        <FieldLabel>Notes</FieldLabel>
                                        <textarea
                                            name="notes"
                                            className="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                        />
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Create draft
                                        encounter
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
