<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DashboardSidebar from '@/Components/Dashboard/DashboardSidebar.vue';
import DashboardHeader from '@/Components/Dashboard/DashboardHeader.vue';
import { useSidebar } from '@/Composables/useSidebar';

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
    { label: 'Employees', permission: 'employees.view', href: '/employees', shortcut: 'EM' },
    { label: 'Organization', permission: 'employees.view', href: '/organization', shortcut: 'OR' },
    { label: 'Attendance', permission: 'attendance.view', href: '/attendance', shortcut: 'AT' },
    { label: 'Leaves', permission: 'leave.view', href: '/leave', shortcut: 'LE' },
    { label: 'Payroll', shortcut: 'PA', disabled: true, badge: 'Soon' },
    { label: 'Recruitment', shortcut: 'RE', disabled: true, badge: 'Soon' },
    { label: 'Performance', shortcut: 'PE', disabled: true, badge: 'Soon' },
    { label: 'Reports', shortcut: 'RP', disabled: true, badge: 'Soon' },
    { label: 'Notifications', shortcut: 'NO', disabled: true, badge: 'Soon' },
    { label: 'Team & roles', permission: 'roles.manage', href: '/team', shortcut: 'TR' },
    { label: 'Settings', shortcut: 'SE', disabled: true, badge: 'Soon' },
];

const navigationItems = computed(() => allNavigationItems.filter((item) => allowed(item.permission)));

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
    <div class="hx-dashboard min-h-screen" data-theme="company" :dir="direction">
        <DashboardSidebar
            :items="navigationItems"
            :collapsed="isCollapsed && !isMobileOpen"
            :mobile-open="isMobileOpen"
            theme="company"
            :direction="direction"
            title="Humexa"
            subtitle="Company Workspace"
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
                :direction="direction"
                @toggle-sidebar="handleSidebarToggle"
            />

            <main class="min-h-[calc(100vh-72px)] p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
