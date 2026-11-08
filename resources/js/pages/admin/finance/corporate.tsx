import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function Corporate({
    clients,
    sponsorships,
}: {
    clients: R[];
    sponsorships: R[];
    patients: R[];
}) {
    const money = (n: number | string | null) =>
        n
            ? new Intl.NumberFormat('en-TZ', {
                  style: 'currency',
                  currency: 'TZS',
                  maximumFractionDigits: 0,
              }).format(Number(n))
            : '—';
    return (
        <>
            <Head title="Corporate Billing" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Corporate Clients"
                    description="Employer-sponsored billing"
                />
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Code</th>
                                    <th className="p-3">Client</th>
                                    <th className="p-3">Credit limit</th>
                                    <th className="p-3">Sponsored</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {clients.map((c) => (
                                    <tr
                                        key={c.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {c.code}
                                        </td>
                                        <td className="p-3 font-medium">
                                            {c.name}
                                        </td>
                                        <td className="p-3">
                                            {money(c.credit_limit)}
                                        </td>
                                        <td className="p-3">
                                            {c.sponsorships_count}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    c.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {c.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {clients.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No corporate clients.
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">
                            Sponsorships ({sponsorships.length})
                        </h2>
                        <table className="w-full text-sm">
                            <tbody>
                                {sponsorships.map((s) => (
                                    <tr
                                        key={s.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-2">
                                            {s.patient.first_name}{' '}
                                            {s.patient.last_name}
                                        </td>
                                        <td className="p-2">{s.client.name}</td>
                                        <td className="p-2">
                                            {s.employee_number ?? '—'}
                                        </td>
                                        <td className="p-2">
                                            {s.coverage_percent}%
                                        </td>
                                        <td className="p-2">
                                            <Badge>{s.status}</Badge>
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
