import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';

type L = {
    id: number;
    action: string;
    entity_type?: string;
    entity_id?: number;
    ip_address?: string;
    created_at: string;
    user?: { name: string };
};

export default function AuditLogs({ logs }: { logs: { data: L[] } }) {
    return (
        <>
            <Head title="Security & Audit Logs" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Security & Audit Logs"
                    description="Immutable record of system activity"
                />
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Time</th>
                                    <th className="p-3">Action</th>
                                    <th className="p-3">By</th>
                                    <th className="p-3">Entity</th>
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
                                        <td className="p-3 font-medium">
                                            {l.action}
                                        </td>
                                        <td className="p-3">
                                            {l.user?.name ?? '—'}
                                        </td>
                                        <td className="p-3 text-xs text-muted-foreground">
                                            {l.entity_type
                                                ? `${l.entity_type.split('\\').pop()}#${l.entity_id}`
                                                : '—'}
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
                                No audit records yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
