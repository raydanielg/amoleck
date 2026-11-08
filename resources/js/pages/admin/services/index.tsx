import { Form, Head, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
export default function Services({
    services,
    categories,
    departments,
    workflows,
}: {
    services: { data: R[] };
    categories: R[];
    departments: R[];
    branches: R[];
    workflows: string[];
}) {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const can = (p: string) => (auth?.permissions ?? []).includes(p);
    return (
        <>
            <Head title="Service Catalogue" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Service Catalogue"
                    description="Configurable clinical services"
                />
                {can('services.create') && (
                    <Card>
                        <CardContent className="p-5">
                            <Form action="/admin/services" method="post">
                                {() => (
                                    <div className="grid gap-3 sm:grid-cols-4">
                                        <Input
                                            name="code"
                                            placeholder="Code"
                                            required
                                        />
                                        <Input
                                            name="name"
                                            placeholder="Name"
                                            required
                                        />
                                        <select
                                            name="service_category_id"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            <option value="">Category</option>
                                            {categories.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.name}
                                                </option>
                                            ))}
                                        </select>
                                        <select
                                            name="department_id"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            <option value="">Department</option>
                                            {departments.map((d) => (
                                                <option key={d.id} value={d.id}>
                                                    {d.name}
                                                </option>
                                            ))}
                                        </select>
                                        <select
                                            name="workflow_mapping"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            <option value="">
                                                No EMR workflow
                                            </option>
                                            {workflows.map((w) => (
                                                <option key={w} value={w}>
                                                    {w.replace(/_/g, ' ')}
                                                </option>
                                            ))}
                                        </select>
                                        <Input
                                            name="default_duration"
                                            type="number"
                                            placeholder="Mins"
                                            defaultValue="30"
                                        />
                                        <Input
                                            name="clinician_category"
                                            placeholder="Clinician type"
                                        />
                                        <Button type="submit">Add</Button>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Code</th>
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Dept</th>
                                    <th className="p-3">Category</th>
                                    <th className="p-3">Duration</th>
                                    <th className="p-3">EMR</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {services.data.map((s) => (
                                    <tr
                                        key={s.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {s.code}
                                        </td>
                                        <td className="p-3 font-medium">
                                            {s.name}
                                        </td>
                                        <td className="p-3">
                                            {s.department?.name ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {s.category?.name ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {s.default_duration}m
                                        </td>
                                        <td className="p-3 text-xs">
                                            {s.workflow_mapping?.replace(
                                                /_/g,
                                                ' ',
                                            ) ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    s.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {s.status}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-right">
                                            {can('services.activate') &&
                                                s.status !== 'archived' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `/admin/services/${s.id}/transition`,
                                                                {
                                                                    status:
                                                                        s.status ===
                                                                        'active'
                                                                            ? 'inactive'
                                                                            : 'active',
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {s.status === 'active'
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </Button>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
