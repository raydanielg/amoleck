import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type E = {
    id: number;
    code: string;
    name: string;
    category: string;
    active: boolean;
    difficulty: string;
};

export default function Exercises({
    exercises,
    categories,
}: {
    exercises: { data: E[] };
    categories: string[];
}) {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const canManage = (auth?.permissions ?? []).includes(
        'patientcare.clinical.exercises.library.manage',
    );
    return (
        <>
            <Head title="Exercise Library" />
            <div className="space-y-6 p-6">
                <Heading
                    title="Exercise Library"
                    description="Configurable physiotherapy exercises"
                />
                {canManage && (
                    <Card>
                        <CardContent className="p-5">
                            <Form action="/admin/exercises" method="post">
                                {() => (
                                    <div className="grid gap-3 sm:grid-cols-3">
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
                                    <th className="p-3">Level</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {exercises.data.map((e) => (
                                    <tr
                                        key={e.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-mono text-xs">
                                            {e.code}
                                        </td>
                                        <td className="p-3 font-medium">
                                            {e.name}
                                        </td>
                                        <td className="p-3">
                                            {e.category.replace(/_/g, ' ')}
                                        </td>
                                        <td className="p-3">{e.difficulty}</td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    e.active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {e.active
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
