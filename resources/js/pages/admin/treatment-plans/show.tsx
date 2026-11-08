import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
const GOAL_STATUS = [
    'not_started',
    'in_progress',
    'achieved',
    'partially_achieved',
    'not_achieved',
    'discontinued',
];
const PREC_TYPES = [
    'contraindication',
    'precaution',
    'modification',
    'escalation',
];

function Sec({
    title,
    children,
    danger,
}: {
    title: string;
    children: React.ReactNode;
    danger?: boolean;
}) {
    return (
        <Card className={danger ? 'border-destructive/50' : ''}>
            <CardContent className="p-5">
                <h2
                    className={`mb-3 font-medium ${danger ? 'text-destructive' : ''}`}
                >
                    {title}
                </h2>
                {children}
            </CardContent>
        </Card>
    );
}

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
        ('text' | 'number' | 'date' | 'select' | 'check' | 'textarea')?,
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

export default function PlanShow({
    plan,
    safety,
    catalogue,
}: {
    plan: R;
    safety: R;
    catalogue: R[];
}) {
    const { auth } = usePage().props as {
        auth: { permissions?: string[]; user?: R };
    };
    const can = (p: string) => (auth?.permissions ?? []).includes(p);
    const readOnly = [
        'completed',
        'discontinued',
        'cancelled',
        'approved',
        'active',
        'on_hold',
    ].includes(plan.status);
    const post = (status: string, extra = {}) =>
        router.post(`/admin/treatment-plans/${plan.id}/transition`, {
            status,
            ...extra,
        });

    return (
        <>
            <Head title={plan.plan_number} />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title={plan.plan_number}
                        description={`${plan.patient.first_name} ${plan.patient.last_name} · ${plan.patient.mrn} · ${plan.branch?.name}${plan.encounter ? ` · Assessment ${plan.encounter.encounter_number}` : ''}`}
                    />
                    <div className="flex gap-2">
                        <Badge
                            variant={
                                plan.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {plan.status.replace('_', ' ')}
                        </Badge>
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={`/admin/treatment-plans/${plan.id}/print`}
                                target="_blank"
                            >
                                Print
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/admin/patients/${plan.patient.id}`}>
                                Patient
                            </Link>
                        </Button>
                    </div>
                </div>

                {safety.allergies.length +
                    safety.red_flags.length +
                    safety.medications.length >
                    0 && (
                    <Sec title="Safety context" danger>
                        <div className="grid gap-2 text-sm sm:grid-cols-2">
                            {safety.allergies.length > 0 && (
                                <p>
                                    <strong>Allergies:</strong>{' '}
                                    {safety.allergies.join(', ')}
                                </p>
                            )}
                            {safety.medications.length > 0 && (
                                <p>
                                    <strong>Medications:</strong>{' '}
                                    {safety.medications.join(', ')}
                                </p>
                            )}
                            {safety.conditions.length > 0 && (
                                <p>
                                    <strong>Conditions:</strong>{' '}
                                    {safety.conditions.join(', ')}
                                </p>
                            )}
                            {safety.red_flags.length > 0 && (
                                <p className="text-destructive">
                                    <strong>Red flags:</strong>{' '}
                                    {safety.red_flags.join('; ')}
                                </p>
                            )}
                        </div>
                    </Sec>
                )}

                <Form
                    action={`/admin/treatment-plans/${plan.id}`}
                    method="post"
                    transform={(d) => ({ ...d, _method: 'put' })}
                >
                    {({ processing }) => (
                        <div className="space-y-6">
                            <Sec title="Clinical problems">
                                <Rows
                                    rows={plan.problems}
                                    name="problems"
                                    readOnly={readOnly}
                                    cols={[
                                        ['description', 'Description', 'text'],
                                        ['body_region', 'Region'],
                                        ['laterality', 'Laterality'],
                                        [
                                            'supporting_findings',
                                            'Findings',
                                            'textarea',
                                        ],
                                        ['functional_impact', 'Impact'],
                                        ['baseline', 'Baseline'],
                                        [
                                            'priority',
                                            'Priority',
                                            'select',
                                            ['low', 'medium', 'high'],
                                        ],
                                        [
                                            'status',
                                            'Status',
                                            'select',
                                            ['active', 'resolved'],
                                        ],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Goals">
                                <Rows
                                    rows={plan.goals}
                                    name="goals"
                                    readOnly={readOnly}
                                    cols={[
                                        ['description', 'Goal'],
                                        [
                                            'type',
                                            'Type',
                                            'select',
                                            ['short_term', 'long_term'],
                                        ],
                                        ['baseline', 'Baseline'],
                                        ['target', 'Target'],
                                        ['unit', 'Unit'],
                                        ['target_date', 'Target date', 'date'],
                                        [
                                            'priority',
                                            'Priority',
                                            'select',
                                            ['low', 'medium', 'high'],
                                        ],
                                        ['measurement_method', 'Method'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Planned interventions">
                                <Rows
                                    rows={plan.planned_interventions}
                                    name="planned_interventions"
                                    readOnly={readOnly}
                                    cols={[
                                        [
                                            'clinical_intervention_id',
                                            'Intervention',
                                            'select',
                                            catalogue.map((c: R) => `${c.id}`),
                                        ],
                                        ['body_region', 'Region'],
                                        ['frequency', 'Frequency'],
                                        ['duration', 'Duration'],
                                        ['intensity', 'Intensity'],
                                        ['sets', 'Sets', 'number'],
                                        ['reps', 'Reps', 'number'],
                                        ['hold_duration', 'Hold'],
                                        ['rest_period', 'Rest'],
                                        ['resistance', 'Resistance'],
                                        ['precautions', 'Precautions'],
                                        ['expected_response', 'Expected'],
                                    ]}
                                />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Intervention column accepts catalogue ID —
                                    see /admin/interventions
                                </p>
                            </Sec>
                            <Sec title="Precautions & contraindications">
                                <Rows
                                    rows={plan.precaution_records}
                                    name="precautions"
                                    readOnly={readOnly}
                                    cols={[
                                        ['type', 'Type', 'select', PREC_TYPES],
                                        [
                                            'description',
                                            'Description',
                                            'textarea',
                                        ],
                                    ]}
                                />
                            </Sec>
                            {!readOnly && (
                                <Button type="submit" disabled={processing}>
                                    Save plan
                                </Button>
                            )}
                        </div>
                    )}
                </Form>

                <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                    {plan.status === 'draft' &&
                        can('patientcare.treatment_plans.submit') && (
                            <Button
                                size="sm"
                                onClick={() => post('pending_review')}
                            >
                                Submit for review
                            </Button>
                        )}
                    {plan.status === 'pending_review' &&
                        can('patientcare.treatment_plans.approve') && (
                            <>
                                <Button
                                    size="sm"
                                    onClick={() => {
                                        if (confirm('Approve plan?'))
                                            post('approved', {
                                                comments:
                                                    prompt(
                                                        'Comments (optional)',
                                                    ) || '',
                                            });
                                    }}
                                >
                                    Approve
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() => {
                                        const r = prompt('Rejection reason');
                                        if (r) post('cancelled', { reason: r });
                                    }}
                                >
                                    Reject
                                </Button>
                            </>
                        )}
                    {plan.status === 'approved' &&
                        can('patientcare.treatment_plans.activate') && (
                            <Button size="sm" onClick={() => post('active')}>
                                Activate
                            </Button>
                        )}
                    {plan.status === 'active' &&
                        can('patientcare.treatment_plans.hold') && (
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
                        )}
                    {plan.status === 'on_hold' &&
                        can('patientcare.treatment_plans.activate') && (
                            <Button size="sm" onClick={() => post('active')}>
                                Resume
                            </Button>
                        )}
                    {['active', 'on_hold'].includes(plan.status) && (
                        <>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    if (confirm('Mark completed?'))
                                        post('completed', {
                                            reason: prompt('Reason') || '',
                                        });
                                }}
                            >
                                Complete
                            </Button>
                            {can('patientcare.treatment_plans.discontinue') && (
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() => {
                                        const r = prompt('Discontinue reason');
                                        if (r)
                                            post('discontinued', { reason: r });
                                    }}
                                >
                                    Discontinue
                                </Button>
                            )}
                        </>
                    )}
                </div>

                <Sec title="Goal progress">
                    {plan.goals.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No goals yet.
                        </p>
                    )}
                    <ul className="space-y-3 text-sm">
                        {plan.goals.map((g: R) => (
                            <li key={g.id} className="rounded-md border p-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">
                                        {g.description}
                                    </span>
                                    <Badge variant="secondary">
                                        {g.status.replace(/_/g, ' ')}
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {g.baseline} → {g.target} {g.unit} ·{' '}
                                    {g.type.replace('_', ' ')}
                                </p>
                                {g.progress_entries?.length > 0 && (
                                    <ul className="mt-2 space-y-1 text-xs">
                                        {g.progress_entries.map((p: R) => (
                                            <li key={p.id}>
                                                — {p.value}{' '}
                                                {p.comment
                                                    ? `(${p.comment})`
                                                    : ''}{' '}
                                                <span className="text-muted-foreground">
                                                    {new Date(
                                                        p.created_at,
                                                    ).toLocaleDateString()}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {can(
                                    'patientcare.treatment_goals.progress.manage',
                                ) &&
                                    ['active', 'on_hold', 'approved'].includes(
                                        plan.status,
                                    ) && (
                                        <Form
                                            action={`/admin/treatment-plans/goals/${g.id}/progress`}
                                            method="post"
                                        >
                                            {() => (
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    <Input
                                                        name="value"
                                                        placeholder="Value"
                                                        className="h-8 w-28"
                                                    />
                                                    <select
                                                        name="status"
                                                        className="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                                                    >
                                                        {GOAL_STATUS.map(
                                                            (s) => (
                                                                <option
                                                                    key={s}
                                                                    value={s}
                                                                >
                                                                    {s.replace(
                                                                        /_/g,
                                                                        ' ',
                                                                    )}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                    <Input
                                                        name="comment"
                                                        placeholder="Comment"
                                                        className="h-8 w-40"
                                                    />
                                                    <Button
                                                        size="sm"
                                                        type="submit"
                                                    >
                                                        +
                                                    </Button>
                                                </div>
                                            )}
                                        </Form>
                                    )}
                            </li>
                        ))}
                    </ul>
                </Sec>

                {plan.reviews.length > 0 && (
                    <Sec title="Review history">
                        <ul className="space-y-1 text-sm">
                            {plan.reviews.map((r: R) => (
                                <li key={r.id} className="flex justify-between">
                                    <span>
                                        {r.decision} by {r.reviewer?.name}
                                        {r.rejection_reason
                                            ? ` — ${r.rejection_reason}`
                                            : ''}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {new Date(
                                            r.created_at,
                                        ).toLocaleString()}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Sec>
                )}
                {plan.versions.length > 1 && (
                    <Sec title="Version history">
                        <ul className="space-y-1 text-sm">
                            {plan.versions.map((v: R) => (
                                <li key={v.id} className="flex justify-between">
                                    <span>
                                        v{v.version} — {v.action} by{' '}
                                        {v.acted_by?.name ?? '—'}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {new Date(
                                            v.created_at,
                                        ).toLocaleString()}
                                        {v.reason ? ` · ${v.reason}` : ''}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Sec>
                )}
            </div>
        </>
    );
}
