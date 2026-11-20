<script setup>
import { Head, Link } from '@inertiajs/vue3';
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
    canViewActivityLogs: {
        type: Boolean,
        default: false,
    },
});

const statsCards = [
    {
        label: 'Total Companies',
        description: 'All registered companies',
        value: props.stats.totalCompanies,
        icon: 'CO',
    },
    {
        label: 'Tenant Users',
        description: 'Users across all companies',
        value: props.stats.totalTenantUsers,
        icon: 'US',
    },
    {
        label: 'New This Month',
        description: 'Companies registered this month',
        value: props.stats.newCompaniesThisMonth,
        icon: 'MT',
    },
    {
        label: 'New Today',
        description: 'Companies registered today',
        value: props.stats.newCompaniesToday,
        icon: 'TD',
    },
];
</script>

<template>
    <Head title="Platform Overview" />

    <PlatformLayout title="Overview" subtitle="Platform management and system insights">
        <section class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-400">
                    HUMEXA CONTROL CENTER
                </p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Platform Overview
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-400">
                    A central view of company registrations and platform activity.
                </p>
            </div>

            <Link
                :href="route('platform.companies.index')"
                class="inline-flex items-center justify-center rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400"
            >
                Manage Companies
                <span class="ms-2" aria-hidden="true">→</span>
            </Link>
        </section>

        <section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="stat in statsCards"
                :key="stat.label"
                class="hx-card relative overflow-hidden p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-400">{{ stat.label }}</p>
                        <p class="mt-3 text-3xl font-bold tabular-nums text-white">
                            {{ Number(stat.value ?? 0).toLocaleString() }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500">{{ stat.description }}</p>
                    </div>
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-cyan-400/15 bg-cyan-400/10 text-xs font-bold text-cyan-300">
                        {{ stat.icon }}
                    </span>
                </div>
                <div class="pointer-events-none absolute -bottom-8 -end-8 h-24 w-24 rounded-full bg-cyan-500/5 blur-2xl" />
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <article class="hx-card overflow-hidden p-5 sm:p-6 xl:col-span-2">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-white">Recently Registered Companies</h2>
                        <p class="mt-1 text-sm text-slate-400">The latest organizations joining Humexa</p>
                    </div>
                    <Link
                        :href="route('platform.companies.index')"
                        class="text-sm font-semibold text-cyan-400 transition hover:text-cyan-300"
                    >
                        View all companies →
                    </Link>
                </div>

                <div v-if="recentCompanies.length" class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-800 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-3 font-semibold">Company</th>
                                <th class="px-3 py-3 font-semibold">Domain</th>
                                <th class="px-3 py-3 font-semibold">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="company in recentCompanies"
                                :key="company.id"
                                class="border-b border-slate-800/70 last:border-0"
                            >
                                <td class="px-3 py-4 font-medium text-slate-200">{{ company.companyName }}</td>
                                <td class="px-3 py-4 text-cyan-300">
                                    <span v-if="company.domains.length">{{ company.domains.join(', ') }}</span>
                                    <span v-else class="text-slate-500">No domain</span>
                                </td>
                                <td class="px-3 py-4 text-slate-400">{{ company.createdAt || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="flex min-h-40 flex-col items-center justify-center text-center">
                    <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-800 text-sm font-bold text-slate-400">CO</span>
                    <p class="text-sm font-medium text-slate-300">No companies registered yet</p>
                    <p class="mt-1 text-xs text-slate-500">New company registrations will appear here.</p>
                </div>
            </article>

            <article class="hx-card p-5 sm:p-6">
                <h2 class="font-semibold text-white">Platform Shortcuts</h2>
                <p class="mt-1 text-sm text-slate-400">Go directly to an available workspace</p>

                <div class="mt-5 space-y-3">
                    <Link
                        :href="route('platform.companies.index')"
                        class="group flex items-center gap-3 rounded-xl border border-slate-800 p-3 transition hover:border-cyan-400/30 hover:bg-cyan-400/5"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-400/10 text-xs font-bold text-cyan-300">CO</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-200">Company Management</span>
                            <span class="mt-1 block text-xs text-slate-500">Review status and manage access</span>
                        </span>
                        <span class="text-slate-500 transition group-hover:text-cyan-300">→</span>
                    </Link>

                    <Link
                        :href="route('platform.activity-logs.index')"
                        class="group flex items-center gap-3 rounded-xl border border-slate-800 p-3 transition hover:border-cyan-400/30 hover:bg-cyan-400/5"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-400/10 text-xs font-bold text-cyan-300">AC</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-200">Activity Logs</span>
                            <span class="mt-1 block text-xs text-slate-500">Inspect administrative status changes</span>
                        </span>
                        <span class="text-slate-500 transition group-hover:text-cyan-300">→</span>
                    </Link>

                    <Link
                        v-if="canViewActivityLogs"
                        :href="route('platform.activity-logs.index')"
                        class="group flex items-center gap-3 rounded-xl border border-slate-800 p-3 transition hover:border-cyan-400/30 hover:bg-cyan-400/5"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-400/10 text-xs font-bold text-cyan-300">AC</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-200">Activity Logs</span>
                            <span class="mt-1 block text-xs text-slate-500">Inspect administrative status changes</span>
                        </span>
                        <span class="text-slate-500 transition group-hover:text-cyan-300">→</span>
                    </Link>
                </div>

                <div class="mt-5 rounded-xl border border-dashed border-slate-700 bg-slate-900/40 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">More modules</p>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Subscriptions, billing, platform users, notifications and settings will be connected as their backend modules are implemented.
                    </p>
                </div>
            </article>
        </section>
    </PlatformLayout>
</template>
