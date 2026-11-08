import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type R = Record<string, any>;
export default function AppointmentForm({
    patients,
    branches,
    types,
    resources,
    clinicians,
}: {
    patients: R[];
    branches: R[];
    types: R[];
    resources: R[];
    clinicians: R[];
}) {
    const sel = (
        name: string,
        label: string,
        opts: R[],
        show: (o: R) => string,
        req = false,
    ) => (
        <Field>
            <FieldLabel>{label}</FieldLabel>
            <select
                name={name}
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
            <Head title="Book appointment" />
            <div className="mx-auto max-w-xl space-y-6 p-6">
                <Heading
                    title="Book appointment"
                    description="Staff booking — patient identity from PatientCare"
                />
                <Card>
                    <CardContent className="p-6">
                        <Form action="/admin/appointments" method="post">
                            {({ processing, errors }) => (
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
                                        'appointment_type_id',
                                        'Type',
                                        types,
                                        (t) => t.name,
                                    )}
                                    {sel(
                                        'clinician_id',
                                        'Clinician',
                                        clinicians,
                                        (c) => c.name,
                                    )}
                                    {sel(
                                        'hospital_resource_id',
                                        'Room/Resource',
                                        resources,
                                        (r) => r.name,
                                    )}
                                    <div className="grid grid-cols-3 gap-4">
                                        <Field>
                                            <FieldLabel>Date</FieldLabel>
                                            <Input
                                                name="appointment_date"
                                                type="date"
                                                required
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel>Start</FieldLabel>
                                            <Input
                                                name="start_time"
                                                type="time"
                                                required
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel>Mins</FieldLabel>
                                            <Input
                                                name="duration"
                                                type="number"
                                                defaultValue="30"
                                            />
                                        </Field>
                                    </div>
                                    <Field>
                                        <FieldLabel>Reason</FieldLabel>
                                        <Input name="reason" />
                                    </Field>
                                    {errors.appointment_date && (
                                        <p className="text-sm text-destructive">
                                            {errors.appointment_date}
                                        </p>
                                    )}
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />} Book
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
