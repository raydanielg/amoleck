import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type U = {
    id: number;
    name: string;
    email: string;
    employee_number?: string;
    status: string;
    job_title?: string;
    roles: { name: string }[];
    branches: { name: string }[];
};

export default function Users({
    users,
    filters,
}: {
    users: { data: U[] };
    filters: { search?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const setStatus = (id: number, status: string) =>
        router.post(`/admin/users/${id}/status`, { status });
    return (
        <>
            <Head title="Staff & Users" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Staff & Users"
                        description="Manage staff accounts"
                    />
                    <Button asChild>
                        <Link href="/admin/users/create">
                            New staff account
                        </Link>
                    </Button>
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/admin/users', { search });
                    }}
                >
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search staff..."
                        className="max-w-sm"
                    />
                </form>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Email</th>
                                    <th className="p-3">Roles</th>
                                    <th className="p-3">Branches</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.map((u) => (
                                    <tr
                                        key={u.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-medium">
                                            {u.name}
                                            <div className="text-xs text-muted-foreground">
                                                {u.job_title}
                                                {u.employee_number
                                                    ? ` · ${u.employee_number}`
                                                    : ''}
                                            </div>
                                        </td>
                                        <td className="p-3">{u.email}</td>
                                        <td className="p-3">
                                            {u.roles.map((r) => (
                                                <Badge
                                                    key={r.name}
                                                    variant="secondary"
                                                    className="mr-1"
                                                >
                                                    {r.name}
                                                </Badge>
                                            ))}
                                        </td>
                                        <td className="p-3 text-xs">
                                            {u.branches
                                                .map((b) => b.name)
                                                .join(', ')}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    u.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {u.status}
                                            </Badge>
                                        </td>
                                        <td className="flex gap-2 p-3">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`/admin/users/${u.id}/edit`}
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setStatus(
                                                        u.id,
                                                        u.status === 'active'
                                                            ? 'suspended'
                                                            : 'active',
                                                    )
                                                }
                                            >
                                                {u.status === 'active'
                                                    ? 'Suspend'
                                                    : 'Activate'}
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {users.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No staff found.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
