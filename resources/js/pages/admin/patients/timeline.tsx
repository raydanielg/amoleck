import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type Ev = {
    at: string;
    kind: string;
    title: string;
    by: string;
    meta?: Record<string, unknown>;
};
const COLORS: Record<string, string> = {
    clinical: 'default',
    referral: 'secondary',
    document: 'outline',
    administrative: 'secondary',
};

export default function Timeline({
    patient,
    events,
}: {
    patient: { id: number; mrn: string };
    events: Ev[];
}) {
    return (
        <>
            <Head title={`Timeline ${patient.mrn}`} />
            <div className="space-y-6 p-6">
                <Heading
                    title="Patient timeline"
                    description={`${patient.mrn} · chronological history`}
                />
                <Card>
                    <CardContent className="p-5">
                        {events.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No events.
                            </p>
                        )}
                        <ol className="relative space-y-4 border-l pl-6">
                            {events.map((e, i) => (
                                <li key={i} className="relative">
                                    <span className="absolute top-1 -left-[31px] h-3 w-3 rounded-full bg-primary" />
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                (COLORS[e.kind] ??
                                                    'secondary') as any
                                            }
                                        >
                                            {e.kind}
                                        </Badge>
                                        <span className="text-sm font-medium">
                                            {e.title}
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {new Date(e.at).toLocaleString()} ·{' '}
                                        {e.by}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>
                <Link
                    href={`/admin/patients/${patient.id}`}
                    className="text-sm underline"
                >
                    ← Back to profile
                </Link>
            </div>
        </>
    );
}
