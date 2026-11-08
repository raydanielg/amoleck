import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';

type R = Record<string, any>;
export default function QueueDisplay({
    entries,
    branch,
}: {
    entries: R[];
    branch: R;
}) {
    const called = entries.filter((e) => e.status === 'called');
    const waiting = entries.filter((e) => e.status === 'waiting');
    return (
        <>
            <Head title={`Queue — ${branch?.name ?? ''}`} />
            <div className="min-h-screen bg-slate-950 p-8 text-white">
                <h1 className="mb-8 text-3xl font-bold">
                    {branch?.name} — Now Serving
                </h1>
                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <h2 className="mb-4 text-xl text-emerald-400">
                            Called ({called.length})
                        </h2>
                        <ul className="space-y-3">
                            {called.map((e) => (
                                <li
                                    key={e.id}
                                    className="flex items-center justify-between rounded-lg bg-emerald-900/30 p-4"
                                >
                                    <span className="font-mono text-2xl font-bold">
                                        {e.ticket_number}
                                    </span>
                                    <Badge>{e.queue_type?.name}</Badge>
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <h2 className="mb-4 text-xl text-slate-400">
                            Waiting ({waiting.length})
                        </h2>
                        <ul className="space-y-2">
                            {waiting.map((e) => (
                                <li
                                    key={e.id}
                                    className="flex items-center justify-between rounded-lg bg-slate-800/50 p-3"
                                >
                                    <span className="font-mono text-lg">
                                        {e.ticket_number}
                                    </span>
                                    <span className="text-sm text-slate-400">
                                        {e.queue_type?.name}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
                <p className="mt-8 text-center text-sm text-slate-500">
                    Refresh: every 15s — patient details protected
                </p>
            </div>
        </>
    );
}
