import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function PatientDashboard({
    stats,
    byBranch,
    recent,
}: {
    stats: Record<string, number>;
    byBranch: { branch: string; total: number }[];
    recent: {
        id: number;
        mrn: string;
        first_name: string;
        last_name: string;
        status: string;
        created_at: string;
    }[];
}) {
    const cards: [string, number][] = [
        ['Total patients', stats.total],
        ['Active', stats.active],
        ['New this month', stats.newThisMonth],
        ['Inactive', stats.inactive],
        ['Archived', stats.archived],
    ];
    return (
        <>
            <Head title="Patient Dashboard" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Patient Management"
                        description="Registration and records overview"
                    />
                    <Button asChild>
                        <Link href="/admin/patients/create">
                            Register patient
                        </Link>
                    </Button>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {cards.map(([l, v]) => (
                        <Card key={l}>
                            <CardContent className="p-4">
                                <p className="text-sm text-muted-foreground">
                                    {l}
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {v}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">
                                Patients by branch
                            </h2>
                            {byBranch.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No patients yet.
                                </p>
                            )}
                            <ul className="space-y-1 text-sm">
                                {byBranch.map((b) => (
                                    <li
                                        key={b.branch}
                                        className="flex justify-between"
                                    >
                                        <span>{b.branch}</span>
                                        <span>{b.total}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">
                                Recently registered
                            </h2>
                            {recent.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No patients yet.
                                </p>
                            )}
                            <ul className="space-y-2 text-sm">
                                {recent.map((p) => (
                                    <li
                                        key={p.id}
                                        className="flex items-center justify-between"
                                    >
                                        <span>
                                            <Link
                                                href={`/admin/patients/${p.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {p.first_name} {p.last_name}
                                            </Link>{' '}
                                            <span className="text-xs text-muted-foreground">
                                                {p.mrn}
                                            </span>
                                        </span>
                                        <Badge variant="secondary">
                                            {p.status}
                                        </Badge>
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
