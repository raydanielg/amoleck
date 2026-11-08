import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

const fields: [string, string][] = [
    ['name', 'Hospital name'],
    ['slogan', 'Slogan'],
    ['registration_number', 'Registration no.'],
    ['license_number', 'License no.'],
    ['email', 'Email'],
    ['phone', 'Phone'],
    ['secondary_phone', 'Alt. phone'],
    ['website', 'Website'],
    ['address', 'Address'],
    ['region', 'Region'],
    ['district', 'District'],
    ['postal_code', 'Postal code'],
    ['country', 'Country'],
    ['timezone', 'Timezone'],
    ['currency', 'Currency'],
    ['report_header', 'Report header'],
    ['report_footer', 'Report footer'],
];

export default function Hospital({
    profile,
}: {
    profile: Record<string, string>;
}) {
    return (
        <>
            <Head title="Hospital Profile" />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <Heading
                    title="Hospital Profile"
                    description="Organization details used in documents and reports"
                />
                <Card>
                    <CardContent className="p-6">
                        <Form
                            action="/admin/hospital"
                            method="post"
                            transform={(d) => ({ ...d, _method: 'put' })}
                            encType="multipart/form-data"
                        >
                            {({ processing, errors }) => (
                                <FieldGroup>
                                    {fields.map(([n, l]) => (
                                        <Field key={n}>
                                            <FieldLabel htmlFor={n}>
                                                {l}
                                            </FieldLabel>
                                            <Input
                                                id={n}
                                                name={n}
                                                defaultValue={
                                                    profile?.[n] ?? ''
                                                }
                                                required={n === 'name'}
                                            />
                                            <InputError message={errors[n]} />
                                        </Field>
                                    ))}
                                    <Field>
                                        <FieldLabel>Description</FieldLabel>
                                        <textarea
                                            name="description"
                                            defaultValue={
                                                profile?.description ?? ''
                                            }
                                            className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="logo">
                                            Logo
                                        </FieldLabel>
                                        <Input
                                            id="logo"
                                            name="logo"
                                            type="file"
                                            accept="image/*"
                                        />
                                        <InputError message={errors.logo} />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="stamp">
                                            Stamp
                                        </FieldLabel>
                                        <Input
                                            id="stamp"
                                            name="stamp"
                                            type="file"
                                            accept="image/*"
                                        />
                                        <InputError message={errors.stamp} />
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Save profile
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
