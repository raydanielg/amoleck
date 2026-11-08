import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

const SECTIONS: [string, [string, string, string?][]][] = [
    [
        'Personal information',
        [
            ['first_name', 'First name', 'text'],
            ['middle_name', 'Middle name'],
            ['last_name', 'Last name', 'text'],
            ['gender', 'Gender (male/female/other)'],
            ['date_of_birth', 'Date of birth', 'date'],
            ['nationality', 'Nationality'],
            ['marital_status', 'Marital status'],
            ['photo', 'Photo URL'],
        ],
    ],
    [
        'Identification',
        [
            [
                'identification_type',
                'ID type (national_id/passport/voter_id/driving_licence/other)',
            ],
            ['identification_number', 'ID number'],
            ['identification_country', 'Issuing country'],
            ['identification_expires_at', 'Expiry date', 'date'],
        ],
    ],
    [
        'Contact',
        [
            ['phone', 'Primary phone'],
            ['alt_phone', 'Alt. phone'],
            ['email', 'Email'],
            ['region', 'Region'],
            ['district', 'District'],
            ['ward', 'Ward'],
            ['address', 'Address'],
        ],
    ],
    [
        'Emergency contact',
        [
            ['emergency_name', 'Name'],
            ['emergency_relationship', 'Relationship'],
            ['emergency_phone', 'Phone'],
            ['emergency_alt_phone', 'Alt. phone'],
        ],
    ],
    [
        'Next of kin / guardian',
        [
            ['guardian_name', 'Name'],
            ['guardian_relationship', 'Relationship'],
            ['guardian_phone', 'Phone'],
            ['guardian_email', 'Email'],
            ['guardian_address', 'Address'],
        ],
    ],
    [
        'Administrative',
        [
            ['registration_source', 'Registration source'],
            ['referred_by', 'Referred by'],
            ['admin_notes', 'Notes'],
        ],
    ],
];

export default function PatientForm({
    patient,
    branches,
    duplicates,
    old,
    duplicateWarning,
}: {
    patient: Record<string, string> | null;
    branches: { id: number; name: string }[];
    duplicates: {
        id: number;
        mrn: string;
        name: string;
        matched_on: string[];
    }[];
    old?: Record<string, string>;
    duplicateWarning?: boolean;
}) {
    const [confirmed, setConfirmed] = useState(false);
    const val = (name: string) => old?.[name] ?? patient?.[name] ?? '';
    return (
        <>
            <Head title={patient ? 'Edit patient' : 'Register patient'} />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <Heading
                    title={
                        patient
                            ? `Edit ${patient.mrn ?? 'patient'}`
                            : 'Register new patient'
                    }
                />
                {duplicateWarning && duplicates.length > 0 && (
                    <Card className="border-destructive">
                        <CardContent className="p-5">
                            <h2 className="mb-2 font-medium text-destructive">
                                Potential duplicates found
                            </h2>
                            <ul className="mb-4 space-y-1 text-sm">
                                {duplicates.map((d) => (
                                    <li key={d.id}>
                                        <strong>{d.name}</strong>{' '}
                                        <span className="font-mono text-xs">
                                            {d.mrn}
                                        </span>{' '}
                                        <Badge variant="secondary">
                                            {d.matched_on.join(', ')}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={confirmed}
                                    onChange={(e) =>
                                        setConfirmed(e.target.checked)
                                    }
                                />
                                I've reviewed these — this is a different
                                person. Continue registration.
                            </label>
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardContent className="p-6">
                        <Form
                            action={
                                patient
                                    ? `/admin/patients/${patient.id}`
                                    : '/admin/patients'
                            }
                            method="post"
                            transform={(d) => ({
                                ...d,
                                ...(patient ? { _method: 'put' } : {}),
                                confirm_duplicates: confirmed ? 1 : 0,
                            })}
                        >
                            {({ processing, errors }) => (
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel>Primary branch</FieldLabel>
                                        <select
                                            name="primary_branch_id"
                                            defaultValue={val(
                                                'primary_branch_id',
                                            )}
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            <option value="">
                                                Current branch
                                            </option>
                                            {branches.map((b) => (
                                                <option key={b.id} value={b.id}>
                                                    {b.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    {SECTIONS.map(([section, fields]) => (
                                        <div key={section}>
                                            <h3 className="mb-3 border-b pb-2 font-medium">
                                                {section}
                                            </h3>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                {fields.map(
                                                    ([name, label, type]) => (
                                                        <Field key={name}>
                                                            <FieldLabel
                                                                htmlFor={name}
                                                            >
                                                                {label}
                                                            </FieldLabel>
                                                            <Input
                                                                id={name}
                                                                name={name}
                                                                type={
                                                                    type ??
                                                                    'text'
                                                                }
                                                                defaultValue={val(
                                                                    name,
                                                                )}
                                                                required={
                                                                    name ===
                                                                        'first_name' ||
                                                                    name ===
                                                                        'last_name'
                                                                }
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors[name]
                                                                }
                                                            />
                                                        </Field>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            (duplicateWarning && !confirmed)
                                        }
                                    >
                                        {processing && <Spinner />}{' '}
                                        {patient
                                            ? 'Save changes'
                                            : 'Register patient'}
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
