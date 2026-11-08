import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

type I = {
    id: number;
    name: string;
    category: string;
    active: boolean;
    description?: string;
    contraindications?: string;
};

export default function Interventions({
    interventions,
    categories,
}: {
    interventions: { data: I[] };
    categories: string[];
}) {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const canManage = (auth?.permissions ?? []).includes(
        'patientcare.interventions.manage_catalogue',
    );
    return (
        <>
            <Head title="Intervention Catalogue" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Intervention Catalogue"
                    description="Configurable physiotherapy interventions"
                />
                {canManage && (
                    <Card>
                        <CardContent className="p-5">
                            <Form action="/admin/interventions" method="post">
                                {() => (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field>
                                            <FieldLabel>Name</FieldLabel>
                                            <Input name="name" required />
                                        </Field>
                                        <Field>
                                            <FieldLabel>Category</FieldLabel>
                                            <select
                                                name="category"
                                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                            >
                                                {categories.map((c) => (
                                                    <option key={c} value={c}>
                                                        {c.replace(/_/g, ' ')}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                        <Field>
                                            <FieldLabel>Description</FieldLabel>
                                            <Input name="description" />
                                        </Field>
                                        <Field>
                                            <FieldLabel>
                                                Contraindications
                                            </FieldLabel>
                                            <Input name="contraindications" />
                                        </Field>
                                        <Button
                                            type="submit"
                                            className="sm:col-span-2"
                                        >
                                            Add intervention
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Category</th>
                                    <th className="p-3">Contraindications</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {interventions.data.map((i) => (
                                    <tr
                                        key={i.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-medium">
                                            {i.name}
                                        </td>
                                        <td className="p-3">
                                            {i.category.replace(/_/g, ' ')}
                                        </td>
                                        <td className="p-3 text-xs">
                                            {i.contraindications}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    i.active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {i.active
                                                    ? 'active'
                                                    : 'inactive'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {interventions.data.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                Catalogue empty — add interventions to use in
                                plans.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
