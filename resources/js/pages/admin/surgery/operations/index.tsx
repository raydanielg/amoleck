import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type R = Record<string, any>;
export default function OperationsIndex({ operations, branches, branchId }: { operations: { data: R[] }; branches: R[]; branchId: number }) {
    return (
        <>
            <Head title="Operations" />
            <div className="space-y-6 p-6">
                <Heading title="Surgical Operations" description="Intra-operative records" />
                <Card><CardContent className="p-0">
                    <table className="w-full text-sm">
                        <thead className="border-b text-left text-xs text-muted-foreground">
                            <tr><th className="p-3">Operation</th><th className="p-3">Case</th><th className="p-3">Patient</th><th className="p-3">Theatre</th><th className="p-3">Surgeon</th><th className="p-3">Status</th><th className="p-3"></th></tr>
                        </thead>
                        <tbody>
                            {operations.data.map((o) => (
                                <tr key={o.id} className="border-b last:border-0">
                                    <td className="p-3 font-mono text-xs">{o.operation_number}</td>
                                    <td className="p-3 font-mono text-xs">{o.case?.case_number}</td>
                                    <td className="p-3">{o.patient?.first_name} {o.patient?.last_name}</td>
                                    <td className="p-3">{o.theatre?.name}</td>
                                    <td className="p-3">{o.surgeon?.name}</td>
                                    <td className="p-3"><Badge variant={o.status === 'in_progress' ? 'default' : 'secondary'}>{o.status.replaceAll('_', ' ')}</Badge></td>
                                    <td className="p-3 text-right"><Button asChild size="sm" variant="outline"><Link href={`/admin/surgery/operations/${o.id}`}>Workspace</Link></Button></td>
                                </tr>
                            ))}
                            {operations.data.length === 0 && (
                                <tr><td colSpan={7} className="p-6 text-center text-muted-foreground">No operations.</td></tr>
                            )}
                        </tbody>
                    </table>
                </CardContent></Card>
            </div>
        </>
    );
}
