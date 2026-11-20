
<script setup>
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/Dashboard/PlatformLayout.vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    recentCompanies: {
        type: Array,
        default: () => [],
    },
});

const statsCards = [
    {
        label: 'Total Companies',
        description: 'All registered companies',
        value: props.stats.totalCompanies,
        shortcut: 'CO',
    },
    {
        label: 'Tenant Users',
        description: 'Users across all companies',
        value: props.stats.totalTenantUsers,
        shortcut: 'US',
    },
    {
        label: 'New This Month',
        description: 'Companies registered this month',
        value: props.stats.newCompaniesThisMonth,
        shortcut: 'MT',
    },
    {
        label: 'New Today',
        description: 'Companies registered today',
        value: props.stats.newCompaniesToday,
        shortcut: 'TD',
    },
];

const quickActions = [
    {
        label: 'Manage Companies',
        description: 'View and manage registered companies',
        shortcut: 'CO',
    },
    {
        label: 'Subscriptions',
        description: 'Manage subscription plans',
        shortcut: 'SU',
    },
    {
        label: 'Platform Users',
        description: 'Manage platform administrators',
        shortcut: 'US',
    },
    {
        label: 'Activity Logs',
        description: 'Review platform activities',
        shortcut: 'AC',
    },
];
</script>

<template>
    <Head title="Platform Dashboard" />

    <PlatformLayout
        title="Overview"
        subtitle="Platform management and system insights"
    >
        <!-- Welcome -->
        <section class="mb-8">
            <p class="mb-2 text-sm font-medium text-cyan-400">
                PLATFORM ADMINISTRATION
            </p>

            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                Platform Overview
            </h1>

            <p class="mt-2 text-sm text-slate-400">
                Monitor your Humexa platform and manage your business ecosystem.
            </p>
        </section>

        <!-- KPI Cards -->
        <section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="stat in statsCards"
                :key="stat.label"
                class="hx-card p-5"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-400">
                            {{ stat.label }}
                        </p>

                        <p class="mt-3 text-3xl font-bold text-white">
                            {{ Number(stat.value ?? 0).toLocaleString() }}
                        </p>

                        <p class="mt-2 text-xs text-slate-500">
                            {{ stat.description }}
                        </p>
                    </div>

                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-500/10 text-xs font-bold text-cyan-400">
                        {{ stat.shortcut }}
                    </span>
                </div>
            </article>
        </section>

        <!-- Main Grid -->
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Growth Chart -->
            <article class="hx-card p-6 xl:col-span-2">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-white">
                            Companies Growth
                        </h2>

                        <p class="mt-1 text-sm text-slate-400">
                            Platform registration analytics
                        </p>
                    </div>

                    <span class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs text-slate-400">
                        Overview
                    </span>
                </div>

                <div class="flex min-h-56 flex-col items-center justify-center text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan-500/10 text-xl font-bold text-cyan-400">
                        GR
                    </div>

                    <h3 class="font-medium text-slate-200">
                        No analytics available
                    </h3>

                    <p class="mt-2 max-w-sm text-sm text-slate-500">
                        Company growth analytics will appear here once registration data is connected.
                    </p>
                </div>
            </article>

            <!-- Quick Actions -->
            <article class="hx-card p-6">
                <h2 class="font-semibold text-white">
                    Quick Actions
                </h2>

                <p class="mt-1 text-sm text-slate-400">
                    Platform management shortcuts
                </p>

                <div class="mt-5 space-y-3">
                    <div
                        v-for="action in quickActions"
                        :key="action.label"
                        class="flex items-center gap-3 rounded-xl border border-slate-800 p-3"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-xs font-bold text-cyan-400">
                            {{ action.shortcut }}
                        </span>

                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-200">
                                {{ action.label }}
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                {{ action.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <!-- Recent Companies -->
        <section class="mt-6">
            <article class="hx-card p-6">
                <div class="mb-5">
                    <h2 class="font-semibold text-white">
                        Recently Registered Companies
                    </h2>

                    <p class="mt-1 text-sm text-slate-400">
                        Latest companies joining Humexa
                    </p>
                </div>

                <div
                    v-if="recentCompanies.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400">
                                <th class="px-3 py-3 font-medium">Company</th>
                                <th class="px-3 py-3 font-medium">Domain</th>
                                <th class="px-3 py-3 font-medium">Registered At</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="company in recentCompanies"
                                :key="company.id"
                                class="border-b border-slate-800/70 last:border-0"
                            >
                                <td class="px-3 py-4 font-medium text-slate-200">
                                    {{ company.companyName }}
                                </td>

                                <td class="px-3 py-4 text-cyan-400">
                                    <div
                                        v-for="domain in company.domains"
                                        :key="domain"
                                    >
                                        {{ domain }}
                                    </div>

                                    <span
                                        v-if="!company.domains.length"
                                        class="text-slate-500"
                                    >
                                        —
                                    </span>
                                </td>

                                <td class="px-3 py-4 text-slate-400">
                                    {{ company.createdAt || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="flex min-h-32 flex-col items-center justify-center text-center"
                >
                    <p class="text-sm font-medium text-slate-300">
                        No companies to display
                    </p>

                    <p class="mt-1 text-xs text-slate-500">
                        Registered companies will appear here.
                    </p>
                </div>
            </article>
        </section>

        <!-- Platform Activity -->
        <section class="mt-6">
            <article class="hx-card p-6">
                <h2 class="font-semibold text-white">
                    Recent Platform Activity
                </h2>

                <p class="mt-1 text-sm text-slate-400">
                    Latest administrative events
                </p>

                <div class="flex min-h-32 flex-col items-center justify-center text-center">
                    <p class="text-sm font-medium text-slate-300">
                        No recent activity
                    </p>

                    <p class="mt-1 text-xs text-slate-500">
                        Platform events will appear here once activity logging is connected.
                    </p>
                </div>
            </article>
        </section>
    </PlatformLayout>
</template>
