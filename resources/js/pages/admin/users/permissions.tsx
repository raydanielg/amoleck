import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';

type U = { id: number; name: string; email: string; roles: { name: string }[] };

export default function UserPermissions({
    user,
    rolePermissions,
    directPermissions,
    permissionGroups,
}: {
    user: U;
    rolePermissions: string[];
    directPermissions: string[];
    permissionGroups: Record<string, string[]>;
}) {
    const roleSet = new Set(rolePermissions);
    const directSet = new Set(directPermissions);
    return (
        <>
            <Head title={`Permissions — ${user.name}`} />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <Heading
                    title={`Permissions: ${user.name}`}
                    description={`Roles: ${user.roles.map((r) => r.name).join(', ') || 'none'}`}
                />
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">
                            Effective permissions
                        </h2>
                        <div className="flex flex-wrap gap-1.5">
                            {permissionGroups &&
                                Object.values(permissionGroups)
                                    .flat()
                                    .filter(
                                        (p) =>
                                            roleSet.has(p) || directSet.has(p),
                                    )
                                    .map((p) => (
                                        <Badge
                                            key={p}
                                            variant={
                                                directSet.has(p) &&
                                                !roleSet.has(p)
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {p}
                                            {directSet.has(p) && !roleSet.has(p)
                                                ? ' (direct)'
                                                : ''}
                                        </Badge>
                                    ))}
                            {rolePermissions.length === 0 &&
                                directPermissions.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No permissions.
                                    </p>
                                )}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-6">
                        <h2 className="mb-1 font-medium">
                            Direct permission overrides
                        </h2>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Additive grants only — checked items granted
                            directly. Branch restrictions and account status
                            still apply.
                        </p>
                        <Form
                            action={`/admin/users/${user.id}/permissions`}
                            method="post"
                            transform={(d) => ({ ...d, _method: 'put' })}
                        >
                            {({ processing }) => (
                                <FieldGroup>
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
                                                                defaultChecked={directSet.has(
                                                                    p,
                                                                )}
                                                                disabled={roleSet.has(
                                                                    p,
                                                                )}
                                                            />
                                                            {p}
                                                            {roleSet.has(p) && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    (via role)
                                                                </span>
                                                            )}
                                                        </label>
                                                    ))}
                                                </div>
                                            </Field>
                                        ),
                                    )}
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Save direct
                                        permissions
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
