import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

const FIELDS: [string, string][] = [
    ['referrer_name', 'Referrer name'],
    ['referrer_facility', 'Facility'],
    ['referrer_designation', 'Designation'],
    ['referrer_contact', 'Contact'],
    ['referrer_address', 'Facility address'],
    ['requested_service', 'Requested service'],
];

export default function ReferralForm({
    patient,
    branches,
}: {
    patient: { id: number; mrn: string; first_name: string; last_name: string };
    branches: { id: number; name: string }[];
}) {
    return (
        <>
            <Head title="New referral" />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <Heading
                    title={`Referral for ${patient.first_name} ${patient.last_name}`}
                    description={patient.mrn}
                />
                <Card>
                    <CardContent className="p-6">
                        <Form
                            action={`/admin/patients/${patient.id}/referrals`}
                            method="post"
                        >
                            {({ processing, errors }) => (
                                <FieldGroup>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field>
                                            <FieldLabel>Type</FieldLabel>
                                            <select
                                                name="type"
                                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                            >
                                                <option value="external">
                                                    External
                                                </option>
                                                <option value="internal">
                                                    Internal
                                                </option>
                                            </select>
                                        </Field>
                                        <Field>
                                            <FieldLabel>
                                                Referral date
                                            </FieldLabel>
                                            <Input
                                                name="referral_date"
                                                type="date"
                                                required
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel>
                                                Receiving branch
                                            </FieldLabel>
                                            <select
                                                name="receiving_branch_id"
                                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                            >
                                                <option value="">—</option>
                                                {branches.map((b) => (
                                                    <option
                                                        key={b.id}
                                                        value={b.id}
                                                    >
                                                        {b.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                        {FIELDS.map(([n, l]) => (
                                            <Field key={n}>
                                                <FieldLabel>{l}</FieldLabel>
                                                <Input name={n} />
                                                <InputError
                                                    message={errors[n]}
                                                />
                                            </Field>
                                        ))}
                                    </div>
                                    {[
                                        ['reason', 'Reason *'],
                                        [
                                            'presenting_complaint',
                                            'Presenting complaint',
                                        ],
                                        [
                                            'relevant_history',
                                            'Relevant history',
                                        ],
                                        [
                                            'preliminary_diagnosis',
                                            'Preliminary diagnosis',
                                        ],
                                        ['notes', 'Notes'],
                                    ].map(([n, l]) => (
                                        <Field key={n}>
                                            <FieldLabel>{l}</FieldLabel>
                                            <textarea
                                                name={n}
                                                className="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                            />
                                            <InputError message={errors[n]} />
                                        </Field>
                                    ))}
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Create
                                        referral
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
