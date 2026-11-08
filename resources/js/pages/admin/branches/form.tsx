import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

export default function BranchForm({
    branch,
}: {
    branch: Record<string, string> | null;
}) {
    const action = branch ? `/admin/branches/${branch.id}` : '/admin/branches';
    const fields: [string, string, boolean?][] = [
        ['name', 'Name', true],
        ['code', 'Code', true],
        ['address', 'Address'],
        ['region', 'Region'],
        ['district', 'District'],
        ['phone', 'Phone'],
        ['email', 'Email'],
        ['manager_name', 'Manager name'],
        ['opening_date', 'Opening date'],
    ];
    return (
        <>
            <Head title={branch ? 'Edit branch' : 'New branch'} />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <Heading title={branch ? 'Edit branch' : 'New branch'} />
                <Card>
                    <CardContent className="p-6">
                        <Form
                            action={action}
                            method="post"
                            transform={(data) =>
                                branch ? { ...data, _method: 'put' } : data
                            }
                        >
                            {({ processing, errors }) => (
                                <FieldGroup>
                                    {fields.map(([name, label, required]) => (
                                        <Field key={name}>
                                            <FieldLabel htmlFor={name}>
                                                {label}
                                            </FieldLabel>
                                            <Input
                                                id={name}
                                                name={name}
                                                type={
                                                    name === 'opening_date'
                                                        ? 'date'
                                                        : name === 'email'
                                                          ? 'email'
                                                          : 'text'
                                                }
                                                defaultValue={
                                                    branch?.[name] ?? ''
                                                }
                                                required={required}
                                            />
                                            <InputError
                                                message={errors[name]}
                                            />
                                        </Field>
                                    ))}
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Save
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
