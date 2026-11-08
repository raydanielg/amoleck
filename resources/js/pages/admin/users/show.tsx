import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type U = Record<string, any> & {
    roles: { name: string }[];
    branches: { name: string }[];
    primaryBranch?: { name: string };
};
type Log = {
    id: number;
    action?: string;
    event?: string;
    email?: string;
    ip_address?: string;
    created_at: string;
};

function Info({ title, rows }: { title: string; rows: [string, any][] }) {
    return (
        <Card>
            <CardContent className="p-5">
                <h2 className="mb-3 font-medium">{title}</h2>
                <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    {rows
                        .filter(([, v]) => v)
                        .map(([l, v]) => (
                            <div key={l}>
                                <dt className="text-muted-foreground">{l}</dt>
                                <dd>{v}</dd>
                            </div>
                        ))}
                </dl>
            </CardContent>
        </Card>
    );
}

export default function UserShow({
    user,
    loginHistory,
    activity,
}: {
    user: U;
    loginHistory: Log[];
    activity: Log[];
}) {
    return (
        <>
            <Head title={user.name} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={user.name}
                        description={`${user.job_title ?? 'Staff'} · ${user.employee_number ?? 'No employee no.'}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={
                                user.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {user.status}
                        </Badge>
                        <Button asChild variant="outline" size="sm">
                            <Link href={`/admin/users/${user.id}/edit`}>
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Info
                        title="Personal"
                        rows={[
                            ['Email', user.email],
                            ['Username', user.username],
                            ['Phone', user.phone],
                            ['Gender', user.gender],
                            ['Date of birth', user.date_of_birth],
                            ['Address', user.address],
                            [
                                'Emergency contact',
                                [
                                    user.emergency_contact_name,
                                    user.emergency_contact_phone,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || null,
                            ],
                        ]}
                    />
                    <Info
                        title="Employment"
                        rows={[
                            ['Job title', user.job_title],
                            ['Department', user.department],
                            ['Type', user.employment_type],
                            ['Start date', user.employment_start_date],
                            ['Primary branch', user.primaryBranch?.name],
                            [
                                'Branches',
                                user.branches.map((b) => b.name).join(', '),
                            ],
                            [
                                'Last login',
                                user.last_login_at
                                    ? new Date(
                                          user.last_login_at,
                                      ).toLocaleString()
                                    : null,
                            ],
                        ]}
                    />
                </div>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">Roles</h2>
                        {user.roles.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No roles assigned.
                            </p>
                        ) : (
                            user.roles.map((r) => (
                                <Badge
                                    key={r.name}
                                    variant="secondary"
                                    className="mr-1"
                                >
                                    {r.name}
                                </Badge>
                            ))
                        )}
                    </CardContent>
                </Card>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">Login history</h2>
                            {loginHistory.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No logins recorded.
                                </p>
                            )}
                            <ul className="space-y-1 text-sm">
                                {loginHistory.map((l) => (
                                    <li
                                        key={l.id}
                                        className="flex justify-between"
                                    >
                                        <span>
                                            {l.event}{' '}
                                            {l.ip_address
                                                ? `· ${l.ip_address}`
                                                : ''}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {new Date(
                                                l.created_at,
                                            ).toLocaleString()}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">Activity</h2>
                            {activity.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No recorded activity.
                                </p>
                            )}
                            <ul className="space-y-1 text-sm">
                                {activity.map((a) => (
                                    <li
                                        key={a.id}
                                        className="flex justify-between"
                                    >
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
            </div>
        </>
    );
}
