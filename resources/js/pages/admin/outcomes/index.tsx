import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type M = {
    id: number;
    code: string;
    name: string;
    category: string;
    scoring_format: string;
    active: boolean;
};

export default function OutcomeMeasures({
    measures,
    categories,
}: {
    measures: { data: M[] };
    categories: string[];
}) {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const canManage = (auth?.permissions ?? []).includes(
        'patientcare.clinical.outcomes.manage_library',
    );
    return (
        <>
            <Head title="Outcome Measures" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Outcome Measures"
                    description="Configurable clinical outcome instruments"
                />
                {canManage && (
                    <Card>
                        <CardContent className="p-5">
                            <Form
                                action="/admin/outcome-measures"
                                method="post"
                            >
                                {() => (
                                    <div className="grid gap-3 sm:grid-cols-4">
                                        <Input
                                            name="name"
                                            placeholder="Name"
                                            required
                                        />
                                        <select
                                            name="category"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        >
                                            {categories.map((c) => (
                                                <option key={c} value={c}>
                                                    {c.replace(/_/g, ' ')}
                                                </option>
                                            ))}
                                        </select>
                                        <Input
                                            name="supported_units"
                                            placeholder='Units JSON e.g. ["deg","cm"]'
                                        />
                                        <Button type="submit">Add</Button>
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
                                    <th className="p-3">Code</th>
                                    <th className="p-3">Name</th>
                                    <th className="p-3">Category</th>
                                    <th className="p-3">Format</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {measures.data.map((m) => (
                                    <tr
                                        key={m.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {m.code}
                                        </td>
                                        <td className="p-3 font-medium">
                                            {m.name}
                                        </td>
                                        <td className="p-3">
                                            {m.category.replace(/_/g, ' ')}
                                        </td>
                                        <td className="p-3">
                                            {m.scoring_format}
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    m.active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {m.active
                                                    ? 'active'
                                                    : 'inactive'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
