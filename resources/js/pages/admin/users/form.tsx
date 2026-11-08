import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type U = {
    id: number;
    name: string;
    email: string;
    employee_number?: string;
    phone?: string;
    job_title?: string;
    department?: string;
    primary_branch_id?: number;
    roles: { id: number; name: string }[];
    branches: { id: number }[];
};

export default function UserForm({
    user,
    roles,
    branches,
}: {
    user: U | null;
    roles: { id: number; name: string }[];
    branches: { id: number; name: string; code: string }[];
}) {
    const fields: [string, string][] = [
        ['name', 'Full name'],
        ['employee_number', 'Employee no.'],
        ['email', 'Email'],
        ['phone', 'Phone'],
        ['job_title', 'Job title'],
        ['department', 'Department'],
        ['password', user ? 'New password (leave blank)' : 'Password'],
    ];
    const hasRole = (name: string) => user?.roles.some((r) => r.name === name);
    const hasBranch = (id: number) => user?.branches.some((b) => b.id === id);
    return (
        <>
            <Head title={user ? 'Edit staff' : 'New staff'} />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <Heading
                    title={user ? 'Edit staff account' : 'New staff account'}
                />
                <Card>
                    <CardContent className="p-6">
                        <Form
                            action={
                                user
                                    ? `/admin/users/${user.id}`
                                    : '/admin/users'
                            }
                            method="post"
                            transform={(d) =>
                                user ? { ...d, _method: 'put' } : d
                            }
                        >
                            {({ processing, errors }) => (
                                <FieldGroup>
                                    {fields.map(([name, label]) => (
                                        <Field key={name}>
                                            <FieldLabel htmlFor={name}>
                                                {label}
                                            </FieldLabel>
                                            <Input
                                                id={name}
                                                name={name}
                                                type={
                                                    name === 'email'
                                                        ? 'email'
                                                        : name === 'password'
                                                          ? 'password'
                                                          : 'text'
                                                }
                                                defaultValue={
                                                    name === 'password'
                                                        ? ''
                                                        : ((user?.[
                                                              name as keyof U
                                                          ] as string) ?? '')
                                                }
                                                required={
                                                    !user && name === 'password'
                                                }
                                            />
                                            <InputError
                                                message={errors[name]}
                                            />
                                        </Field>
                                    ))}
                                    <Field>
                                        <FieldLabel>Roles</FieldLabel>
                                        <div className="flex flex-wrap gap-3">
                                            {roles.map((r) => (
                                                <label
                                                    key={r.id}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        name="roles[]"
                                                        value={r.name}
                                                        defaultChecked={hasRole(
                                                            r.name,
                                                        )}
                                                    />
                                                    {r.name}
                                                </label>
                                            ))}
                                        </div>
                                        <InputError message={errors.roles} />
                                    </Field>
                                    <Field>
                                        <FieldLabel>Branches</FieldLabel>
                                        <div className="flex flex-wrap gap-3">
                                            {branches.map((b) => (
                                                <label
                                                    key={b.id}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        name="branch_ids[]"
                                                        value={b.id}
                                                        defaultChecked={hasBranch(
                                                            b.id,
                                                        )}
                                                    />
                                                    {b.name}
                                                </label>
                                            ))}
                                        </div>
                                        <InputError
                                            message={errors.branch_ids}
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="primary_branch_id">
                                            Primary branch
                                        </FieldLabel>
                                        <Input
                                            id="primary_branch_id"
                                            name="primary_branch_id"
                                            type="number"
                                            defaultValue={
                                                user?.primary_branch_id ?? ''
                                            }
                                            placeholder="Branch ID"
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="profile_photo">
                                            Profile photo
                                        </FieldLabel>
                                        <Input
                                            id="profile_photo"
                                            name="profile_photo"
                                            type="file"
                                            accept="image/*"
                                        />
                                        <InputError
                                            message={errors.profile_photo}
                                        />
                                    </Field>
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
