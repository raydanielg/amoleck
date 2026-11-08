import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type P = {
    id: number;
    mrn: string;
    first_name: string;
    middle_name?: string;
    last_name: string;
    phone?: string;
    status: string;
    primary_branch?: { name: string };
};

export default function Patients({
    patients,
    branches,
    filters,
}: {
    patients: {
        data: P[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    branches: { id: number; name: string }[];
    filters: Record<string, string>;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const go = (params: Record<string, string>) =>
        router.get('/admin/patients', { search, ...filters, ...params });
    return (
        <>
            <Head title="Patients" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Patient Directory"
                        description="Search and manage patient records"
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/patients/duplicates">
                                Duplicates
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="/admin/patients/create">
                                Register patient
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="flex flex-wrap gap-3">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            go({});
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="MRN, name, phone, email..."
                            className="w-72"
                        />
                    </form>
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) => go({ status: e.target.value })}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        {['active', 'inactive', 'archived', 'deceased'].map(
                            (s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ),
                        )}
                    </select>
                    <select
                        value={filters.branch_id ?? ''}
                        onChange={(e) => go({ branch_id: e.target.value })}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All branches</option>
                        {branches.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                    </select>
                </div>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">MRN</th>
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Phone</th>
                                    <th className="p-3">Branch</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {patients.data.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {p.mrn}
                                        </td>
                                        <td className="p-3 font-medium">
                                            <Link
                                                href={`/admin/patients/${p.id}`}
                                                className="hover:underline"
                                            >
                                                {p.first_name} {p.middle_name}{' '}
                                                {p.last_name}
                                            </Link>
                                        </td>
                                        <td className="p-3">{p.phone}</td>
                                        <td className="p-3">
                                            {p.primary_branch?.name}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    p.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {p.status}
                                            </Badge>
                                        </td>
                                        <td className="p-3">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`/admin/patients/${p.id}/edit`}
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {patients.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No patients found.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
