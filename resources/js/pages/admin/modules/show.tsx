import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';

type M = {
    id: number;
    name: string;
    description?: string;
    version: string;
    category: string;
    status: string;
    protected: boolean;
    dependencies: string[];
    features: string[];
    role_ids: number[];
    updated_at?: string;
};
type Dep = { name: string; status: string };
type A = { id: number; action: string; created_at: string };

export default function ModuleShow({
    module,
    roles,
    dependencies,
    activity,
}: {
    module: M;
    roles: { id: number; name: string }[];
    dependencies: Dep[];
    activity: A[];
}) {
    const bound = new Set(module.role_ids);
    return (
        <>
            <Head title={module.name} />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={module.name}
                        description={`${module.description ?? ''} · v${module.version} · ${module.category}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={
                                module.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {module.status}
                        </Badge>
                        {!module.protected &&
                            module.status !== 'unavailable' && (
                                <>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/admin/modules/${module.name}/toggle`,
                                            )
                                        }
                                    >
                                        {module.status === 'active'
                                            ? 'Disable'
                                            : 'Activate'}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/admin/modules/${module.id}/maintenance`,
                                            )
                                        }
                                    >
                                        {module.status === 'maintenance'
                                            ? 'End maintenance'
                                            : 'Maintenance'}
                                    </Button>
                                </>
                            )}
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">Dependencies</h2>
                            {dependencies.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    None.
                                </p>
                            )}
                            <ul className="space-y-1 text-sm">
                                {dependencies.map((d) => (
                                    <li
                                        key={d.name}
                                        className="flex justify-between"
                                    >
                                        <span>{d.name}</span>
                                        <Badge
                                            variant={
                                                d.status === 'active'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {d.status}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">
                                Registered features
                            </h2>
                            {module.features.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    None registered.
                                </p>
                            )}
                            <div className="flex flex-wrap gap-1.5">
                                {module.features.map((f) => (
                                    <Badge key={f} variant="secondary">
                                        {f.replace('_', ' ')}
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardContent className="p-6">
                        <h2 className="mb-1 font-medium">Role access</h2>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Restrict module to specific roles. Empty = all
                            authorized users. Super Admin always retains access.
                        </p>
                        <Form
                            action={`/admin/modules/${module.id}/access`}
                            method="post"
                            transform={(d) => ({ ...d, _method: 'put' })}
                        >
                            {({ processing }) => (
                                <FieldGroup>
                                    <Field>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {roles.map((r) => (
                                                <label
                                                    key={r.id}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        name="role_ids[]"
                                                        value={r.id}
                                                        defaultChecked={bound.has(
                                                            r.id,
                                                        )}
                                                    />
                                                    {r.name}
                                                </label>
                                            ))}
                                        </div>
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Save access
                                    </Button>
                                </FieldGroup>
                            )}
                        </Form>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">Activation history</h2>
                        {activity.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No recorded changes.
                            </p>
                        )}
                        <ul className="space-y-1 text-sm">
                            {activity.map((a) => (
                                <li key={a.id} className="flex justify-between">
                                    <span>{a.action}</span>
                                    <span className="text-muted-foreground">
                                        {new Date(
                                            a.created_at,
                                        ).toLocaleString()}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
