
<script setup>
import DashboardSidebar from '@/Components/Dashboard/DashboardSidebar.vue';
import DashboardHeader from '@/Components/Dashboard/DashboardHeader.vue';
import { useSidebar } from '@/Composables/useSidebar';

const props = defineProps({
    title: { type: String, default: 'Dashboard' },
    subtitle: { type: String, default: 'Platform Overview' },
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
    { label: 'Overview', href: '/platform/dashboard', shortcut: 'OV' },
    { label: 'Companies', href: '/platform/companies', shortcut: 'CO' },
    { label: 'Subscriptions', href: '/platform/subscriptions', shortcut: 'SU' },
    { label: 'Billing', href: '/platform/billing', shortcut: 'BI' },
    { label: 'Platform Users', href: '/platform/users', shortcut: 'US' },
    { label: 'Activity Logs', href: '/platform/activity-logs', shortcut: 'AC' },
    { label: 'Notifications', href: '/platform/notifications', shortcut: 'NO' },
    { label: 'Settings', href: '/platform/settings', shortcut: 'SE' },
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
    <div class="hx-dashboard min-h-screen" data-theme="platform"
                :logout-url="route('platform.logout')" :dir="direction">
        <DashboardSidebar
            :items="navigationItems"
            :collapsed="isCollapsed && !isMobileOpen"
            :mobile-open="isMobileOpen"
            theme="platform"
                :logout-url="route('platform.logout')"
            :direction="direction"
            title="Humexa"
            subtitle="Platform Administration"
            @toggle-collapse="toggleCollapsed"
            @close-mobile="closeMobile"
        />

        <div
            class="hx-dashboard-content min-h-screen"
            :style="{
                '--hx-sidebar-current-width': isCollapsed ? '76px' : '260px',
            }"
        >
            <DashboardHeader
                :title="title"
                :subtitle="subtitle"
                theme="platform"
                :logout-url="route('platform.logout')"
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

