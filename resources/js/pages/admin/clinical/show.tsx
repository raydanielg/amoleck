import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;

const FUNC_LEVELS = [
    'independent',
    'independent_difficulty',
    'supervision',
    'assistance',
    'unable',
    'not_assessed',
];
const FUNC_ACTIVITIES = [
    'walking',
    'standing',
    'sitting',
    'transfers',
    'balance',
    'stairs',
    'lifting',
    'reaching',
    'dressing',
    'personal_care',
    'work',
    'daily_living',
];
const OBJ_CATS = [
    'posture',
    'gait',
    'muscle_tone',
    'joint_mobility',
    'swelling',
    'sensation',
    'reflexes',
    'balance',
    'coordination',
    'functional_movement',
];
const RED_CATS = [
    'neurological',
    'fracture',
    'dvt',
    'severe_symptoms',
    'spinal_pathology',
    'post_op',
    'medical_precaution',
    'other',
];
const SIDES = ['right', 'left', 'bilateral'];
const SPECIAL_CATS = ['sensory', 'motor', 'reflex', 'special_test'];

function Sec({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardContent className="p-5">
                <h2 className="mb-3 font-medium">{title}</h2>
                {children}
            </CardContent>
        </Card>
    );
}

function Rows({
    rows,
    cols,
    name,
    readOnly,
}: {
    rows: R[];
    cols: [
        string,
        string,
        ('text' | 'number' | 'date' | 'select' | 'textarea' | 'check')?,
        string[]?,
    ][];
    name: string;
    readOnly: boolean;
}) {
    const [items, setItems] = useState<R[]>(rows.length ? rows : [{}]);
    const add = () => setItems([...items, {}]);
    const rem = (i: number) => setItems(items.filter((_, x) => x !== i));
    return (
        <div className="space-y-2">
            {items.map((row, i) => (
                <div
                    key={i}
                    className="flex flex-wrap items-start gap-2 rounded-md border p-2"
                >
                    {cols.map(([k, label, type, opts]) => (
                        <div key={k} className="min-w-[110px] flex-1">
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
                                            {o.replace('_', ' ')}
                                        </option>
                                    ))}
                                </select>
                            ) : type === 'check' ? (
                                <input
                                    type="checkbox"
                                    name={`${name}[${i}][${k}]`}
                                    value="1"
                                    defaultChecked={!!row[k]}
                                    disabled={readOnly}
                                    className="mt-2"
                                />
                            ) : type === 'textarea' ? (
                                <textarea
                                    name={`${name}[${i}][${k}]`}
                                    defaultValue={row[k] ?? ''}
                                    disabled={readOnly}
                                    className="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm"
                                    rows={1}
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
                            onClick={() => rem(i)}
                        >
                            ×
                        </Button>
                    )}
                </div>
            ))}
            {!readOnly && (
                <Button type="button" size="sm" variant="outline" onClick={add}>
                    + Add
                </Button>
            )}
        </div>
    );
}

export default function ClinicalShow({
    encounter,
    patientHistory,
}: {
    encounter: R;
    patientHistory: R;
}) {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const can = (p: string) => (auth?.permissions ?? []).includes(p);
    const e = encounter;
    const readOnly =
        e.status === 'finalized' ||
        e.status === 'amended' ||
        e.status === 'cancelled';
    const p = e.patient;

    const ta = (name: string, label: string, val: string) => (
        <div key={name}>
            <label className="text-xs text-muted-foreground">{label}</label>
            <textarea
                name={`assessment[${name}]`}
                defaultValue={val ?? ''}
                disabled={readOnly}
                rows={2}
                className="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
            />
        </div>
    );

    return (
        <>
            <Head title={e.encounter_number} />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title={`${e.encounter_number}`}
                        description={`${p.first_name} ${p.last_name} · ${p.mrn} · ${e.type.replace('_', ' ')} · ${e.branch?.name}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={
                                e.status === 'finalized'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {e.status.replace('_', ' ')}
                        </Badge>
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={`/admin/clinical/encounters/${e.id}/print`}
                                target="_blank"
                            >
                                Print
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/admin/patients/${p.id}`}>
                                Patient
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card className="border-blue-200 bg-blue-50/50">
                    <CardContent className="p-4 text-sm">
                        <div className="grid gap-2 sm:grid-cols-4">
                            <span>
                                <strong>MRN:</strong> {p.mrn}
                            </span>
                            <span>
                                <strong>DOB:</strong> {p.date_of_birth ?? '—'}
                            </span>
                            <span>
                                <strong>Allergies:</strong>{' '}
                                {p.allergy_status?.replace('_', ' ')}
                            </span>
                            <span>
                                <strong>Physio:</strong>{' '}
                                {e.physiotherapist?.name ?? '—'}
                            </span>
                        </div>
                        {patientHistory.conditions?.length > 0 && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                History:{' '}
                                {patientHistory.conditions
                                    .map((c: R) => c.name)
                                    .join(', ')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Form
                    action={`/admin/patients/clinical/${e.id}`}
                    method="post"
                    transform={(d) => ({ ...d, _method: 'put' })}
                >
                    {({ processing }) => (
                        <div className="space-y-6">
                            <Sec title="Presenting complaints">
                                <Rows
                                    rows={e.complaints}
                                    name="complaints"
                                    readOnly={readOnly}
                                    cols={[
                                        ['complaint', 'Complaint'],
                                        ['onset_date', 'Onset', 'date'],
                                        ['location', 'Location'],
                                        [
                                            'patient_description',
                                            'Description',
                                            'textarea',
                                        ],
                                        ['aggravating', 'Aggravating'],
                                        ['relieving', 'Relieving'],
                                        [
                                            'functional_limitations',
                                            'Limitations',
                                        ],
                                    ]}
                                />
                            </Sec>
                            <Sec title="History of present illness">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="text-xs text-muted-foreground">
                                            Onset type
                                        </label>
                                        <select
                                            name="assessment[onset_type]"
                                            defaultValue={
                                                e.assessment?.onset_type ?? ''
                                            }
                                            disabled={readOnly}
                                            className="mt-1 h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            <option value="">—</option>
                                            <option value="sudden">
                                                Sudden
                                            </option>
                                            <option value="gradual">
                                                Gradual
                                            </option>
                                        </select>
                                    </div>
                                    {ta(
                                        'mechanism',
                                        'Mechanism of injury',
                                        e.assessment?.mechanism,
                                    )}
                                </div>
                                {ta(
                                    'progression',
                                    'Progression',
                                    e.assessment?.progression,
                                )}
                                {ta(
                                    'previous_interventions',
                                    'Previous interventions',
                                    e.assessment?.previous_interventions,
                                )}
                                {ta(
                                    'investigations',
                                    'Investigations',
                                    e.assessment?.investigations,
                                )}
                                {ta(
                                    'patient_expectations',
                                    'Patient expectations',
                                    e.assessment?.patient_expectations,
                                )}
                            </Sec>
                            <Sec title="Pain assessment (NPRS)">
                                <Rows
                                    rows={e.pain_assessments}
                                    name="pain"
                                    readOnly={readOnly}
                                    cols={[
                                        ['location', 'Location'],
                                        ['pain_type', 'Type'],
                                        [
                                            'nprs_current',
                                            'Current /10',
                                            'number',
                                        ],
                                        ['nprs_best', 'Best /10', 'number'],
                                        ['nprs_worst', 'Worst /10', 'number'],
                                        ['aggravating', 'Aggravating'],
                                        ['relieving', 'Relieving'],
                                        ['not_assessable', 'N/A', 'check'],
                                        ['not_assessable_reason', 'Reason'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Functional assessment">
                                <Rows
                                    rows={e.functional_entries}
                                    name="functional"
                                    readOnly={readOnly}
                                    cols={[
                                        [
                                            'activity',
                                            'Activity',
                                            'select',
                                            FUNC_ACTIVITIES,
                                        ],
                                        [
                                            'level',
                                            'Level',
                                            'select',
                                            FUNC_LEVELS,
                                        ],
                                        ['notes', 'Notes'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Objective examination">
                                <Rows
                                    rows={e.objective_findings}
                                    name="objective"
                                    readOnly={readOnly}
                                    cols={[
                                        [
                                            'category',
                                            'Category',
                                            'select',
                                            OBJ_CATS,
                                        ],
                                        ['finding', 'Finding', 'textarea'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="ROM measurements">
                                <Rows
                                    rows={e.rom_measurements}
                                    name="rom"
                                    readOnly={readOnly}
                                    cols={[
                                        ['body_region', 'Region'],
                                        ['joint', 'Joint'],
                                        ['movement', 'Movement'],
                                        ['side', 'Side', 'select', SIDES],
                                        ['active_rom', 'Active', 'number'],
                                        ['passive_rom', 'Passive', 'number'],
                                        ['pain_during', 'Pain', 'check'],
                                        ['observations', 'Notes'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Muscle strength (MMT)">
                                <Rows
                                    rows={e.strength_measurements}
                                    name="strength"
                                    readOnly={readOnly}
                                    cols={[
                                        ['muscle_group', 'Muscle group'],
                                        ['body_region', 'Region'],
                                        ['side', 'Side', 'select', SIDES],
                                        ['grade', 'Grade /5', 'number'],
                                        [
                                            'pain_or_limitations',
                                            'Pain/limitations',
                                        ],
                                        ['observations', 'Notes'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Neurological & special tests">
                                <Rows
                                    rows={e.special_tests}
                                    name="special_tests"
                                    readOnly={readOnly}
                                    cols={[
                                        [
                                            'category',
                                            'Category',
                                            'select',
                                            SPECIAL_CATS,
                                        ],
                                        ['test_name', 'Test'],
                                        ['result', 'Result'],
                                        ['interpretation', 'Interpretation'],
                                        ['observations', 'Notes'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Red flags & precautions">
                                <Rows
                                    rows={e.red_flags}
                                    name="red_flags"
                                    readOnly={readOnly}
                                    cols={[
                                        [
                                            'category',
                                            'Category',
                                            'select',
                                            RED_CATS,
                                        ],
                                        [
                                            'description',
                                            'Description',
                                            'textarea',
                                        ],
                                        [
                                            'severity',
                                            'Severity',
                                            'select',
                                            ['suspected', 'confirmed'],
                                        ],
                                        ['action_taken', 'Action taken'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Clinical problems">
                                <Rows
                                    rows={e.problems}
                                    name="problems"
                                    readOnly={readOnly}
                                    cols={[
                                        ['description', 'Description'],
                                        ['body_region', 'Region'],
                                        ['laterality', 'Laterality'],
                                        ['functional_impact', 'Impact'],
                                        [
                                            'severity',
                                            'Severity',
                                            'select',
                                            ['mild', 'moderate', 'severe'],
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
                            <Sec title="Body map">
                                <Rows
                                    rows={e.body_map_annotations}
                                    name="body_map"
                                    readOnly={readOnly}
                                    cols={[
                                        [
                                            'view',
                                            'View',
                                            'select',
                                            ['front', 'back'],
                                        ],
                                        ['region', 'Region'],
                                        ['side', 'Side', 'select', SIDES],
                                        [
                                            'annotation_type',
                                            'Type',
                                            'select',
                                            [
                                                'pain',
                                                'swelling',
                                                'sensory',
                                                'injury',
                                                'other',
                                            ],
                                        ],
                                        ['note', 'Note'],
                                    ]}
                                />
                            </Sec>
                            <Sec title="Assessment summary">
                                {ta(
                                    'subjective_summary',
                                    'Subjective summary',
                                    e.assessment?.subjective_summary,
                                )}
                                {ta(
                                    'objective_summary',
                                    'Objective summary',
                                    e.assessment?.objective_summary,
                                )}
                                {ta(
                                    'functional_impairments',
                                    'Functional impairments',
                                    e.assessment?.functional_impairments,
                                )}
                                {ta(
                                    'interpretation',
                                    'Clinical interpretation',
                                    e.assessment?.interpretation,
                                )}
                                {ta(
                                    'limitations',
                                    'Assessment limitations',
                                    e.assessment?.limitations,
                                )}
                                {ta(
                                    'recommendations',
                                    'Recommendations',
                                    e.assessment?.recommendations,
                                )}
                            </Sec>
                            {!readOnly && (
                                <Button type="submit" disabled={processing}>
                                    Save draft
                                </Button>
                            )}
                        </div>
                    )}
                </Form>

                <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                    {!readOnly &&
                        ['in_progress', 'pending_review'].map((s) => (
                            <Button
                                key={s}
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/admin/clinical/encounters/${e.id}/transition`,
                                        { status: s },
                                    )
                                }
                            >
                                {s.replace('_', ' ')}
                            </Button>
                        ))}
                    {e.status === 'in_progress' &&
                        can('patientcare.clinical.assessments.finalize') && (
                            <Button
                                size="sm"
                                onClick={() => {
                                    if (
                                        confirm(
                                            'Finalize this assessment? It becomes read-only.',
                                        )
                                    )
                                        router.post(
                                            `/admin/clinical/encounters/${e.id}/transition`,
                                            { status: 'finalized' },
                                        );
                                }}
                            >
                                Finalize
                            </Button>
                        )}
                    {e.status === 'pending_review' &&
                        can('patientcare.clinical.assessments.finalize') && (
                            <Button
                                size="sm"
                                onClick={() => {
                                    if (confirm('Finalize?'))
                                        router.post(
                                            `/admin/clinical/encounters/${e.id}/transition`,
                                            { status: 'finalized' },
                                        );
                                }}
                            >
                                Finalize
                            </Button>
                        )}
                    {!readOnly &&
                        can('patientcare.clinical.encounters.cancel') && (
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => {
                                    const reason = prompt(
                                        'Cancellation reason',
                                    );
                                    if (reason)
                                        router.post(
                                            `/admin/clinical/encounters/${e.id}/transition`,
                                            { status: 'cancelled', reason },
                                        );
                                }}
                            >
                                Cancel
                            </Button>
                        )}
                    {['finalized', 'amended'].includes(e.status) &&
                        can('patientcare.clinical.assessments.amend') && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    const reason = prompt('Amendment reason');
                                    if (reason)
                                        router.post(
                                            `/admin/clinical/encounters/${e.id}/amend`,
                                            { reason },
                                        );
                                }}
                            >
                                Amend
                            </Button>
                        )}
                </div>

                {e.versions.length > 0 && (
                    <Sec title="Version history">
                        <ul className="space-y-1 text-sm">
                            {e.versions.map((v: R) => (
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
