import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useState } from 'react';

type Branch = {
    id: number;
    name: string;
    code: string;
    status: string;
    phone?: string;
    email?: string;
    region?: string;
    users_count: number;
};
type Page = { data: Branch[] };

export default function Branches({
    branches,
    filters,
}: {
    branches: Page;
    filters: { search?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const setStatus = (id: number, status: string) =>
        router.post(`/admin/branches/${id}/status`, { status });
    return (
        <>
            <Head title="Branches" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Branches"
                        description="Manage hospital branches"
                    />
                    <Button asChild>
                        <Link href="/admin/branches/create">New branch</Link>
                    </Button>
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/admin/branches', { search });
                    }}
                >
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search branches..."
                        className="max-w-sm"
                    />
                </form>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Code</th>
                                    <th className="p-3">Region</th>
                                    <th className="p-3">Staff</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {branches.data.map((b) => (
                                    <tr
                                        key={b.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-medium">
                                            {b.name}
                                        </td>
                                        <td className="p-3">{b.code}</td>
                                        <td className="p-3">{b.region}</td>
                                        <td className="p-3">{b.users_count}</td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    b.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {b.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                        <td className="flex gap-2 p-3">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`/admin/branches/${b.id}/edit`}
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setStatus(
                                                        b.id,
                                                        b.status === 'active'
                                                            ? 'inactive'
                                                            : 'active',
                                                    )
                                                }
                                            >
                                                {b.status === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate'}
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {branches.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No branches found.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
