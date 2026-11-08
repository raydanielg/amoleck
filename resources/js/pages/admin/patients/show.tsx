import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;

function Section({
    title,
    children,
    action,
}: {
    title: string;
    children: React.ReactNode;
    action?: React.ReactNode;
}) {
    return (
        <Card>
            <CardContent className="p-5">
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="font-medium">{title}</h2>
                    {action}
                </div>
                {children}
            </CardContent>
        </Card>
    );
}

function Rows({ rows }: { rows: [string, any][] }) {
    return (
        <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            {rows
                .filter(([, v]) => v)
                .map(([l, v]) => (
                    <div key={l}>
                        <dt className="text-muted-foreground">{l}</dt>
                        <dd>{v}</dd>
                    </div>
                ))}
        </dl>
    );
}

export default function PatientShow({
    patient,
    activity,
    branches,
}: {
    patient: R;
    activity: R[];
    branches: { id: number; name: string }[];
}) {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const can = (p: string) => (auth?.permissions ?? []).includes(p);
    const statuses = ['active', 'inactive', 'archived', 'deceased'];
    return (
        <>
            <Head title={patient.mrn} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={patient.full_name}
                        description={`${patient.mrn} · ${patient.gender ?? ''} · ${patient.age ? patient.age + ' yrs' : ''} · ${patient.primary_branch?.name ?? ''}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={
                                patient.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {patient.status}
                        </Badge>
                        {can('patientcare.patients.update') && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={`/admin/patients/${patient.id}/edit`}
                                >
                                    Edit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
                {can('patientcare.patients.update') && (
                    <Form
                        action={`/admin/patients/${patient.id}/status`}
                        method="post"
                    >
                        {() => (
                            <div className="flex flex-wrap items-center gap-2">
                                <select
                                    name="status"
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    {statuses
                                        .filter((s) => s !== patient.status)
                                        .map((s) => (
                                            <option key={s} value={s}>
                                                {s}
                                            </option>
                                        ))}
                                </select>
                                <Input
                                    name="reason"
                                    placeholder="Reason (optional)"
                                    className="w-48"
                                />
                                <Button size="sm" type="submit">
                                    Change status
                                </Button>
                            </div>
                        )}
                    </Form>
                )}
                <div className="flex flex-wrap items-center gap-4">
                    {can('patientcare.allergies.manage') && (
                        <Form
                            action={`/admin/patients/${patient.id}/allergy-status`}
                            method="post"
                        >
                            {() => (
                                <div className="flex items-center gap-2 text-sm">
                                    <span className="text-muted-foreground">
                                        Allergies:
                                    </span>
                                    <select
                                        name="allergy_status"
                                        defaultValue={patient.allergy_status}
                                        className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                    >
                                        <option value="known">
                                            Known allergies
                                        </option>
                                        <option value="no_known">
                                            No known allergies
                                        </option>
                                        <option value="not_assessed">
                                            Not yet assessed
                                        </option>
                                    </select>
                                    <Button size="sm" type="submit">
                                        Save
                                    </Button>
                                </div>
                            )}
                        </Form>
                    )}
                    {can('patientcare.branches.transfer') && (
                        <Form
                            action={`/admin/patients/${patient.id}/transfer`}
                            method="post"
                        >
                            {() => (
                                <div className="flex items-center gap-2 text-sm">
                                    <span className="text-muted-foreground">
                                        Transfer to:
                                    </span>
                                    <select
                                        name="to_branch_id"
                                        className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                    >
                                        {branches
                                            .filter(
                                                (b: { id: number }) =>
                                                    b.id !==
                                                    patient.primary_branch_id,
                                            )
                                            .map(
                                                (b: {
                                                    id: number;
                                                    name: string;
                                                }) => (
                                                    <option
                                                        key={b.id}
                                                        value={b.id}
                                                    >
                                                        {b.name}
                                                    </option>
                                                ),
                                            )}
                                    </select>
                                    <Input
                                        name="reason"
                                        placeholder="Reason"
                                        className="h-8 w-40"
                                    />
                                    <Button size="sm" type="submit">
                                        Transfer
                                    </Button>
                                </div>
                            )}
                        </Form>
                    )}
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Section title="Contact">
                        <Rows
                            rows={[
                                ['Phone', patient.phone],
                                ['Alt', patient.alt_phone],
                                ['Email', patient.email],
                                ['Region', patient.region],
                                ['District', patient.district],
                                ['Ward', patient.ward],
                                ['Address', patient.address],
                            ]}
                        />
                    </Section>
                    <Section title="Identification">
                        {patient.identifications.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                None recorded.
                            </p>
                        )}
                        <ul className="space-y-1 text-sm">
                            {patient.identifications.map((i: R) => (
                                <li key={i.id}>
                                    <Badge variant="secondary" className="mr-2">
                                        {i.type}
                                    </Badge>
                                    {i.number}
                                    {i.issuing_country
                                        ? ` · ${i.issuing_country}`
                                        : ''}
                                </li>
                            ))}
                        </ul>
                    </Section>
                    <Section title="Emergency contacts">
                        {patient.emergencyContacts.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                None.
                            </p>
                        )}
                        <ul className="space-y-1 text-sm">
                            {patient.emergencyContacts.map((c: R) => (
                                <li key={c.id}>
                                    {c.name}{' '}
                                    <span className="text-muted-foreground">
                                        {c.relationship} · {c.phone}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Section>
                    <Section title="Guardians / next of kin">
                        {patient.guardians.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                None.
                            </p>
                        )}
                        <ul className="space-y-1 text-sm">
                            {patient.guardians.map((c: R) => (
                                <li key={c.id}>
                                    {c.name}{' '}
                                    <span className="text-muted-foreground">
                                        {c.relationship} · {c.phone}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Section>
                </div>
                <Section
                    title="Medical safety summary"
                    action={
                        can('patientcare.patients.manage_medical_summary') && (
                            <Form
                                action={`/admin/patients/${patient.id}/medical-entries`}
                                method="post"
                            >
                                {() => (
                                    <div className="flex gap-2">
                                        <select
                                            name="type"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {[
                                                'allergy',
                                                'condition',
                                                'medication',
                                                'alert',
                                                'note',
                                            ].map((t) => (
                                                <option key={t} value={t}>
                                                    {t}
                                                </option>
                                            ))}
                                        </select>
                                        <Input
                                            name="description"
                                            placeholder="Description"
                                            className="w-48"
                                            required
                                        />
                                        <select
                                            name="source"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {[
                                                'patient_reported',
                                                'clinician',
                                                'document',
                                            ].map((s) => (
                                                <option key={s} value={s}>
                                                    {s.replace('_', ' ')}
                                                </option>
                                            ))}
                                        </select>
                                        <Button size="sm" type="submit">
                                            Add
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        )
                    }
                >
                    {patient.medical_entries.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No medical safety information recorded.
                        </p>
                    )}
                    <ul className="space-y-2 text-sm">
                        {patient.medical_entries.map((e: R) => (
                            <li
                                key={e.id}
                                className="flex items-center justify-between"
                            >
                                <span>
                                    <Badge
                                        variant={
                                            e.verified ? 'default' : 'secondary'
                                        }
                                        className="mr-2"
                                    >
                                        {e.type}
                                    </Badge>
                                    {e.description}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {e.verified
                                        ? 'verified'
                                        : e.source.replace('_', ' ')}{' '}
                                    · {e.recorded_by?.name}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Section>
                <Section
                    title="Documents"
                    action={
                        can('patientcare.patients.manage_documents') && (
                            <Form
                                action={`/admin/patients/${patient.id}/documents`}
                                method="post"
                                encType="multipart/form-data"
                            >
                                {() => (
                                    <div className="flex gap-2">
                                        <select
                                            name="category"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {[
                                                'referral',
                                                'identification',
                                                'medical_report',
                                                'consent',
                                                'other',
                                            ].map((c) => (
                                                <option key={c} value={c}>
                                                    {c.replace('_', ' ')}
                                                </option>
                                            ))}
                                        </select>
                                        <Input
                                            name="file"
                                            type="file"
                                            required
                                        />
                                        <Button size="sm" type="submit">
                                            Upload
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        )
                    }
                >
                    {patient.documents.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No documents.
                        </p>
                    )}
                    <ul className="space-y-1 text-sm">
                        {patient.documents.map((d: R) => (
                            <li key={d.id} className="flex justify-between">
                                <span>
                                    <Badge variant="secondary" className="mr-2">
                                        {d.category}
                                    </Badge>
                                    {d.original_name}
                                </span>
                                {can(
                                    'patientcare.patients.download_documents',
                                ) ? (
                                    <a
                                        href={`/admin/patients/${patient.id}/documents/${d.id}/download`}
                                        className="text-xs underline"
                                    >
                                        download
                                    </a>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </Section>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Section
                        title="Consents"
                        action={
                            can('patientcare.patients.manage_consent') && (
                                <Form
                                    action={`/admin/patients/${patient.id}/consents`}
                                    method="post"
                                >
                                    {() => (
                                        <div className="flex gap-2">
                                            <select
                                                name="type"
                                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                            >
                                                {[
                                                    'data_processing',
                                                    'information_sharing',
                                                    'photography',
                                                    'other',
                                                ].map((t) => (
                                                    <option key={t} value={t}>
                                                        {t.replace('_', ' ')}
                                                    </option>
                                                ))}
                                            </select>
                                            <select
                                                name="status"
                                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                            >
                                                <option value="granted">
                                                    granted
                                                </option>
                                                <option value="declined">
                                                    declined
                                                </option>
                                            </select>
                                            <Button size="sm" type="submit">
                                                Record
                                            </Button>
                                        </div>
                                    )}
                                </Form>
                            )
                        }
                    >
                        {patient.consents.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No consent records.
                            </p>
                        )}
                        <ul className="space-y-1 text-sm">
                            {patient.consents.map((c: R) => (
                                <li
                                    key={c.id}
                                    className="flex items-center justify-between"
                                >
                                    <span>
                                        {c.type.replace('_', ' ')}{' '}
                                        <Badge
                                            variant={
                                                c.status === 'granted'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {c.status}
                                        </Badge>
                                    </span>
                                    {c.status !== 'withdrawn' &&
                                        can(
                                            'patientcare.patients.manage_consent',
                                        ) && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    const reason =
                                                        prompt(
                                                            'Withdrawal reason',
                                                        );
                                                    if (reason)
                                                        router.post(
                                                            `/admin/patients/${patient.id}/consents/${c.id}/withdraw`,
                                                            {
                                                                withdrawal_reason:
                                                                    reason,
                                                            },
                                                        );
                                                }}
                                            >
                                                Withdraw
                                            </Button>
                                        )}
                                </li>
                            ))}
                        </ul>
                    </Section>
                    <Section title="Referrals">
                        {patient.referrals.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No referrals.
                            </p>
                        )}
                        <ul className="space-y-2 text-sm">
                            {patient.referrals.map((r: R) => (
                                <li
                                    key={r.id}
                                    className="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span>
                                        <span className="font-mono text-xs">
                                            {r.ref_number}
                                        </span>{' '}
                                        <Badge
                                            variant="secondary"
                                            className="mx-1"
                                        >
                                            {r.type}
                                        </Badge>
                                        <Badge>
                                            {r.status.replace('_', ' ')}
                                        </Badge>{' '}
                                        → {r.receiving_branch?.name ?? '—'}
                                    </span>
                                    {can('patientcare.referrals.review') && (
                                        <span className="flex gap-1">
                                            {[
                                                'received',
                                                'under_review',
                                                'accepted',
                                                'rejected',
                                                'completed',
                                            ].map((st) => (
                                                <Button
                                                    key={st}
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        let reason;
                                                        if (st === 'rejected') {
                                                            reason =
                                                                prompt(
                                                                    'Rejection reason',
                                                                );
                                                            if (!reason) return;
                                                        }
                                                        router.post(
                                                            `/admin/patients/referrals/${r.id}/transition`,
                                                            {
                                                                status: st,
                                                                reason,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    {st.replace('_', ' ')}
                                                </Button>
                                            ))}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Section>
                    <Section title="Branch history">
                        {patient.branch_history.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No branch associations.
                            </p>
                        )}
                        <ul className="space-y-1 text-sm">
                            {patient.branch_history.map((b: R) => (
                                <li key={b.id}>
                                    Branch #{b.branch_id} — {b.event}{' '}
                                    <span className="text-muted-foreground">
                                        {new Date(
                                            b.created_at,
                                        ).toLocaleDateString()}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Section>
                </div>
                <Section title="Activity">
                    {activity.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No recorded activity.
                        </p>
                    )}
                    <ul className="space-y-1 text-sm">
                        {activity.map((a) => (
                            <li key={a.id} className="flex justify-between">
                                <span>{a.action}</span>
                                <span className="text-muted-foreground">
                                    {new Date(a.created_at).toLocaleString()}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Section>
            </div>
        </>
    );
}
