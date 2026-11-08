import { InboxUnreadIcon, Megaphone01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import TextLink from '@/components/text-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { announcements as announcementsRoute } from '@/routes';

export type AnnouncementItem = {
    id: number;
    title: string;
    body: string;
    created_at_diff: string;
};

export default function AnnouncementsButton({
    announcements,
}: {
    announcements: AnnouncementItem[];
}) {
    const count = announcements.length;

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="relative gap-2"
                    aria-label={`Inbox (${count} unread)`}
                >
                    <HugeiconsIcon
                        icon={InboxUnreadIcon}
                        strokeWidth={2}
                        className="size-4"
                        aria-hidden="true"
                    />
                    Inbox
                    {count > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -top-1.5 -right-2 rounded-full px-1.5"
                            aria-hidden="true"
                        >
                            {count}
                        </Badge>
                    )}
                </Button>
            </SheetTrigger>
            <SheetContent
                side="right"
                className="w-full gap-0 border-l border-primary/30 p-0 shadow-[0_0_40px_-10px] shadow-primary/25 sm:max-w-md"
            >
                <SheetHeader className="border-b border-primary/20 bg-primary/5 p-6">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary shadow-[0_0_16px_-2px] shadow-primary/40">
                            <HugeiconsIcon
                                icon={Megaphone01Icon}
                                strokeWidth={2}
                                className="size-5"
                            />
                        </div>
                        <div className="flex-1">
                            <SheetTitle className="flex items-center gap-2">
                                Announcements
                                {count > 0 && (
                                    <Badge
                                        variant="destructive"
                                        className="rounded-full px-1.5"
                                    >
                                        {count}
                                    </Badge>
                                )}
                            </SheetTitle>
                            <SheetDescription>
                                Latest updates for all staff
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto p-6">
                    {count === 0 ? (
                        <div className="flex h-full flex-col items-center justify-center gap-2 text-center text-sm text-muted-foreground">
                            <HugeiconsIcon
                                icon={InboxUnreadIcon}
                                strokeWidth={2}
                                className="size-8 text-muted-foreground/50"
                            />
                            <p>No announcements yet. Check back later.</p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {announcements.map((announcement) => (
                                <div
                                    key={announcement.id}
                                    className="rounded-xl border border-primary/25 bg-primary/5 p-4 shadow-[0_0_20px_-6px] shadow-primary/30 transition-shadow hover:shadow-[0_0_28px_-4px] hover:shadow-primary/40"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <h3 className="font-medium">
                                            {announcement.title}
                                        </h3>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {announcement.created_at_diff}
                                        </span>
                                    </div>
                                    <p className="mt-1.5 text-sm text-muted-foreground">
                                        {announcement.body}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="border-t border-primary/20 p-4 text-center">
                    <TextLink href={announcementsRoute()} className="text-sm">
                        View all announcements
                    </TextLink>
                </div>
            </SheetContent>
        </Sheet>
    );
}
