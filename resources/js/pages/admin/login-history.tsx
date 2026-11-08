import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type L = {
    id: number;
    email?: string;
    event: string;
    ip_address?: string;
    created_at: string;
    user?: { name: string };
};

export default function LoginHistory({ logs }: { logs: { data: L[] } }) {
    return (
        <>
            <Head title="Login History" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Login History"
                    description="Staff sign-in activity"
                />
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Time</th>
                                    <th className="p-3">Email</th>
                                    <th className="p-3">Event</th>
                                    <th className="p-3">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((l) => (
                                    <tr
                                        key={l.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 whitespace-nowrap">
                                            {new Date(
                                                l.created_at,
                                            ).toLocaleString()}
                                        </td>
                                        <td className="p-3">{l.email}</td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    l.event === 'failed'
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                            >
                                                {l.event}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-xs">
                                            {l.ip_address}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {logs.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No login records yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
