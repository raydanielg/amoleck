import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
const STATUSES_IV = ['performed', 'modified', 'not_performed'];
const IV_REASONS = [
    'patient_declined',
    'patient_unavailable',
    'clinical_precaution',
    'intolerance',
    'equipment',
    'time',
    'other',
];
const GOAL_STATUSES = [
    'not_started',
    'in_progress',
    'improving',
    'no_change',
    'deteriorating',
    'achieved',
    'not_achieved',
    'not_assessed',
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
        ('text' | 'number' | 'select' | 'textarea' | 'check')?,
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
                            ) : type === 'check' ? (
                                <input
                                    type="checkbox"
                                    name={`${name}[${i}][${k}]`}
                                    value="1"
                                    defaultChecked={!!row[k]}
                                    disabled={readOnly}
                                    className="mt-2"
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

export default function SessionShow({
    session,
    plannedInterventions,
    goals,
    tolerances,
}: {
    session: R;
    plannedInterventions: R[];
    goals: R[];
    tolerances: string[];
}) {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const can = (p: string) => (auth?.permissions ?? []).includes(p);
    const s = session;
    const readOnly = ['finalized', 'amended', 'cancelled'].includes(s.status);
    const post = (status: string, extra = {}) =>
        router.post(`/admin/sessions/${s.id}/transition`, { status, ...extra });
    const field = (name: string, label: string, type = 'text') => (
        <div key={name} className="min-w-[140px] flex-1">
            <label className="text-xs text-muted-foreground">{label}</label>
            <Input
                name={`session[${name}]`}
                type={type}
                defaultValue={s[name] ?? ''}
                disabled={readOnly}
                className="mt-1 h-8"
            />
        </div>
    );
    const ta = (name: string, label: string, src: R) => (
        <div key={name}>
            <label className="text-xs text-muted-foreground">{label}</label>
            <textarea
                name={`notes[${name}]`}
                defaultValue={src?.[name] ?? ''}
                disabled={readOnly}
                rows={3}
                className="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
            />
        </div>
    );

    return (
        <>
            <Head title={s.session_number} />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title={s.session_number}
                        description={`${s.patient.first_name} ${s.patient.last_name} · ${s.patient.mrn} · ${s.type.replace('_', ' ')} · Plan ${s.plan?.plan_number ?? '—'}`}
                    />
                    <div className="flex gap-2">
                        <Badge
                            variant={
                                s.status === 'finalized'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {s.status.replace('_', ' ')}
                        </Badge>
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={`/admin/sessions/${s.id}/print`}
                                target="_blank"
                            >
                                Print
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={`/admin/treatment-plans/${s.clinical_treatment_plan_id}`}
                            >
                                Plan
                            </Link>
                        </Button>
                    </div>
                </div>

                <Form
                    action={`/admin/sessions/${s.id}`}
                    method="post"
                    transform={(d) => ({ ...d, _method: 'put' })}
                >
                    {({ processing }) => (
                        <div className="space-y-6">
                            <Sec title="Pre-treatment assessment">
                                <div className="flex flex-wrap gap-3">
                                    {field('pre_condition', 'Condition')}
                                    {field('pre_pain', 'Pain /10', 'number')}
                                    {field(
                                        'pre_functional',
                                        'Functional status',
                                    )}
                                </div>
                                <div className="mt-3 flex flex-wrap gap-3">
                                    {field('pre_changes', 'Changes since last')}
                                    {field(
                                        'pre_medical_updates',
                                        'Medical updates',
                                    )}
                                </div>
                                <div className="mt-3 flex items-center gap-3">
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="session[pre_not_assessed]"
                                            value="1"
                                            defaultChecked={
                                                !!s.pre_not_assessed
                                            }
                                            disabled={readOnly}
                                        />{' '}
                                        Not assessed
                                    </label>
                                    {field('pre_not_assessed_reason', 'Reason')}
                                </div>
                            </Sec>
                            <Sec title="Interventions performed">
                                {plannedInterventions.length > 0 && (
                                    <p className="mb-2 text-xs text-muted-foreground">
                                        Planned:{' '}
                                        {plannedInterventions
                                            .map(
                                                (i: R) =>
                                                    i.intervention?.name ??
                                                    'custom',
                                            )
                                            .join(', ')}
                                    </p>
                                )}
                                <Rows
                                    rows={s.interventions}
                                    name="interventions"
                                    readOnly={readOnly}
                                    cols={[
                                        ['name', 'Intervention'],
                                        [
                                            'status',
                                            'Status',
                                            'select',
                                            STATUSES_IV,
                                        ],
                                        [
                                            'not_performed_reason',
                                            'Reason',
                                            'select',
                                            IV_REASONS,
                                        ],
                                        ['technique', 'Technique'],
                                        ['dosage', 'Dosage'],
                                        ['sets', 'Sets', 'number'],
                                        ['reps', 'Reps', 'number'],
                                        ['resistance', 'Resistance'],
                                        ['duration', 'Duration'],
                                        ['equipment', 'Equipment'],
                                        [
                                            'tolerance',
                                            'Tolerance',
                                            'select',
                                            tolerances,
                                        ],
                                        [
                                            'observations',
                                            'Observations',
                                            'textarea',
                                        ],
                                        ['modification', 'Modification'],
                                        ['modification_reason', 'Mod. reason'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Post-treatment">
                                <div className="flex flex-wrap gap-3">
                                    {field(
                                        'post_pain',
                                        'Pain after /10',
                                        'number',
                                    )}
                                    {field(
                                        'overall_tolerance',
                                        'Tolerance',
                                        'text',
                                    )}
                                    {field('stop_reason', 'Stop reason')}
                                </div>
                                <div className="mt-3 flex flex-wrap gap-3">
                                    {field(
                                        'post_functional',
                                        'Functional response',
                                    )}
                                    {field('post_feedback', 'Patient feedback')}
                                </div>
                                <div className="mt-3">
                                    {field('post_next_steps', 'Next session')}
                                </div>
                            </Sec>
                            <Sec title="Progress notes (SOAP)">
                                {ta('subjective', 'S — Subjective', s.notes)}
                                {ta('objective', 'O — Objective', s.notes)}
                                {ta('assessment', 'A — Assessment', s.notes)}
                                {ta('plan', 'P — Plan', s.notes)}
                            </Sec>
                            <Sec title="Adverse events" danger>
                                <Rows
                                    rows={s.adverse_events}
                                    name="adverse_events"
                                    readOnly={readOnly}
                                    cols={[
                                        [
                                            'description',
                                            'Description',
                                            'textarea',
                                        ],
                                        ['action_taken', 'Action taken'],
                                        ['escalation', 'Escalation'],
                                    ]}
                                />
                            </Sec>
                            {!readOnly && (
                                <Button type="submit" disabled={processing}>
                                    Save session
                                </Button>
                            )}
                        </div>
                    )}
                </Form>

                {goals.length > 0 && (
                    <Sec title="Goal progress (session-linked)">
                        <ul className="space-y-3 text-sm">
                            {goals.map((g: R) => (
                                <li
                                    key={g.id}
                                    className="rounded-md border p-3"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium">
                                            {g.description}
                                        </span>
                                        <Badge variant="secondary">
                                            {g.status.replace(/_/g, ' ')}
                                        </Badge>
                                    </div>
                                    {g.progress_entries?.length > 0 && (
                                        <ul className="mt-1 space-y-0.5 text-xs">
                                            {g.progress_entries.map((p: R) => (
                                                <li key={p.id}>
                                                    — {p.value}
                                                    {p.clinical_treatment_session_id
                                                        ? ' (session)'
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
                                    {!readOnly &&
                                        can(
                                            'patientcare.clinical.sessions.interventions.manage',
                                        ) && (
                                            <Form
                                                action={`/admin/sessions/${s.id}/goals/${g.id}/progress`}
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
                                                            {GOAL_STATUSES.map(
                                                                (st) => (
                                                                    <option
                                                                        key={st}
                                                                        value={
                                                                            st
                                                                        }
                                                                    >
                                                                        {st.replace(
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
                                                            className="h-8 w-36"
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
                )}

                <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                    {!readOnly &&
                        s.status !== 'pending_review' &&
                        can('patientcare.clinical.sessions.submit') && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => post('pending_review')}
                            >
                                Submit for review
                            </Button>
                        )}
                    {!readOnly &&
                        can('patientcare.clinical.sessions.finalize') && (
                            <Button
                                size="sm"
                                onClick={() => {
                                    if (confirm('Finalize this session?'))
                                        post('finalized');
                                }}
                            >
                                Finalize
                            </Button>
                        )}
                    {!readOnly &&
                        can('patientcare.clinical.sessions.cancel') && (
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => {
                                    const r = prompt('Cancellation reason');
                                    if (r) post('cancelled', { reason: r });
                                }}
                            >
                                Cancel
                            </Button>
                        )}
                    {['finalized', 'amended'].includes(s.status) &&
                        can('patientcare.clinical.sessions.amend') && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    const r = prompt('Amendment reason');
                                    if (r)
                                        router.post(
                                            `/admin/sessions/${s.id}/amend`,
                                            { reason: r },
                                        );
                                }}
                            >
                                Amend
                            </Button>
                        )}
                </div>

                {s.versions.length > 0 && (
                    <Sec title="Version history">
                        <ul className="space-y-1 text-sm">
                            {s.versions.map((v: R) => (
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
