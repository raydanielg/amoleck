import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type M = {
    id: number;
    identifier: string;
    name: string;
    description?: string;
    version: string;
    category: string;
    status: string;
    protected: boolean;
    dependencies: string[];
    roles: string[];
};
type Stats = {
    total: number;
    active: number;
    disabled: number;
    maintenance: number;
    unavailable: number;
};

const variant = (s: string) =>
    s === 'active'
        ? 'default'
        : s === 'maintenance'
          ? 'destructive'
          : 'secondary';

export default function Modules({
    modules,
    stats,
}: {
    modules: M[];
    stats: Stats;
}) {
    return (
        <>
            <Head title="Module Management" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Module Management"
                    description="Registry, availability and access control"
                />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {[
                        ['Registered', stats.total],
                        ['Active', stats.active],
                        ['Disabled', stats.disabled],
                        ['Maintenance', stats.maintenance],
                        ['Unavailable', stats.unavailable],
                    ].map(([l, v]) => (
                        <Card key={l}>
                            <CardContent className="p-4">
                                <p className="text-sm text-muted-foreground">
                                    {l}
                                </p>
                                <p className="text-xl font-semibold">{v}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Module</th>
                                    <th className="p-3">Version</th>
                                    <th className="p-3">Dependencies</th>
                                    <th className="p-3">Roles</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {modules.map((m) => (
                                    <tr
                                        key={m.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-medium">
                                            <Link
                                                href={`/admin/modules/${m.id}`}
                                                className="hover:underline"
                                            >
                                                {m.name}
                                            </Link>
                                            {m.protected && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    required
                                                </Badge>
                                            )}
                                            <div className="text-xs text-muted-foreground">
                                                {m.description}
                                            </div>
                                        </td>
                                        <td className="p-3">{m.version}</td>
                                        <td className="p-3 text-xs">
                                            {m.dependencies.join(', ') || '—'}
                                        </td>
                                        <td className="p-3 text-xs">
                                            {m.roles.length
                                                ? m.roles.join(', ')
                                                : 'all'}
                                        </td>
                                        <td className="p-3">
                                            <Badge variant={variant(m.status)}>
                                                {m.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                        <td className="flex gap-2 p-3">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`/admin/modules/${m.id}`}
                                                >
                                                    Manage
                                                </Link>
                                            </Button>
                                            {!m.protected &&
                                                m.status !== 'unavailable' &&
                                                m.status !==
                                                    'pending_installation' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `/admin/modules/${m.name}/toggle`,
                                                            )
                                                        }
                                                    >
                                                        {m.status === 'active'
                                                            ? 'Disable'
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
