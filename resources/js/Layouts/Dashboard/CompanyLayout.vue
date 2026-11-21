<script setup>
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

const navigationItems = [
    { label: 'Overview', href: '/dashboard', shortcut: 'OV' },
    { label: 'Employees', href: '/employees', shortcut: 'EM' },
    { label: 'Organization', href: '/organization', shortcut: 'OR' },
    { label: 'Attendance', href: '/attendance', shortcut: 'AT' },
    { label: 'Leaves', shortcut: 'LE', disabled: true, badge: 'Soon' },
    { label: 'Payroll', shortcut: 'PA', disabled: true, badge: 'Soon' },
    { label: 'Recruitment', shortcut: 'RE', disabled: true, badge: 'Soon' },
    { label: 'Performance', shortcut: 'PE', disabled: true, badge: 'Soon' },
    { label: 'Reports', shortcut: 'RP', disabled: true, badge: 'Soon' },
    { label: 'Notifications', shortcut: 'NO', disabled: true, badge: 'Soon' },
    { label: 'Settings', shortcut: 'SE', disabled: true, badge: 'Soon' },
];

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
