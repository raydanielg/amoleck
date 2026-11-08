import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Boxes,
    Building2,
    FileClock,
    FolderGit2,
    GitBranch,
    LayoutGrid,
    Settings,
    ShieldCheck,
    Users,
    Activity,
    Share2,
    Stethoscope,
    Banknote,
    Pill,
    BedDouble,
    CalendarDays,
    ClipboardList,
    Dumbbell,
    UserRound,
    KeyRound,
    Scissors,
    CalendarClock,
    BedSingle,
    BarChart3,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const navGroups: { label: string; items: (NavItem & { perm: string })[] }[] = [
    {
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: '/admin/dashboard',
                icon: LayoutGrid,
                perm: 'dashboard.view',
            },
        ],
    },
    {
        label: 'Hospital',
        items: [
            {
                title: 'Hospital Profile',
                href: '/admin/hospital',
                icon: Building2,
                perm: 'hospital.view',
            },
            {
                title: 'Branches',
                href: '/admin/branches',
                icon: GitBranch,
                perm: 'branches.view',
            },
        ],
    },
    {
        label: 'Patient Care',
        items: [
            {
                title: 'Patient Dashboard',
                href: '/admin/patients/dashboard',
                icon: Activity,
                perm: 'patientcare.dashboard.view',
            },
            {
                title: 'Patients',
                href: '/admin/patients',
                icon: UserRound,
                perm: 'patientcare.patients.view',
            },
            {
                title: 'Referrals',
                href: '/admin/referrals',
                icon: Share2,
                perm: 'patientcare.referrals.view',
            },
            {
                title: 'Clinical Dashboard',
                href: '/admin/clinical/dashboard',
                icon: Stethoscope,
                perm: 'patientcare.clinical.dashboard.view',
            },
            {
                title: 'Encounters',
                href: '/admin/clinical/encounters',
                icon: Stethoscope,
                perm: 'patientcare.clinical.encounters.view',
            },
            {
                title: 'Treatment Plans',
                href: '/admin/treatment-plans',
                icon: ClipboardList,
                perm: 'patientcare.treatment_plans.view',
            },
            {
                title: 'Insurance',
                href: '/admin/insurance',
                icon: ShieldCheck,
                perm: 'finance.insurance.view',
            },
            {
                title: 'Finance',
                href: '/admin/finance',
                icon: Banknote,
                perm: 'finance.dashboard.view',
            },
            {
                title: 'eMAR',
                href: '/admin/medications',
                icon: Pill,
                perm: 'medications.view',
            },
            {
                title: 'Inpatient',
                href: '/admin/ipd',
                icon: BedDouble,
                perm: 'ipd.view',
            },
            {
                title: 'Queue',
                href: '/admin/queue',
                icon: Users,
                perm: 'queue.view',
            },
            {
                title: 'Departments',
                href: '/admin/departments',
                icon: Building2,
                perm: 'departments.view',
            },
            {
                title: 'Services',
                href: '/admin/services',
                icon: ClipboardList,
                perm: 'services.view',
            },
            {
                title: 'Appointments',
                href: '/admin/appointments',
                icon: CalendarDays,
                perm: 'appointments.view',
            },
            {
                title: 'Exercise Library',
                href: '/admin/exercises',
                icon: Dumbbell,
                perm: 'patientcare.clinical.exercises.library.view',
            },
            {
                title: 'Sessions',
                href: '/admin/sessions',
                icon: Activity,
                perm: 'patientcare.clinical.sessions.view',
            },
            {
                title: 'Interventions',
                href: '/admin/interventions',
                icon: Dumbbell,
                perm: 'patientcare.interventions.view',
            },
        ],
    },
    {
        label: 'Theatre & Surgery',
        items: [
            {
                title: 'Theatre Dashboard',
                href: '/admin/surgery',
                icon: LayoutGrid,
                perm: 'surgery.dashboard.view',
            },
            {
                title: 'Surgical Cases',
                href: '/admin/surgery/cases',
                icon: Scissors,
                perm: 'surgery.cases.view',
            },
            {
                title: 'Theatre Calendar',
                href: '/admin/surgery/calendar',
                icon: CalendarClock,
                perm: 'surgery.scheduling.view',
            },
            {
                title: 'Operating Theatres',
                href: '/admin/surgery/theatres',
                icon: Building2,
                perm: 'surgery.theatres.view',
            },
            {
                title: 'Operations',
                href: '/admin/surgery/operations',
                icon: Activity,
                perm: 'surgery.cases.view',
            },
            {
                title: 'Recovery / PACU',
                href: '/admin/surgery/recovery',
                icon: BedSingle,
                perm: 'surgery.recovery.view',
            },
            {
                title: 'Surgical Reports',
                href: '/admin/surgery/reports',
                icon: BarChart3,
                perm: 'surgery.reports.view',
            },
            {
                title: 'Surgery Config',
                href: '/admin/surgery/config',
                icon: Settings,
                perm: 'surgery.catalogue.manage',
            },
        ],
    },
    {
        label: 'People & Access',
        items: [
            {
                title: 'Staff & Users',
                href: '/admin/users',
                icon: Users,
                perm: 'users.view',
            },
            {
                title: 'Roles',
                href: '/admin/roles',
                icon: ShieldCheck,
                perm: 'roles.view',
            },
            {
                title: 'Permissions',
                href: '/admin/permissions',
                icon: KeyRound,
                perm: 'permissions.view',
            },
        ],
    },
    {
        label: 'System',
        items: [
            {
                title: 'Module Management',
                href: '/admin/modules',
                icon: Boxes,
                perm: 'modules.view',
            },
            {
                title: 'System Settings',
                href: '/admin/settings',
                icon: Settings,
                perm: 'foundation.settings.view',
            },
        ],
    },
    {
        label: 'Security & Monitoring',
        items: [
            {
                title: 'Audit Logs',
                href: '/admin/audit-logs',
                icon: FileClock,
                perm: 'audit_logs.view',
            },
            {
                title: 'Login History',
                href: '/admin/login-history',
                icon: FileClock,
                perm: 'login_history.view',
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props as { auth: { permissions?: string[] } };
    const perms = auth?.permissions ?? [];
    const groups = navGroups
        .map((g) => ({
            ...g,
            items: g.items.filter((i) => perms.includes(i.perm)),
        }))
        .filter((g) => g.items.length > 0);
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((g) => (
                    <NavMain key={g.label} label={g.label} items={g.items} />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
