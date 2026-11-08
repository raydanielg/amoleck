import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type R = Record<string, any>;
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

export default function ReassessmentShow({
    reassessment,
    goals,
    recommendations,
    goalStatuses,
    versions,
}: {
    reassessment: R;
    goals: R[];
    recommendations: string[];
    goalStatuses: string[];
    versions: R[];
}) {
    const r = reassessment;
    const readOnly = ['finalized', 'amended', 'cancelled'].includes(r.status);
    const post = (status: string, extra = {}) =>
        router.post(`/admin/reassessments/${r.id}/transition`, {
            status,
            ...extra,
        });
    const ta = (name: string, label: string) => (
        <div key={name}>
            <label className="text-xs text-muted-foreground">{label}</label>
            <textarea
                name={`reassessment[${name}]`}
                defaultValue={r[name] ?? ''}
                disabled={readOnly}
                rows={3}
                className="mt-1 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
            />
        </div>
    );

    return (
        <>
            <Head title={r.reassessment_number} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={r.reassessment_number}
                        description={`${r.patient.first_name} ${r.patient.last_name} · ${r.patient.mrn} · ${r.status}`}
                    />
                    <Badge
                        variant={
                            r.status === 'finalized' ? 'default' : 'secondary'
                        }
                    >
                        {r.status.replace('_', ' ')}
                    </Badge>
                </div>
                <Form
                    action={`/admin/reassessments/${r.id}`}
                    method="post"
                    transform={(d) => ({ ...d, _method: 'put' })}
                >
                    {({ processing }) => (
                        <div className="space-y-6">
                            <Sec title="Subjective">
                                {ta(
                                    'subjective',
                                    'Current complaints / changes',
                                )}
                            </Sec>
                            <Sec title="Objective">
                                {ta(
                                    'objective',
                                    'ROM / strength / functional findings',
                                )}
                            </Sec>
                            <Sec title="Goal review">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="p-2">Goal</th>
                                            <th className="p-2">
                                                Baseline→Target
                                            </th>
                                            <th className="p-2">Status</th>
                                            <th className="p-2">Evidence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {goals.map((g: R, i: number) => (
                                            <tr key={g.id} className="border-b">
                                                <td className="p-2">
                                                    {g.description}
                                                    <input
                                                        type="hidden"
                                                        name={`goal_reviews[${i}][clinical_treatment_goal_id]`}
                                                        value={g.id}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    {g.baseline}→{g.target}
                                                </td>
                                                <td className="p-2">
                                                    <select
                                                        name={`goal_reviews[${i}][status]`}
                                                        defaultValue={g.status}
                                                        disabled={readOnly}
                                                        className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                                    >
                                                        {goalStatuses.map(
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
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        name={`goal_reviews[${i}][evidence]`}
                                                        disabled={readOnly}
                                                        className="h-8"
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </Sec>
                            <Sec title="Interpretation & recommendation">
                                {ta(
                                    'interpretation',
                                    'Clinical interpretation',
                                )}
                                <div className="mt-3 flex flex-wrap gap-3">
                                    <div className="min-w-[200px]">
                                        <label className="text-xs text-muted-foreground">
                                            Recommendation
                                        </label>
                                        <select
                                            name="reassessment[recommendation]"
                                            defaultValue={
                                                r.recommendation ?? ''
                                            }
                                            disabled={readOnly}
                                            className="mt-1 h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm"
                                        >
                                            <option value="">—</option>
                                            {recommendations.map((o) => (
                                                <option key={o} value={o}>
                                                    {o.replace(/_/g, ' ')}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex-1">
                                        {ta('recommendation_reason', 'Reason')}
                                    </div>
                                </div>
                            </Sec>
                            {!readOnly && (
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            )}
                        </div>
                    )}
                </Form>
                <div className="flex gap-2 border-t pt-4">
                    {r.status === 'draft' && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => post('pending_review')}
                        >
                            Submit
                        </Button>
                    )}
                    {r.status === 'pending_review' && (
                        <Button
                            size="sm"
                            onClick={() => {
                                if (confirm('Finalize?')) post('finalized');
                            }}
                        >
                            Finalize
                        </Button>
                    )}
                    {r.status === 'finalized' && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                const x = prompt('Amend reason');
                                if (x) post('amended', { reason: x });
                            }}
                        >
                            Amend
                        </Button>
                    )}
                </div>
                {versions.length > 0 && (
                    <Sec title="Versions">
                        <ul className="space-y-1 text-sm">
                            {versions.map((v: R) => (
                                <li key={v.id}>
                                    v{v.version} — {v.action} ·{' '}
                                    {new Date(v.created_at).toLocaleString()}
                                    {v.reason ? ` · ${v.reason}` : ''}
                                </li>
                            ))}
                        </ul>
                    </Sec>
                )}
            </div>
        </>
    );
}
