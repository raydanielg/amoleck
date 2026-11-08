import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Branch = {
    id: number;
    name: string;
    code: string;
    status: string;
    phone?: string;
    email?: string;
    address?: string;
    region?: string;
    district?: string;
    ward?: string;
    manager_name?: string;
    opening_date?: string;
    description?: string;
    users: {
        id: number;
        name: string;
        email: string;
        job_title?: string;
        status: string;
        pivot?: { is_primary: boolean };
    }[];
};
type Activity = { id: number; action: string; created_at: string };

export default function BranchShow({
    branch,
    activity,
}: {
    branch: Branch;
    activity: Activity[];
}) {
    const rows: [string, string | undefined][] = [
        ['Code', branch.code],
        ['Phone', branch.phone],
        ['Email', branch.email],
        ['Address', branch.address],
        ['Region', branch.region],
        ['District', branch.district],
        ['Ward', branch.ward],
        ['Manager', branch.manager_name],
        ['Opened', branch.opening_date],
    ];
    return (
        <>
            <Head title={branch.name} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={branch.name}
                        description={branch.description}
                    />
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={
                                branch.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {branch.status.replace('_', ' ')}
                        </Badge>
                        <Button asChild variant="outline" size="sm">
                            <Link href={`/admin/branches/${branch.id}/edit`}>
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">Details</h2>
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                {rows.map(([l, v]) => (
                                    <div key={l}>
                                        <dt className="text-muted-foreground">
                                            {l}
                                        </dt>
                                        <dd>{v || '—'}</dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">
                                Assigned staff ({branch.users.length})
                            </h2>
                            {branch.users.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No staff assigned.
                                </p>
                            )}
                            <ul className="space-y-2 text-sm">
                                {branch.users.map((u) => (
                                    <li
                                        key={u.id}
                                        className="flex items-center justify-between"
                                    >
                                        <span>
                                            {u.name}{' '}
                                            {u.pivot?.is_primary ? (
                                                <Badge variant="secondary">
                                                    primary
                                                </Badge>
                                            ) : null}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {u.job_title || u.email}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">Recent activity</h2>
                        {activity.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No recorded activity.
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
