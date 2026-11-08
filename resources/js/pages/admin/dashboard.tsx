import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Stat = Record<string, number>;
type Branch = {
    id: number;
    name: string;
    code: string;
    manager_name?: string;
    users_count: number;
    status: string;
};
type Row = {
    id: number;
    action?: string;
    event?: string;
    email?: string;
    ip_address?: string;
    created_at: string;
    user?: { name: string };
    entity_type?: string;
};
type Security = {
    recentLogins: Row[];
    failedLogins: Row[];
    lockedAccounts: number;
    rbacChanges: Row[];
} | null;

const LABELS: Record<string, string> = {
    totalStaff: 'Total staff',
    activeStaff: 'Active staff',
    inactiveAccounts: 'Inactive',
    suspendedUsers: 'Suspended',
    totalBranches: 'Branches',
    activeBranches: 'Active branches',
    activeModules: 'Active modules',
    disabledModules: 'Disabled modules',
};

function List({
    rows,
    render,
    empty,
}: {
    rows: Row[];
    render: (r: Row) => React.ReactNode;
    empty: string;
}) {
    return (
        <ul className="space-y-1.5 text-sm">
            {rows.length === 0 && (
                <li className="text-muted-foreground">{empty}</li>
            )}
            {rows.map((r) => (
                <li key={r.id} className="flex justify-between gap-2">
                    <span className="truncate">{render(r)}</span>
                    <span className="shrink-0 text-muted-foreground">
                        {new Date(r.created_at).toLocaleString()}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export default function Dashboard({
    stats,
    branches,
    security,
    activity,
    filters,
}: {
    stats: Stat;
    branches: Branch[];
    security: Security;
    activity: Row[];
    filters: Record<string, string>;
}) {
    const [action, setAction] = useState(filters.action ?? '');
    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Administration Dashboard"
                    description="Central hospital administration overview"
                />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(stats).map(([k, v]) => (
                        <Card key={k}>
                            <CardContent className="p-4">
                                <p className="text-sm text-muted-foreground">
                                    {LABELS[k] ?? k}
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {v}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    {branches.length > 0 && (
                        <Card>
                            <CardContent className="p-5">
                                <h2 className="mb-3 font-medium">
                                    Branches ({branches.length})
                                </h2>
                                <ul className="space-y-2 text-sm">
                                    {branches.map((b) => (
                                        <li
                                            key={b.id}
                                            className="flex items-center justify-between"
                                        >
                                            <span>
                                                <Link
                                                    href={`/admin/branches/${b.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {b.name}
                                                </Link>{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    {b.code}
                                                    {b.manager_name
                                                        ? ` · ${b.manager_name}`
                                                        : ''}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-2">
                                                <span className="text-muted-foreground">
                                                    {b.users_count} staff
                                                </span>
                                                <Badge
                                                    variant={
                                                        b.status === 'active'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {b.status}
                                                </Badge>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}
                    {security && (
                        <Card>
                            <CardContent className="p-5">
                                <h2 className="mb-3 font-medium">Security</h2>
                                <p className="mb-3 text-sm">
                                    {security.lockedAccounts} locked account(s)
                                </p>
                                <h3 className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                    Failed logins
                                </h3>
                                <List
                                    rows={security.failedLogins}
                                    render={(r) => (
                                        <>
                                            {r.email}{' '}
                                            <span className="text-muted-foreground">
                                                · {r.ip_address}
                                            </span>
                                        </>
                                    )}
                                    empty="None."
                                />
                                <h3 className="mt-4 mb-2 text-xs font-medium text-muted-foreground uppercase">
                                    RBAC changes
                                </h3>
                                <List
                                    rows={security.rbacChanges}
                                    render={(r) => (
                                        <>
                                            {r.action}{' '}
                                            {r.user ? `by ${r.user.name}` : ''}
                                        </>
                                    )}
                                    empty="None."
                                />
                            </CardContent>
                        </Card>
                    )}
                </div>
                <Card>
                    <CardContent className="p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-medium">Recent activity</h2>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    router.get('/admin/dashboard', { action });
                                }}
                            >
                                <Input
                                    value={action}
                                    onChange={(e) => setAction(e.target.value)}
                                    placeholder="Filter action (e.g. user.)"
                                    className="h-8 w-48"
                                />
                            </form>
                        </div>
                        <List
                            rows={activity}
                            render={(r) => (
                                <>
                                    {r.action}{' '}
                                    {r.user ? `by ${r.user.name}` : ''}
                                </>
                            )}
                            empty="No activity recorded."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
