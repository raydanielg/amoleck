import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Rev = {
    id: number;
    matched_on?: string;
    patient: { id: number; mrn: string; first_name: string; last_name: string };
    matched_patient: {
        id: number;
        mrn: string;
        first_name: string;
        last_name: string;
    };
};

export default function Duplicates({ reviews }: { reviews: { data: Rev[] } }) {
    const resolve = (id: number, resolution: string) =>
        router.post(`/admin/patients/duplicates/${id}/resolve`, { resolution });
    return (
        <>
            <Head title="Duplicate Patients" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Duplicate Review"
                    description="Potential duplicate patient records"
                />
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">New patient</th>
                                    <th className="p-3">Possible match</th>
                                    <th className="p-3">Matched on</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {reviews.data.map((r) => (
                                    <tr
                                        key={r.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-medium">
                                            {r.patient.first_name}{' '}
                                            {r.patient.last_name}{' '}
                                            <span className="font-mono text-xs">
                                                {r.patient.mrn}
                                            </span>
                                        </td>
                                        <td className="p-3">
                                            {r.matched_patient.first_name}{' '}
                                            {r.matched_patient.last_name}{' '}
                                            <span className="font-mono text-xs">
                                                {r.matched_patient.mrn}
                                            </span>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="secondary">
                                                {r.matched_on}
                                            </Badge>
                                        </td>
                                        <td className="flex gap-2 p-3">
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                onClick={() =>
                                                    resolve(
                                                        r.id,
                                                        'confirmed_duplicate',
                                                    )
                                                }
                                            >
                                                Duplicate
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    resolve(
                                                        r.id,
                                                        'not_duplicate',
                                                    )
                                                }
                                            >
                                                Not a duplicate
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {reviews.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No pending duplicate reviews.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
