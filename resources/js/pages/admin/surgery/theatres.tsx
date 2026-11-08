import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
export default function Theatres({
    theatres, departments, branches, branchId, statuses, categories,
}: {
    theatres: R[]; departments: R[]; branches: R[]; branchId: number; statuses: string[]; categories: string[];
}) {
    return (
        <>
            <Head title="Operating Theatres" />
            <div className="space-y-6 p-6">
                <Heading title="Operating Theatres" description="Theatre configuration & status" />
                <Card><CardContent className="p-4">
                    <h3 className="mb-3 text-sm font-medium">New theatre</h3>
                    <Form method="post" action="/admin/surgery/theatres">
                        {() => (
                            <div className="flex flex-wrap items-end gap-2">
                                <Input name="code" placeholder="Code" required className="h-8 w-28" />
                                <Input name="name" placeholder="Name" required className="h-8 w-48" />
                                <select name="branch_id" required className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                    {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                                </select>
                                <select name="department_id" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                    <option value="">— dept —</option>
                                    {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                                <select name="category" className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                                    {categories.map((cat) => <option key={cat} value={cat}>{cat.replaceAll('_', ' ')}</option>)}
                                </select>
                                <Input name="prep_minutes" type="number" placeholder="Prep min" className="h-8 w-24" />
                                <Input name="cleaning_minutes" type="number" placeholder="Clean min" className="h-8 w-24" />
                                <Button size="sm" type="submit">Create</Button>
                            </div>
                        )}
                    </Form>
                </CardContent></Card>
                <div className="grid gap-4 lg:grid-cols-2">
                    {theatres.map((t) => (
                        <Card key={t.id}><CardContent className="p-4">
                            <div className="mb-2 flex items-center justify-between">
                                <h3 className="font-medium">{t.name} <span className="font-mono text-xs text-muted-foreground">{t.code}</span></h3>
                                <Badge variant={t.status === 'available' ? 'default' : ['maintenance', 'out_of_service'].includes(t.status) ? 'destructive' : 'secondary'}>
                                    {t.status.replaceAll('_', ' ')}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {t.category} · prep {t.prep_minutes}m · clean {t.cleaning_minutes}m
                            </p>
                            <div className="mt-2 text-xs">
                                Hours: {(t.hours ?? []).map((h: R) => `${DAYS[h.day_of_week]} ${h.start_time?.slice(0, 5)}–${h.end_time?.slice(0, 5)}`).join(', ') || 'not configured'}
                            </div>
                            {(t.exceptions ?? []).length > 0 && (
                                <div className="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                    Exceptions: {t.exceptions.map((e: R) => `${e.date} (${e.available ? 'open' : e.reason})`).join(', ')}
                                </div>
                            )}
                            <div className="mt-3 flex flex-wrap gap-1">
                                {statuses.filter((s) => s !== t.status).map((s) => (
                                    <Button key={s} size="sm" variant="ghost" className="h-7 px-2 text-xs"
                                        onClick={() => router.post(`/admin/surgery/theatres/${t.id}/status`, { status: s, reason: prompt('Reason') ?? undefined })}>
                                        {s.replaceAll('_', ' ')}
                                    </Button>
                                ))}
                            </div>
                            <Form method="post" action={`/admin/surgery/theatres/${t.id}/hours`}>
                                {() => (
                                    <div className="mt-3 flex items-end gap-1 text-xs">
                                        <select name="day_of_week" className="h-7 rounded border border-input bg-transparent px-1">
                                            {DAYS.map((d, i) => <option key={d} value={i}>{d}</option>)}
                                        </select>
                                        <Input type="time" name="start_time" required className="h-7 w-24" />
                                        <Input type="time" name="end_time" required className="h-7 w-24" />
                                        <Button size="sm" variant="outline" type="submit" className="h-7">Set hours</Button>
                                    </div>
                                )}
                            </Form>
                            <Form method="post" action={`/admin/surgery/theatres/${t.id}/exceptions`}>
                                {() => (
                                    <div className="mt-2 flex items-end gap-1 text-xs">
                                        <Input type="date" name="date" required className="h-7" />
                                        <Input name="reason" placeholder="Closure reason" required className="h-7" />
                                        <Button size="sm" variant="outline" type="submit" className="h-7">Block date</Button>
                                    </div>
                                )}
                            </Form>
                        </CardContent></Card>
                    ))}
                </div>
            </div>
        </>
    );
}
