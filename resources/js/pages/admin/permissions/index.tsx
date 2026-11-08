import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type Perm = {
    name: string;
    description: string;
    risk_level: string;
    roles_count: number;
};
type Group = { module: string; permissions: Perm[] };

export default function Permissions({ registry }: { registry: Group[] }) {
    const risk = (r: string) =>
        r === 'critical'
            ? 'destructive'
            : r === 'high'
              ? 'default'
              : 'secondary';
    return (
        <>
            <Head title="Permission Registry" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Permission Registry"
                    description="All registered permissions by module"
                />
                <div className="grid gap-6 lg:grid-cols-2">
                    {registry.map((g) => (
                        <Card key={g.module}>
                            <CardContent className="p-5">
                                <h2 className="mb-3 font-medium">{g.module}</h2>
                                <table className="w-full text-sm">
                                    <tbody>
                                        {g.permissions.map((p) => (
                                            <tr
                                                key={p.name}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-2 pr-2 font-mono text-xs">
                                                    {p.name}
                                                </td>
                                                <td className="py-2 text-muted-foreground">
                                                    {p.description}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <Badge
                                                        variant={risk(
                                                            p.risk_level,
                                                        )}
                                                    >
                                                        {p.risk_level}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}
