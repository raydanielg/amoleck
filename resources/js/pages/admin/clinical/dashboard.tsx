import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

const L: Record<string, string> = {
    total: 'Total',
    draft: 'Drafts',
    inProgress: 'In progress',
    pendingReview: 'Pending review',
    finalized: 'Finalized',
    mine: 'Assigned to me',
};

export default function ClinicalDashboard({
    stats,
    recent,
}: {
    stats: Record<string, number>;
    recent: {
        id: number;
        encounter_number: string;
        type: string;
        status: string;
        encounter_at: string;
        patient: {
            id: number;
            mrn: string;
            first_name: string;
            last_name: string;
        };
        physiotherapist?: { name: string };
    }[];
}) {
    return (
        <>
            <Head title="Clinical Dashboard" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Physiotherapy Clinical"
                    description="Assessment and encounter overview"
                />
                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    {Object.entries(stats).map(([k, v]) => (
                        <Card key={k}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground">
                                    {L[k]}
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {v}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">Recent encounters</h2>
                        {recent.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No encounters yet.
                            </p>
                        )}
                        <ul className="space-y-2 text-sm">
                            {recent.map((e) => (
                                <li
                                    key={e.id}
                                    className="flex items-center justify-between"
                                >
                                    <span>
                                        <Link
                                            href={`/admin/patients/clinical/${e.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {e.encounter_number}
                                        </Link>{' '}
                                        · {e.patient.first_name}{' '}
                                        {e.patient.last_name}{' '}
                                        <span className="text-xs text-muted-foreground">
                                            ({e.patient.mrn})
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <span className="text-xs text-muted-foreground">
                                            {e.physiotherapist?.name}
                                        </span>
                                        <Badge variant="secondary">
                                            {e.status.replace('_', ' ')}
                                        </Badge>
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
