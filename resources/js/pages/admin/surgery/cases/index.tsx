import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
const badgeFor = (s: string) =>
    s === 'emergency' ? 'destructive' : ['cancelled', 'rejected'].includes(s) ? 'outline' : 'secondary';
export default function CasesIndex({
    cases,
    statuses,
    priorities,
    filters,
    branches,
}: {
    cases: { data: R[] };
    statuses: string[];
    priorities: string[];
    filters: R;
    branches: R[];
}) {
    return (
        <>
            <Head title="Surgical Cases" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Surgical Cases"
                        description="Registered surgical cases and their lifecycle"
                    />
                    <Button asChild size="sm">
                        <Link href="/admin/surgery/cases/create">New case</Link>
                    </Button>
                </div>
                <Form method="get" action="/admin/surgery/cases" className="flex flex-wrap gap-2">
                    <Input name="q" placeholder="Case no / patient / MRN" defaultValue={filters.q ?? ''} className="h-8 w-56" />
                    <select name="status" defaultValue={filters.status ?? ''} className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                        <option value="">All statuses</option>
                        {statuses.map((s) => (
                            <option key={s} value={s}>{s.replaceAll('_', ' ')}</option>
                        ))}
                    </select>
                    <select name="priority" defaultValue={filters.priority ?? ''} className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                        <option value="">All priorities</option>
                        {priorities.map((p) => (
                            <option key={p} value={p}>{p}</option>
                        ))}
                    </select>
                    <select name="branch_id" defaultValue={filters.branch_id ?? ''} className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                        <option value="">All branches</option>
                        {branches.map((b) => (
                            <option key={b.id} value={b.id}>{b.name}</option>
                        ))}
                    </select>
                    <Button size="sm" type="submit">Filter</Button>
                </Form>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="p-3">Case</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Procedure</th>
                                    <th className="p-3">Priority</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Branch</th>
                                    <th className="p-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {cases.data.map((c) => (
                                    <tr key={c.id} className="border-b last:border-0">
                                        <td className="p-3 font-mono text-xs">{c.case_number}</td>
                                        <td className="p-3">
                                            {c.patient?.first_name} {c.patient?.last_name}
                                            <span className="ml-1 text-xs text-muted-foreground">{c.patient?.mrn}</span>
                                        </td>
                                        <td className="p-3">{c.procedure?.name ?? '—'}</td>
                                        <td className="p-3">
                                            <Badge variant={badgeFor(c.priority)}>{c.priority}</Badge>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="secondary">{c.status.replaceAll('_', ' ')}</Badge>
                                        </td>
                                        <td className="p-3 text-xs">{c.branch?.name}</td>
                                        <td className="p-3 text-right">
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={`/admin/surgery/cases/${c.id}`}>Open</Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {cases.data.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="p-6 text-center text-muted-foreground">
                                            No surgical cases found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
