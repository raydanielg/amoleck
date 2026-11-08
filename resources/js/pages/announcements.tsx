import { Head, Link, usePage } from '@inertiajs/react';
import { Megaphone01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import AppLogoIcon from '@/components/app-logo-icon';
import TextLink from '@/components/text-link';
import { Card, CardContent } from '@/components/ui/card';
import { home, login } from '@/routes';

type Announcement = {
    id: number;
    title: string;
    body: string;
    created_at_diff: string;
};

export default function Announcements({
    announcements,
}: {
    announcements: Announcement[];
}) {
    const { name } = usePage().props;

    return (
        <>
            <Head title="Announcements" />

            <div className="flex min-h-screen flex-col bg-muted">
                <header className="border-b bg-background">
                    <div className="mx-auto flex w-full max-w-3xl items-center justify-between px-6 py-4">
                        <Link
                            href={home()}
                            className="flex items-center gap-2 font-medium"
                        >
                            <AppLogoIcon className="size-8 object-contain" />
                            <span>{name}</span>
                        </Link>
                        <TextLink href={login()} className="text-sm">
                            Staff login
                        </TextLink>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-3xl flex-1 px-6 py-10">
                    <div className="mb-6 flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <HugeiconsIcon
                                icon={Megaphone01Icon}
                                strokeWidth={2}
                                className="size-5"
                            />
                        </div>
                        <div>
                            <h1 className="text-xl font-semibold">
                                Announcements
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Latest updates for all staff
                            </p>
                        </div>
                    </div>

                    {announcements.length === 0 ? (
                        <Card>
                            <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                No announcements yet. Check back later.
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {announcements.map((announcement) => (
                                <Card key={announcement.id}>
                                    <CardContent className="p-5">
                                        <div className="flex items-start justify-between gap-4">
                                            <h2 className="font-medium">
                                                {announcement.title}
                                            </h2>
                                            <span className="shrink-0 text-xs text-muted-foreground">
                                                {announcement.created_at_diff}
                                            </span>
                                        </div>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {announcement.body}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
