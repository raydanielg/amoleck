import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
export default function TheatreCalendar({
    bookings, theatres, readyCases, emergencyCases, staff, equipment, filters, branches, branchId,
}: {
    bookings: R[]; theatres: R[]; readyCases: R[]; emergencyCases: R[]; staff: R[];
    equipment: R[]; filters: R; branches: R[]; branchId: number;
}) {
    const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
    const preCase = params.get('case_id');
    const byDay = bookings.reduce((acc: R, b) => {
        (acc[b.scheduled_date] ??= []).push(b);
        return acc;
    }, {});
    return (
        <>
            <Head title="Theatre Calendar" />
            <div className="space-y-6 p-6">
                <Heading title="Theatre Calendar" description="Surgical bookings & scheduling" />
                <Form method="get" action="/admin/surgery/calendar" className="flex flex-wrap gap-2">
                    <Input type="date" name="from" defaultValue={filters.from} className="h-8 w-40" />
                    <Input type="date" name="to" defaultValue={filters.to} className="h-8 w-40" />
                    <select name="theatre_id" defaultValue={filters.theatre_id ?? ''} className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                        <option value="">All theatres</option>
                        {theatres.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </select>
                    <select name="surgeon_id" defaultValue={filters.surgeon_id ?? ''} className="h-8 rounded-md border border-input bg-transparent px-2 text-sm">
                        <option value="">All surgeons</option>
                        {staff.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    <Button size="sm" type="submit">Filter</Button>
                </Form>

                <Card><CardContent className="p-4">
                    <h3 className="mb-3 text-sm font-medium">New booking</h3>
                    <div className="min-w-[220px] max-w-md">
                        <label className="text-xs text-muted-foreground">Case (ready for scheduling)</label>
                        <select required value={preCase ?? ''} className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm"
                            onChange={(e) => router.get('/admin/surgery/calendar', { ...filters, case_id: e.target.value })}>
                            <option value="">— select —</option>
                            {readyCases.map((rc) => (
                                <option key={rc.id} value={rc.id}>{rc.case_number} — {rc.patient?.first_name} {rc.patient?.last_name} ({rc.procedure?.name ?? 'no procedure'})</option>
                            ))}
                            {emergencyCases.map((rc) => (
                                <option key={rc.id} value={rc.id}>⚠ {rc.case_number} — EMERGENCY ({rc.status.replaceAll('_', ' ')})</option>
                            ))}
                        </select>
                    </div>
                    {preCase && (
                        <Form method="post" action={`/admin/surgery/cases/${preCase}/book`}>
                            {() => (
                                <div className="mt-3 flex flex-wrap items-end gap-2">
                                    <div className="min-w-[160px] flex-1">
                                        <label className="text-xs text-muted-foreground">Theatre</label>
                                        <select name="operating_theatre_id" required className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                            {theatres.filter((t) => !['maintenance', 'out_of_service', 'inactive'].includes(t.status)).map((t) => (
                                                <option key={t.id} value={t.id}>{t.name} ({t.status})</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div><label className="text-xs text-muted-foreground">Date</label><Input type="date" name="scheduled_date" required className="mt-1 h-8" /></div>
                                    <div><label className="text-xs text-muted-foreground">Start</label><Input type="time" name="start_time" required className="mt-1 h-8" /></div>
                                    <div><label className="text-xs text-muted-foreground">End</label><Input type="time" name="end_time" required className="mt-1 h-8" /></div>
                                    <div className="min-w-[150px]">
                                        <label className="text-xs text-muted-foreground">Surgeon</label>
                                        <select name="surgeon_id" className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                            <option value="">—</option>
                                            {staff.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                        </select>
                                    </div>
                                    <div className="min-w-[150px]">
                                        <label className="text-xs text-muted-foreground">Anesthetist</label>
                                        <select name="anesthetist_id" className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm">
                                            <option value="">—</option>
                                            {staff.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                        </select>
                                    </div>
                                    <label className="flex items-center gap-1 text-xs">
                                        <input type="checkbox" name="emergency_override" value="1" /> Emergency override
                                    </label>
                                    <Button size="sm" type="submit">Book</Button>
                                </div>
                            )}
                        </Form>
                    )}
                </CardContent></Card>

                {Object.keys(byDay).sort().map((day) => (
                    <Card key={day}><CardContent className="p-4">
                        <h3 className="mb-2 text-sm font-medium">{day}</h3>
                        <table className="w-full text-sm">
                            <tbody>
                                {byDay[day].map((b: R) => (
                                    <tr key={b.id} className="border-b last:border-0">
                                        <td className="p-2 font-mono text-xs">{b.booking_number}</td>
                                        <td className="p-2">{b.theatre?.name}</td>
                                        <td className="p-2">{b.start_time}–{b.end_time}</td>
                                        <td className="p-2">{b.patient?.first_name} {b.patient?.last_name} <span className="text-xs text-muted-foreground">{b.patient?.mrn}</span></td>
                                        <td className="p-2 text-xs">{b.case?.specialty?.name}</td>
                                        <td className="p-2 text-xs">{b.surgeon?.name}</td>
                                        <td className="p-2"><Badge variant={b.priority === 'emergency' ? 'destructive' : b.status === 'cancelled' ? 'outline' : 'secondary'}>{b.status}</Badge></td>
                                        <td className="p-2 text-right">
                                            <a className="text-xs underline" href={`/admin/surgery/cases/${b.surgical_case_id}`}>case</a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent></Card>
                ))}
                {bookings.length === 0 && <p className="text-sm text-muted-foreground">No bookings in this range.</p>}
            </div>
        </>
    );
}
