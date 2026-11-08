import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type R = Record<string, any>;
export default function QueueForm({
    patients,
    branches,
    queueTypes,
    priorities,
    appointments,
}: {
    patients: R[];
    branches: R[];
    queueTypes: R[];
    priorities: string[];
    appointments: R[];
}) {
    const sel = (
        n: string,
        l: string,
        opts: R[],
        show: (o: R) => string,
        req = false,
    ) => (
        <Field>
            <FieldLabel>{l}</FieldLabel>
            <select
                name={n}
                required={req}
                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
            >
                <option value="">—</option>
                {opts.map((o) => (
                    <option key={o.id} value={o.id}>
                        {show(o)}
                    </option>
                ))}
            </select>
        </Field>
    );
    return (
        <>
            <Head title="New visit" />
            <div className="mx-auto max-w-xl space-y-6 p-6">
                <Heading
                    title="Patient arrival"
                    description="Check in scheduled or walk-in"
                />
                <Card>
                    <CardContent className="p-6">
                        <Form action="/admin/queue/visits" method="post">
                            {({ processing }) => (
                                <FieldGroup>
                                    {sel(
                                        'patient_id',
                                        'Patient',
                                        patients,
                                        (p) =>
                                            `${p.mrn} · ${p.first_name} ${p.last_name}`,
                                        true,
                                    )}
                                    {sel(
                                        'branch_id',
                                        'Branch',
                                        branches,
                                        (b) => b.name,
                                        true,
                                    )}
                                    {sel(
                                        'queue_type_id',
                                        'Queue',
                                        queueTypes,
                                        (q) => q.name,
                                        true,
                                    )}
                                    <Field>
                                        <FieldLabel>Source</FieldLabel>
                                        <select
                                            name="source"
                                            required
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {[
                                                'walk_in',
                                                'appointment',
                                                'internal_referral',
                                                'external_referral',
                                                'follow_up',
                                            ].map((s) => (
                                                <option key={s} value={s}>
                                                    {s.replace(/_/g, ' ')}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    {sel(
                                        'appointment_id',
                                        'Appointment (today)',
                                        appointments,
                                        (a) =>
                                            `${a.appointment_number} · ${a.patient?.first_name} ${a.patient?.last_name}`,
                                    )}
                                    <Field>
                                        <FieldLabel>Priority</FieldLabel>
                                        <select
                                            name="priority"
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {priorities.map((p) => (
                                                <option key={p} value={p}>
                                                    {p}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field>
                                        <FieldLabel>Reason</FieldLabel>
                                        <Input name="reason" />
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Create visit
                                    </Button>
                                </FieldGroup>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
