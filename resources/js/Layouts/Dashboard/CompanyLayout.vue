<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DashboardSidebar from '@/Components/Dashboard/DashboardSidebar.vue';
import DashboardHeader from '@/Components/Dashboard/DashboardHeader.vue';
import { useSidebar } from '@/Composables/useSidebar';
import { t } from '@/lib/i18n';

defineProps({
    title: { type: String, default: 'Dashboard' },
    subtitle: { type: String, default: 'Company Overview' },
    direction: { type: String, default: 'ltr' },
});

const {
    isCollapsed,
    isMobileOpen,
    toggleCollapsed,
    toggleMobile,
    closeMobile,
} = useSidebar();

const page = usePage();
const allowed = (permission) => !permission || !!page.props.can?.[permission];

const allNavigationItems = [
    { label: 'Overview', href: '/dashboard', shortcut: 'OV' },
    { label: 'My space', href: '/me', shortcut: 'ME', hideForOwner: true },
    { label: 'Employees', permission: 'employees.view', href: '/employees', shortcut: 'EM' },
    { label: 'Organization', permission: 'employees.view', href: '/organization', shortcut: 'OR' },
    { label: 'Document expiry', permission: 'employees.manage', href: '/documents/expiry', shortcut: 'DX' },
    { label: 'Attendance', permission: 'attendance.view', href: '/attendance', shortcut: 'AT' },
    { label: 'Leaves', permission: 'leave.view', href: '/leave', shortcut: 'LE' },
    { label: 'Payroll', permission: 'payroll.view', href: '/payroll', shortcut: 'PA' },
    { label: 'Recruitment', permission: 'recruitment.view', href: '/recruitment', shortcut: 'RE' },
    { label: 'Performance', permission: 'performance.view', href: '/performance', shortcut: 'PE' },
    { label: 'Reports', permission: 'reports.view', href: '/reports', shortcut: 'RP' },
    { label: 'Notifications', href: '/notifications', shortcut: 'NO' },
    { label: 'Team & roles', permission: 'roles.manage', href: '/team', shortcut: 'TR' },
    { label: 'Settings', permission: 'settings.manage', href: '/settings', shortcut: 'SE' },
    { label: 'My account', href: '/account', shortcut: 'AC' },
];

const navigationItems = computed(() => allNavigationItems.filter((item) => allowed(item.permission) && !(item.hideForOwner && page.props.is_owner)).map((item) => ({ ...item, label: t(item.label) })));

function handleSidebarToggle() {
    if (window.matchMedia('(min-width: 1024px)').matches) {
        toggleCollapsed();
        closeMobile();
        return;
    }

    toggleMobile();
}
</script>

<template>
    <div class="hx-dashboard min-h-screen" data-theme="company" :dir="page.props.dir ?? direction">
        <DashboardSidebar
            :items="navigationItems"
            :collapsed="isCollapsed && !isMobileOpen"
            :mobile-open="isMobileOpen"
            theme="company"
            :direction="page.props.dir ?? direction"
            :title="$t('Afrad')"
            :subtitle="$t('Company Workspace')"
            @toggle-collapse="toggleCollapsed"
            @close-mobile="closeMobile"
        />

        <div
            class="hx-dashboard-content min-h-screen"
            :style="{ '--hx-sidebar-current-width': isCollapsed ? '76px' : '260px' }"
        >
            <DashboardHeader
                :title="title"
                :subtitle="subtitle"
                theme="company"
                :logout-url="'/logout'"
                :collapsed="isCollapsed"
                :direction="page.props.dir ?? direction"
                @toggle-sidebar="handleSidebarToggle"
            />

            <main class="min-h-[calc(100vh-72px)] p-4 sm:p-6 lg:p-8">
                <p v-if="$page.props.errors?.plan_limit" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900" role="alert">{{ $page.props.errors.plan_limit }}</p>
                <slot />
            </main>
        </div>
    </div>
</template>
