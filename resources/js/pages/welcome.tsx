import { Head, Link, usePage } from '@inertiajs/react';
import AnnouncementsButton, {
    type AnnouncementItem,
} from '@/components/announcements-button';
import { cn } from '@/lib/utils';
import { dashboard, login } from '@/routes';

const services = ['Physiotherapy', 'Rehabilitation', 'Wellness'];

export default function Welcome({
    announcements,
}: {
    announcements: AnnouncementItem[];
}) {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8">
                <header className="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
                    <nav className="flex items-center justify-end gap-4">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a]"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <AnnouncementsButton
                                announcements={announcements}
                            />
                        )}
                    </nav>
                </header>
                <div className="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
                    <main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">
                        <div className="flex-1 rounded-br-lg rounded-bl-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-tl-lg lg:rounded-br-none lg:p-20">
                            <img
                                src="/logo.png"
                                alt={name}
                                className="mb-8 h-10 w-auto"
                            />
                            <h1 className="mb-1 text-base font-medium">
                                Welcome to {name}
                            </h1>
                            <p className="mb-6 text-[#706f6c]">
                                Your partner in movement, recovery and better
                                living.
                            </p>
                            <ul className="mb-8 flex flex-col">
                                {services.map((service, index) => (
                                    <li
                                        key={service}
                                        className={cn(
                                            'relative flex items-center gap-4 py-2 before:absolute before:left-[0.4rem] before:border-l before:border-[#e3e3e0]',
                                            index === 0 &&
                                                'before:top-1/2 before:bottom-0',
                                            index === services.length - 1 &&
                                                'before:top-0 before:bottom-1/2',
                                            index > 0 &&
                                                index < services.length - 1 &&
                                                'before:top-0 before:bottom-0',
                                        )}
                                    >
                                        <span className="relative bg-white py-1">
                                            <span className="flex h-3.5 w-3.5 items-center justify-center rounded-full border border-[#e3e3e0] bg-[#FDFDFC] shadow-[0px_0px_1px_0px_rgba(0,0,0,0.03),0px_1px_2px_0px_rgba(0,0,0,0.06)]">
                                                <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                                            </span>
                                        </span>
                                        <span>{service}</span>
                                    </li>
                                ))}
                            </ul>
                            <Link
                                href={login()}
                                className="inline-block rounded-sm bg-primary px-5 py-1.5 text-sm leading-normal font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                Staff login
                            </Link>
                        </div>
                        <div className="relative -mb-px aspect-[335/364] w-full shrink-0 overflow-hidden rounded-t-lg bg-muted lg:mb-0 lg:-ml-px lg:aspect-auto lg:w-[438px] lg:rounded-t-none lg:rounded-r-lg">
                            <img
                                src="/welcome-banner.jpg"
                                alt={name}
                                className="absolute inset-0 h-full w-full object-cover"
                            />
                        </div>
                    </main>
                </div>
                <div className="hidden h-14.5 lg:block" />
            </div>
        </>
    );
}
