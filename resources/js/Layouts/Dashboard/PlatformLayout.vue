<script setup>
import DashboardSidebar from '@/Components/Dashboard/DashboardSidebar.vue';
import DashboardHeader from '@/Components/Dashboard/DashboardHeader.vue';
import { useSidebar } from '@/Composables/useSidebar';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
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

const page = usePage();

const navigationItems = computed(() => [
    { label: 'Overview', href: route('platform.dashboard'), shortcut: 'OV' },
    { label: 'Companies', href: route('platform.companies.index'), shortcut: 'CO' },
    ...(page.props.canViewActivityLogs
        ? [{ label: 'Activity Logs', href: route('platform.activity-logs.index'), shortcut: 'AC' }]
        : []),
    { label: 'Plans', href: route('platform.plans.index'), shortcut: 'PL' },
    { label: 'Billing', shortcut: 'BI', disabled: true, badge: 'Soon' },
    { label: 'Platform Users', shortcut: 'US', disabled: true, badge: 'Soon' },
    { label: 'Notifications', shortcut: 'NO', disabled: true, badge: 'Soon' },
    { label: 'Settings', shortcut: 'SE', disabled: true, badge: 'Soon' },
]);

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
    <div class="hx-dashboard min-h-screen" data-theme="platform" :dir="page.props.dir ?? direction">
        <DashboardSidebar
            :items="navigationItems"
            :collapsed="isCollapsed && !isMobileOpen"
            :mobile-open="isMobileOpen"
            theme="platform"
            :logout-url="route('platform.logout')"
            :direction="page.props.dir ?? direction"
            :title="$t('Afrad')"
            :subtitle="$t('Platform Administration')"
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
                theme="platform"
                :logout-url="route('platform.logout')"
                :collapsed="isCollapsed"
                :direction="page.props.dir ?? direction"
                @toggle-sidebar="handleSidebarToggle"
            />

            <main class="min-h-[calc(100vh-72px)] p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
