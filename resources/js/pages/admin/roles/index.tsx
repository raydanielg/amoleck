import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = {
    id: number;
    name: string;
    users_count: number;
    permissions_count: number;
};

export default function Roles({ roles }: { roles: R[] }) {
    return (
        <>
            <Head title="Roles & Permissions" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Roles & Permissions"
                        description="Manage roles and their permissions"
                    />
                    <Button asChild>
                        <Link href="/admin/roles/create">New role</Link>
                    </Button>
                </div>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Role</th>
                                    <th className="p-3">Permissions</th>
                                    <th className="p-3">Users</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {roles.map((r) => (
                                    <tr
                                        key={r.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-medium">
                                            {r.name}
                                            {r.name === 'Super Admin' && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    protected
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="p-3">
                                            {r.permissions_count}
                                        </td>
                                        <td className="p-3">{r.users_count}</td>
                                        <td className="flex gap-2 p-3">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`/admin/roles/${r.id}/edit`}
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        `/admin/roles/${r.id}/duplicate`,
                                                    )
                                                }
                                            >
                                                Duplicate
                                            </Button>
                                            {r.users_count === 0 &&
                                                r.name !== 'Super Admin' && (
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            router.delete(
                                                                `/admin/roles/${r.id}`,
                                                            )
                                                        }
                                                    >
                                                        Delete
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
