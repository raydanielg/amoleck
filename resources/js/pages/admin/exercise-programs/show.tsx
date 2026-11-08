import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
const ADHERENCE = [
    'completed',
    'partially_completed',
    'not_completed',
    'skipped',
    'not_reported',
];

function Rows({
    rows,
    name,
    cols,
    readOnly,
}: {
    rows: R[];
    name: string;
    cols: [
        string,
        string,
        ('text' | 'number' | 'select' | 'textarea')?,
        string[]?,
    ][];
    readOnly: boolean;
}) {
    const [items, setItems] = useState<R[]>(rows.length ? rows : [{}]);
    return (
        <div className="space-y-2">
            {items.map((row, i) => (
                <div
                    key={i}
                    className="flex flex-wrap items-start gap-2 rounded-md border p-2"
                >
                    {cols.map(([k, label, type, opts]) => (
                        <div key={k} className="min-w-[100px] flex-1">
                            <label className="text-xs text-muted-foreground">
                                {label}
                            </label>
                            {type === 'select' ? (
                                <select
                                    name={`${name}[${i}][${k}]`}
                                    defaultValue={row[k] ?? ''}
                                    disabled={readOnly}
                                    className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm"
                                >
                                    <option value="">—</option>
                                    {opts!.map((o) => (
                                        <option key={o} value={o}>
                                            {o.replace(/_/g, ' ')}
                                        </option>
                                    ))}
                                </select>
                            ) : type === 'textarea' ? (
                                <textarea
                                    name={`${name}[${i}][${k}]`}
                                    defaultValue={row[k] ?? ''}
                                    disabled={readOnly}
                                    rows={1}
                                    className="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm"
                                />
                            ) : (
                                <Input
                                    name={`${name}[${i}][${k}]`}
                                    type={type ?? 'text'}
                                    defaultValue={row[k] ?? ''}
                                    disabled={readOnly}
                                    className="mt-1 h-8"
                                />
                            )}
                        </div>
                    ))}
                    {!readOnly && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                setItems(items.filter((_, x) => x !== i))
                            }
                        >
                            ×
                        </Button>
                    )}
                </div>
            ))}
            {!readOnly && (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => setItems([...items, {}])}
                >
                    + Add
                </Button>
            )}
        </div>
    );
}

export default function ProgramShow({
    entity,
    kind,
    adherence,
    adherenceRecords,
    library,
    versions,
    safety,
}: {
    entity: R;
    kind: string;
    adherence: R | null;
    adherenceRecords: R[];
    library: R[];
    versions: R[];
    safety: R;
}) {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const can = (p: string) => (auth?.permissions ?? []).includes(p);
    const e = entity;
    const readOnly = ['completed', 'discontinued', 'cancelled'].includes(
        e.status,
    );
    const post = (status: string, extra = {}) =>
        router.post(`/admin/exercise-programs/${e.id}/transition`, {
            status,
            kind,
            ...extra,
        });
    const num = e.hep_number ?? e.rx_number;

    return (
        <>
            <Head title={num} />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title={num}
                        description={`${e.patient.first_name} ${e.patient.last_name} · ${e.patient.mrn} · ${kind === 'rx' ? 'Prescription' : 'Home Exercise Program'}`}
                    />
                    <div className="flex gap-2">
                        <Badge
                            variant={
                                e.status === 'active' ? 'default' : 'secondary'
                            }
                        >
                            {e.status.replace('_', ' ')}
                        </Badge>
                        {kind === 'hep' && (
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={`/admin/exercise-programs/${e.id}/print`}
                                    target="_blank"
                                >
                                    Print
                                </a>
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/admin/patients/${e.patient.id}`}>
                                Patient
                            </Link>
                        </Button>
                    </div>
                </div>
                {safety.allergies.length + safety.conditions.length > 0 && (
                    <Card className="border-destructive/50">
                        <CardContent className="p-4 text-sm">
                            <p className="text-destructive">
                                <strong>Safety:</strong>{' '}
                                {[
                                    ...safety.allergies,
                                    ...safety.conditions,
                                ].join(', ')}
                            </p>
                        </CardContent>
                    </Card>
                )}
                <Form
                    action={`/admin/exercise-programs/${e.id}`}
                    method="post"
                    transform={(d) => ({ ...d, _method: 'put', kind })}
                >
                    {({ processing }) => (
                        <div className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <h2 className="mb-3 font-medium">
                                        Exercises
                                    </h2>
                                    <Rows
                                        rows={e.exercises}
                                        name="exercises"
                                        readOnly={readOnly}
                                        cols={[
                                            [
                                                'clinical_exercise_id',
                                                'Exercise',
                                                'select',
                                                library.map(
                                                    (x: R) => `${x.id}`,
                                                ),
                                            ],
                                            ['custom_name', 'Custom name'],
                                            ['sets', 'Sets', 'number'],
                                            ['reps', 'Reps', 'number'],
                                            ['hold_duration', 'Hold'],
                                            ['frequency', 'Frequency'],
                                            ['days_per_week', 'Days/wk'],
                                            [
                                                'patient_instructions',
                                                'Instructions',
                                                'textarea',
                                            ],
                                            ['precautions', 'Precautions'],
                                        ]}
                                    />
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Exercise column accepts library ID — see
                                        /admin/exercises
                                    </p>
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
                <div className="flex flex-wrap gap-2 border-t pt-4">
                    {e.status === 'draft' && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => post('pending_review')}
                        >
                            Submit
                        </Button>
                    )}
                    {e.status === 'pending_review' &&
                        can(
                            'patientcare.clinical.exercises.prescriptions.approve',
                        ) && (
                            <Button size="sm" onClick={() => post('approved')}>
                                Approve
                            </Button>
                        )}
                    {e.status === 'approved' && (
                        <Button size="sm" onClick={() => post('active')}>
                            Activate
                        </Button>
                    )}
                    {e.status === 'active' && (
                        <>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    const r = prompt('Hold reason');
                                    if (r) post('on_hold', { reason: r });
                                }}
                            >
                                Hold
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => {
                                    const r = prompt('Discontinue reason');
                                    if (r) post('discontinued', { reason: r });
                                }}
                            >
                                Discontinue
                            </Button>
                        </>
                    )}
                </div>
                {kind === 'hep' && (
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-3 font-medium">
                                Adherence{' '}
                                {adherence?.rate !== null && adherence
                                    ? `— ${adherence.rate}% (${adherence.completed}/${adherence.reported})`
                                    : ''}
                            </h2>
                            {can(
                                'patientcare.clinical.exercise_adherence.record',
                            ) &&
                                ['active', 'on_hold'].includes(e.status) && (
                                    <Form
                                        action={`/admin/exercise-programs/${e.id}/adherence`}
                                        method="post"
                                    >
                                        {() => (
                                            <div className="mb-3 flex flex-wrap gap-2">
                                                <Input
                                                    name="performed_on"
                                                    type="date"
                                                    required
                                                    className="h-8 w-36"
                                                />
                                                <select
                                                    name="clinical_prescribed_exercise_id"
                                                    className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                                >
                                                    <option value="">—</option>
                                                    {e.exercises.map((x: R) => (
                                                        <option
                                                            key={x.id}
                                                            value={x.id}
                                                        >
                                                            {x.exercise?.name ??
                                                                x.custom_name}
                                                        </option>
                                                    ))}
                                                </select>
                                                <select
                                                    name="status"
                                                    className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                                >
                                                    {ADHERENCE.map((s) => (
                                                        <option
                                                            key={s}
                                                            value={s}
                                                        >
                                                            {s.replace(
                                                                /_/g,
                                                                ' ',
                                                            )}
                                                        </option>
                                                    ))}
                                                </select>
                                                <Input
                                                    name="completed_sets"
                                                    type="number"
                                                    placeholder="Sets"
                                                    className="h-8 w-16"
                                                />
                                                <Input
                                                    name="completed_reps"
                                                    type="number"
                                                    placeholder="Reps"
                                                    className="h-8 w-16"
                                                />
                                                <select
                                                    name="source"
                                                    className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                                >
                                                    <option value="patient_reported">
                                                        Patient reported
                                                    </option>
                                                    <option value="clinician_observed">
                                                        Observed
                                                    </option>
                                                </select>
                                                <Input
                                                    name="comments"
                                                    placeholder="Notes"
                                                    className="h-8 w-32"
                                                />
                                                <Button size="sm" type="submit">
                                                    +
                                                </Button>
                                            </div>
                                        )}
                                    </Form>
                                )}
                            <ul className="space-y-1 text-xs">
                                {adherenceRecords.map((a: R) => (
                                    <li key={a.id}>
                                        {a.performed_on} —{' '}
                                        {a.status.replace(/_/g, ' ')}
                                        {a.completed_sets
                                            ? ` ${a.completed_sets}×${a.completed_reps ?? ''}`
                                            : ''}{' '}
                                        <span className="text-muted-foreground">
                                            ({a.source.replace(/_/g, ' ')})
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
                {versions.length > 0 && (
                    <Card>
                        <CardContent className="p-5">
                            <h2 className="mb-2 font-medium">
                                Version history
                            </h2>
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
