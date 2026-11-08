import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';

type L = {
    id: number;
    action: string;
    before?: Record<string, unknown> | null;
    after?: Record<string, unknown> | null;
    created_at: string;
    user?: { name: string };
};

export default function SettingsHistory({ logs }: { logs: { data: L[] } }) {
    return (
        <>
            <Head title="Settings History" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Settings History"
                    description="Sanitized record of configuration changes"
                />
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Time</th>
                                    <th className="p-3">Action</th>
                                    <th className="p-3">By</th>
                                    <th className="p-3">Change</th>
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
                                        <td className="p-3 font-mono text-xs">
                                            {l.after
                                                ? JSON.stringify(l.after)
                                                : ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {logs.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No settings changes recorded.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
