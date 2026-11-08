import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Role = {
    id: number;
    name: string;
    slug?: string;
    description?: string;
    status?: string;
    permissions: { name: string }[];
};
type U = { id: number; name: string; email: string; status: string };
type A = { id: number; action: string; created_at: string };

export default function RoleShow({
    role,
    users,
    activity,
    protected: isProtected,
}: {
    role: Role;
    users: U[];
    activity: A[];
    protected: boolean;
}) {
    return (
        <>
            <Head title={role.name} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={role.name}
                        description={role.description ?? role.slug}
                    />
                    <div className="flex items-center gap-2">
                        {isProtected && (
                            <Badge variant="secondary">protected</Badge>
                        )}
                        <Badge
                            variant={
                                role.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {role.status}
                        </Badge>
                        <Button asChild variant="outline" size="sm">
                            <Link href={`/admin/roles/${role.id}/edit`}>
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">
                                Permissions ({role.permissions.length})
                            </h2>
                            <div className="flex flex-wrap gap-1.5">
                                {role.permissions.map((p) => (
                                    <Badge key={p.name} variant="secondary">
                                        {p.name}
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">
                                Assigned users ({users.length})
                            </h2>
                            {users.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No users assigned.
                                </p>
                            )}
                            <ul className="space-y-1 text-sm">
                                {users.map((u) => (
                                    <li key={u.id}>
                                        {u.name}{' '}
                                        <span className="text-muted-foreground">
                                            · {u.email}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">Recent changes</h2>
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
