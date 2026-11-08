import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

export default function SessionForm({
    patient,
    plan,
    branches,
}: {
    patient: { id: number; mrn: string; first_name: string; last_name: string };
    plan: { id: number; plan_number: string };
    branches: { id: number; name: string }[];
}) {
    return (
        <>
            <Head title="New session" />
            <div className="mx-auto max-w-xl space-y-6 p-6">
                <Heading
                    title="Start treatment session"
                    description={`${patient.first_name} ${patient.last_name} · ${patient.mrn} · Plan ${plan.plan_number}`}
                />
                <Card>
                    <CardContent className="p-6">
                        <Form
                            action={`/admin/patients/${patient.id}/sessions`}
                            method="post"
                        >
                            {({ processing }) => (
                                <FieldGroup>
                                    <input
                                        type="hidden"
                                        name="clinical_treatment_plan_id"
                                        value={plan.id}
                                    />
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
                                        <FieldLabel>Type</FieldLabel>
                                        <select
                                            name="type"
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {[
                                                'initial',
                                                'routine',
                                                'follow_up',
                                                'reassessment',
                                                'post_op',
                                                'discharge',
                                            ].map((t) => (
                                                <option key={t} value={t}>
                                                    {t.replace('_', ' ')}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field>
                                        <FieldLabel>
                                            Session date & time
                                        </FieldLabel>
                                        <Input
                                            name="session_at"
                                            type="datetime-local"
                                            required
                                        />
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Create
                                        session
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
