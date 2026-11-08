import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
export default function Ipd({
    census,
    admissions,
    beds,
    branches,
    patients,
    types,
    sources,
    dischargeTypes,
}: {
    census: R;
    admissions: { data: R[] };
    beds: R[];
    branches: R[];
    patients: R[];
    types: string[];
    sources: string[];
    dischargeTypes: string[];
}) {
    const sel = (
        n: string,
        l: string,
        opts: R[],
        show: (o: R) => string,
        req = true,
    ) => (
        <div className="min-w-[120px] flex-1">
            <label className="text-xs text-muted-foreground">{l}</label>
            <select
                name={n}
                required={req}
                className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm"
            >
                {!req && <option value="">—</option>}
                {opts.map((o) => (
                    <option key={o.id} value={o.id}>
                        {show(o)}
                    </option>
                ))}
            </select>
        </div>
    );
    return (
        <>
            <Head title="Inpatient" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Inpatient Management"
                    description="Admissions, wards & beds"
                />
                <div className="grid gap-4 sm:grid-cols-7">
                    {Object.entries(census).map(([k, v]) => (
                        <Card key={k}>
                            <CardContent className="p-3 text-center">
                                <p className="text-xs text-muted-foreground capitalize">
                                    {k.replace('_', ' ')}
                                </p>
                                <p className="text-xl font-semibold">{v}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">New admission</h2>
                        <Form action="/admin/ipd/admissions" method="post">
                            {() => (
                                <div className="flex flex-wrap gap-3">
                                    {sel(
                                        'patient_id',
                                        'Patient',
                                        patients,
                                        (p) => `${p.mrn} ${p.first_name}`,
                                    )}
                                    {sel(
                                        'branch_id',
                                        'Branch',
                                        branches,
                                        (b) => b.name,
                                    )}
                                    <div className="min-w-[110px] flex-1">
                                        <label className="text-xs text-muted-foreground">
                                            Type
                                        </label>
                                        <select
                                            name="type"
                                            className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm"
                                        >
                                            {types.map((t) => (
                                                <option key={t} value={t}>
                                                    {t.replace(/_/g, ' ')}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="min-w-[110px] flex-1">
                                        <label className="text-xs text-muted-foreground">
                                            Source
                                        </label>
                                        <select
                                            name="source"
                                            className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm"
                                        >
                                            {sources.map((s) => (
                                                <option key={s} value={s}>
                                                    {s.replace(/_/g, ' ')}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="min-w-[160px] flex-1">
                                        <label className="text-xs text-muted-foreground">
                                            Reason
                                        </label>
                                        <Input
                                            name="reason"
                                            className="mt-1 h-8"
                                        />
                                    </div>
                                    <div className="flex items-end">
                                        <Button type="submit" size="sm">
                                            Admit
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Admission</th>
                                    <th className="p-3">Patient</th>
                                    <th className="p-3">Ward/Bed</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {admissions.data.map((a) => (
                                    <tr
                                        key={a.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {a.admission_number}
                                        </td>
                                        <td className="p-3">
                                            {a.patient.first_name}{' '}
                                            {a.patient.last_name}
                                        </td>
                                        <td className="p-3">
                                            {a.ward?.name ?? '—'} /{' '}
                                            {a.bed?.code ?? '—'}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    a.status === 'admitted'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {a.status.replace(/_/g, ' ')}
                                            </Badge>
                                        </td>
                                        <td className="space-x-1 p-3">
                                            {a.status === 'approved' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/ipd/admissions/${a.id}/transition`,
                                                            {
                                                                status: 'awaiting_bed',
                                                            },
                                                        )
                                                    }
                                                >
                                                    Await bed
                                                </Button>
                                            )}
                                            {a.status === 'awaiting_bed' && (
                                                <Button
                                                    size="sm"
                                                    onClick={() => {
                                                        const b =
                                                            prompt('Bed ID');
                                                        if (b)
                                                            router.post(
                                                                `/admin/ipd/admissions/${a.id}/allocate`,
                                                                { bed_id: b },
                                                            );
                                                    }}
                                                >
                                                    Allocate
                                                </Button>
                                            )}
                                            {a.status === 'admitted' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        const t = prompt(
                                                            'Discharge type: ' +
                                                                dischargeTypes.join(
                                                                    '/',
                                                                ),
                                                        );
                                                        if (t)
                                                            router.post(
                                                                `/admin/ipd/admissions/${a.id}/discharge`,
                                                                { type: t },
                                                            );
                                                    }}
                                                >
                                                    Discharge
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {admissions.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No admissions.
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 font-medium">
                            Beds ({beds.length})
                        </h2>
                        <div className="grid gap-2 sm:grid-cols-6">
                            {beds.map((b) => (
                                <div
                                    key={b.id}
                                    className={`rounded-md border p-3 text-center text-xs ${b.status === 'available' ? 'border-emerald-500/40' : b.status === 'occupied' ? 'border-destructive/40' : 'border-muted'}`}
                                >
                                    <p className="font-mono font-medium">
                                        {b.code}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {b.status}
                                    </p>
                                    {b.status === 'cleaning' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="mt-1"
                                            onClick={() =>
                                                router.post(
                                                    `/admin/ipd/beds/${b.id}/clean`,
                                                )
                                            }
                                        >
                                            Clean done
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
