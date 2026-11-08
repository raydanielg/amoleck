import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type Setting = {
    key: string;
    label: string;
    type: string;
    options?: Record<string, string> | null;
    scope: string;
    readonly: boolean;
    restart: boolean;
    perm: string;
    value: string | number | boolean | string[] | null;
    has_value: boolean;
};
type Category = { category: string; settings: Setting[] };

export default function Settings({
    categories,
    branches,
    selectedBranch,
    permissions,
}: {
    categories: Category[];
    branches: { id: number; name: string }[];
    selectedBranch: number | null;
    permissions: Record<string, boolean>;
}) {
    const [search, setSearch] = useState('');
    const url = new URL(window.location.href);
    const activeCategory =
        url.searchParams.get('category') ?? categories[0]?.category;
    const branchParam = selectedBranch ? `&branch_id=${selectedBranch}` : '';

    const filtered = categories
        .map((c) => ({
            ...c,
            settings: c.settings.filter(
                (s) =>
                    s.label.toLowerCase().includes(search.toLowerCase()) ||
                    s.key.includes(search.toLowerCase()),
            ),
        }))
        .filter((c) => c.settings.length > 0);
    const current =
        filtered.find((c) => c.category === activeCategory) ?? filtered[0];

    const setBranch = (id: string) =>
        router.get(
            `/admin/settings?category=${activeCategory}${id ? `&branch_id=${id}` : ''}`,
        );

    const field = (s: Setting) => {
        const name = s.scope === 'branch' && selectedBranch ? s.key : s.key;
        const val = s.value as string | number | boolean | string[] | null;
        if (s.readonly)
            return (
                <p className="text-sm text-muted-foreground">
                    {String(val ?? '—')}{' '}
                    <Badge variant="secondary" className="ml-2">
                        deployment-managed
                    </Badge>
                </p>
            );
        if (s.type === 'select' && s.options)
            return (
                <select
                    name={name}
                    defaultValue={String(val ?? '')}
                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    {Object.entries(s.options).map(([v, l]) => (
                        <option key={v} value={v}>
                            {l}
                        </option>
                    ))}
                </select>
            );
        if (s.type === 'bool')
            return (
                <label className="flex items-center gap-2 text-sm">
                    <input type="hidden" name={name} value="0" />
                    <Checkbox
                        name={name}
                        value="1"
                        defaultChecked={!!val}
                    />{' '}
                    Enabled
                </label>
            );
        if (s.type === 'secret')
            return (
                <div className="flex items-center gap-2">
                    <Input
                        name={name}
                        type="password"
                        placeholder={
                            s.has_value
                                ? '•••••••• (stored — type to replace)'
                                : 'Not set'
                        }
                        autoComplete="new-password"
                    />
                    {s.has_value && (
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            onClick={() =>
                                router.delete(`/admin/settings/secret`, {
                                    data: {
                                        key: s.key,
                                        branch_id: selectedBranch,
                                    },
                                })
                            }
                        >
                            Remove
                        </Button>
                    )}
                </div>
            );
        if (s.type === 'int')
            return (
                <Input
                    name={name}
                    type="number"
                    defaultValue={Number(val ?? 0)}
                />
            );
        if (s.type === 'json')
            return (
                <Input
                    name={name}
                    defaultValue={JSON.stringify(val)}
                    placeholder='["en","sw"]'
                />
            );
        if (s.type === 'text')
            return (
                <textarea
                    name={name}
                    defaultValue={String(val ?? '')}
                    className="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                />
            );
        return <Input name={name} defaultValue={String(val ?? '')} />;
    };

    return (
        <>
            <Head title="System Settings" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="System Settings"
                        description="Hospital-wide and branch-level configuration"
                    />
                    <Link
                        href="/admin/settings/history"
                        className="text-sm underline"
                    >
                        History
                    </Link>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search settings..."
                        className="max-w-xs"
                    />
                    <select
                        value={selectedBranch ?? ''}
                        onChange={(e) => setBranch(e.target.value)}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Hospital-wide</option>
                        {branches.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name} override
                            </option>
                        ))}
                    </select>
                    {selectedBranch && (
                        <Badge variant="secondary">Branch override mode</Badge>
                    )}
                </div>
                <div className="grid gap-6 lg:grid-cols-[200px_1fr]">
                    <nav className="flex flex-row gap-1 overflow-x-auto lg:flex-col">
                        {filtered.map((c) => (
                            <Link
                                key={c.category}
                                href={`/admin/settings?category=${c.category}${branchParam}`}
                                className={`rounded-md px-3 py-2 text-sm capitalize ${c.category === current?.category ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                            >
                                {c.category}
                            </Link>
                        ))}
                    </nav>
                    <Card>
                        <CardContent className="p-6">
                            {!current && (
                                <p className="text-sm text-muted-foreground">
                                    No settings match your search.
                                </p>
                            )}
                            {current && (
                                <Form
                                    action={`/admin/settings${selectedBranch ? `?branch_id=${selectedBranch}` : ''}`}
                                    method="post"
                                    transform={(d) => ({
                                        ...d,
                                        _method: 'put',
                                    })}
                                >
                                    {({ processing, errors }) => (
                                        <FieldGroup>
                                            <h2 className="font-medium capitalize">
                                                {current.category.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </h2>
                                            {current.settings.map((s) => (
                                                <Field key={s.key}>
                                                    <FieldLabel>
                                                        {s.label}{' '}
                                                        <span className="font-mono text-xs text-muted-foreground">
                                                            {s.key}
                                                        </span>
                                                        {s.scope ===
                                                            'branch' && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="ml-1"
                                                            >
                                                                branch-scoped
                                                            </Badge>
                                                        )}
                                                    </FieldLabel>
                                                    {permissions[s.perm] ===
                                                        false || s.readonly ? (
                                                        <p className="text-sm text-muted-foreground">
                                                            {typeof s.value ===
                                                            'object'
                                                                ? JSON.stringify(
                                                                      s.value,
                                                                  )
                                                                : String(
                                                                      s.value ??
                                                                          '—',
                                                                  )}
                                                        </p>
                                                    ) : (
                                                        field(s)
                                                    )}
                                                    <InputError
                                                        message={errors[s.key]}
                                                    />
                                                </Field>
                                            ))}
                                            <div className="flex gap-2">
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    {processing && <Spinner />}{' '}
                                                    Save {current.category}
                                                </Button>
                                            </div>
                                        </FieldGroup>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
