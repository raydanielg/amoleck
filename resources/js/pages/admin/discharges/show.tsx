import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;

export default function DischargeShow({
    discharge,
    types,
    hep,
    versions,
}: {
    discharge: R;
    types: string[];
    hep: R | null;
    versions: R[];
}) {
    const d = discharge;
    const readOnly = ['finalized', 'amended', 'cancelled'].includes(d.status);
    const post = (status: string, extra = {}) =>
        router.post(`/admin/discharges/${d.id}/transition`, {
            status,
            ...extra,
        });
    const f = (name: string, label: string, type = 'text') => (
        <div key={name} className="min-w-[160px] flex-1">
            <label className="text-xs text-muted-foreground">{label}</label>
            <Input
                name={name}
                type={type}
                defaultValue={d[name] ?? ''}
                disabled={readOnly}
                className="mt-1 h-8"
            />
        </div>
    );
    const ta = (name: string, label: string) => (
        <div key={name}>
            <label className="text-xs text-muted-foreground">{label}</label>
            <textarea
                name={name}
                defaultValue={d[name] ?? ''}
                disabled={readOnly}
                rows={3}
                className="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
            />
        </div>
    );

    return (
        <>
            <Head title={d.discharge_number} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={d.discharge_number}
                        description={`${d.patient.first_name} ${d.patient.last_name} · ${d.patient.mrn}`}
                    />
                    <div className="flex gap-2">
                        <Badge
                            variant={
                                d.status === 'finalized'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {d.status.replace('_', ' ')}
                        </Badge>
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={`/admin/discharges/${d.id}/print`}
                                target="_blank"
                            >
                                Print
                            </a>
                        </Button>
                    </div>
                </div>
                {hep && (
                    <Card className="border-primary/30">
                        <CardContent className="p-4 text-sm">
                            Active home program:{' '}
                            <strong>{hep.hep_number}</strong> — decide
                            continuation before discharge.
                        </CardContent>
                    </Card>
                )}
                <Form
                    action={`/admin/discharges/${d.id}`}
                    method="post"
                    transform={(x) => ({ ...x, _method: 'put' })}
                >
                    {({ processing }) => (
                        <div className="space-y-6">
                            <Card>
                                <CardContent className="space-y-4 p-5">
                                    <div className="flex flex-wrap gap-3">
                                        <div className="min-w-[200px] flex-1">
                                            <label className="text-xs text-muted-foreground">
                                                Discharge type
                                            </label>
                                            <select
                                                name="type"
                                                defaultValue={d.type ?? ''}
                                                disabled={readOnly}
                                                className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm"
                                            >
                                                {types.map((t) => (
                                                    <option key={t} value={t}>
                                                        {t.replace(/_/g, ' ')}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        {f(
                                            'discharge_on',
                                            'Discharge date',
                                            'date',
                                        )}
                                        {f(
                                            'baseline_pain',
                                            'Baseline pain',
                                            'number',
                                        )}
                                        {f(
                                            'final_pain',
                                            'Final pain',
                                            'number',
                                        )}
                                    </div>
                                    {ta(
                                        'initial_status',
                                        'Initial clinical status',
                                    )}
                                    {ta(
                                        'final_status',
                                        'Final clinical status',
                                    )}
                                    {ta(
                                        'functional_status',
                                        'Functional status',
                                    )}
                                    {ta('goal_achievement', 'Goal achievement')}
                                    {ta(
                                        'outstanding_concerns',
                                        'Outstanding concerns',
                                    )}
                                    {ta(
                                        'hep_recommendation',
                                        'Home program recommendation',
                                    )}
                                    {ta('follow_up', 'Follow-up')}
                                    {ta('precautions', 'Precautions')}
                                </CardContent>
                            </Card>
                            {!readOnly && (
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            )}
                        </div>
                    )}
                </Form>
                <div className="flex gap-2 border-t pt-4">
                    {d.status === 'draft' && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => post('pending_review')}
                        >
                            Submit
                        </Button>
                    )}
                    {d.status === 'pending_review' && (
                        <Button size="sm" onClick={() => post('approved')}>
                            Approve
                        </Button>
                    )}
                    {d.status === 'approved' && (
                        <Button
                            size="sm"
                            onClick={() => {
                                if (confirm('Finalize discharge?'))
                                    post('finalized');
                            }}
                        >
                            Finalize
                        </Button>
                    )}
                    {d.status === 'finalized' && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                const x = prompt('Amend reason');
                                if (x) post('amended', { reason: x });
                            }}
                        >
                            Amend
                        </Button>
                    )}
                </div>
                {versions.length > 0 && (
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-2 font-medium">Versions</h2>
                            <ul className="space-y-1 text-sm">
                                {versions.map((v: R) => (
                                    <li key={v.id}>
                                        v{v.version} — {v.action} ·{' '}
                                        {new Date(
                                            v.created_at,
                                        ).toLocaleString()}
                                        {v.reason ? ` · ${v.reason}` : ''}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
