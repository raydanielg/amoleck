import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
export default function Departments({
    departments,
    allDepartments,
    staff,
}: {
    departments: { data: R[] };
    allDepartments: R[];
    branches: R[];
    staff: R[];
}) {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const canManage = (auth?.permissions ?? []).includes('departments.manage');
    return (
        <>
            <Head title="Departments" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Departments"
                    description="Hospital organizational units"
                />
                {canManage && (
                    <Card>
                        <CardContent className="p-5">
                            <Form action="/admin/departments" method="post">
                                {() => (
                                    <div className="grid gap-3 sm:grid-cols-5">
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
                                            name="parent_id"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            <option value="">No parent</option>
                                            {allDepartments.map((d) => (
                                                <option key={d.id} value={d.id}>
                                                    {d.name}
                                                </option>
                                            ))}
                                        </select>
                                        <select
                                            name="head_id"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            <option value="">No head</option>
                                            {staff.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.name}
                                                </option>
                                            ))}
                                        </select>
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
                                    <th className="p-3">Parent</th>
                                    <th className="p-3">Head</th>
                                    <th className="p-3">Sub-depts</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {departments.data.map((d) => (
                                    <tr
                                        key={d.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {d.code}
                                        </td>
                                        <td className="p-3 font-medium">
                                            {d.name}
                                        </td>
                                        <td className="p-3">
                                            {d.parent?.name ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {d.head?.name ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            {d.children_count}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    d.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {d.status}
                                            </Badge>
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
