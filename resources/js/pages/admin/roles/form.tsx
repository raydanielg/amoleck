import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type R = { id: number; name: string; permissions: { name: string }[] };

export default function RoleForm({
    role,
    permissionGroups,
    protected: isProtected,
}: {
    role: R | null;
    permissionGroups: Record<string, string[]>;
    protected?: boolean;
}) {
    const has = (p: string) => role?.permissions.some((x) => x.name === p);
    return (
        <>
            <Head title={role ? 'Edit role' : 'New role'} />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <Heading
                    title={role ? `Edit: ${role.name}` : 'New role'}
                    description={
                        isProtected
                            ? 'This is a protected system role.'
                            : undefined
                    }
                />
                <Card>
                    <CardContent className="p-6">
                        <Form
                            action={
                                role
                                    ? `/admin/roles/${role.id}`
                                    : '/admin/roles'
                            }
                            method="post"
                            transform={(d) =>
                                role ? { ...d, _method: 'put' } : d
                            }
                        >
                            {({ processing, errors }) => (
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="name">
                                            Role name
                                        </FieldLabel>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={role?.name ?? ''}
                                            required
                                            disabled={isProtected}
                                        />
                                        <InputError message={errors.name} />
                                    </Field>
                                    {Object.entries(permissionGroups).map(
                                        ([group, perms]) => (
                                            <Field key={group}>
                                                <FieldLabel>{group}</FieldLabel>
                                                <div className="grid gap-2 sm:grid-cols-2">
                                                    {perms.map((p) => (
                                                        <label
                                                            key={p}
                                                            className="flex items-center gap-2 text-sm"
                                                        >
                                                            <Checkbox
                                                                name="permissions[]"
                                                                value={p}
                                                                defaultChecked={has(
                                                                    p,
                                                                )}
                                                            />
                                                            {p}
                                                        </label>
                                                    ))}
                                                </div>
                                            </Field>
                                        ),
                                    )}
                                    <InputError message={errors.permissions} />
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
