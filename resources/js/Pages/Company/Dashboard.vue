<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    company: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
});

const isActive = computed(() => props.company.status === 'active');

const formattedCreatedAt = computed(() => {
    if (!props.company.createdAt) {
        return '—';
    }

    const date = new Date(props.company.createdAt);

    if (Number.isNaN(date.getTime())) {
        return props.company.createdAt;
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
});

const statCards = computed(() => [
    {
        label: 'Team Accounts',
        value: Number(props.stats.teamAccounts ?? 0).toLocaleString(),
        description: 'User accounts in this workspace',
        shortcut: 'US',
    },
    {
        label: 'Workspace Status',
        value: isActive.value ? 'Active' : 'Suspended',
        description: 'Current company access status',
        shortcut: 'ST',
        status: isActive.value ? 'success' : 'danger',
    },
    {
        label: 'Workspace Created',
        value: formattedCreatedAt.value,
        description: 'Company registration date',
        shortcut: 'DT',
    },
    {
        label: 'Workspace Domain',
        value: props.company.domain || '—',
        description: 'Your company sign-in address',
        shortcut: 'DM',
        compact: true,
    },
]);

const modules = [
    {
        name: 'Employee Management',
        description: 'Employee profiles, employment details and documents',
        shortcut: 'EM',
    },
    {
        name: 'Attendance',
        description: 'Check-in, shifts, working hours and overtime',
        shortcut: 'AT',
    },
    {
        name: 'Leave Management',
        description: 'Leave policies, balances and approval workflows',
        shortcut: 'LV',
    },
    {
        name: 'Payroll',
        description: 'Salary structures, payslips and payroll processing',
        shortcut: 'PY',
    },
    {
        name: 'Reports & Analytics',
        description: 'Workforce insights and downloadable reports',
        shortcut: 'RP',
    },
];
</script>

<template>
    <Head title="Company Overview" />

    <CompanyLayout title="Overview" subtitle="Your company workspace">
        <!-- Page heading -->
        <section class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">
                    COMPANY WORKSPACE
                </p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Welcome back
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Here's what's happening in {{ company.name }}.
                </p>
            </div>

            <div
                class="inline-flex w-fit items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold"
                :class="isActive
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    : 'border-red-200 bg-red-50 text-red-700'"
            >
                <span
                    class="h-2 w-2 rounded-full"
                    :class="isActive ? 'bg-emerald-500' : 'bg-red-500'"
                />
                {{ isActive ? 'Workspace active' : 'Workspace suspended' }}
            </div>
        </section>

        <!-- Company identity card -->
        <section class="mb-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="relative overflow-hidden bg-gradient-to-r from-slate-950 via-slate-900 to-[#12334A] px-5 py-6 sm:px-7 sm:py-8">
                <div class="pointer-events-none absolute -end-12 -top-24 h-64 w-64 rounded-full border-[36px] border-cyan-400/10" />
                <div class="pointer-events-none absolute end-24 -bottom-24 h-48 w-48 rounded-full bg-cyan-400/10 blur-3xl" />

                <div class="relative flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">
                            YOUR ORGANIZATION
                        </p>
                        <h2 class="mt-2 truncate text-2xl font-bold tracking-tight text-white sm:text-3xl">
                            {{ company.name }}
                        </h2>
                        <p class="mt-2 break-all text-sm text-slate-300">
                            {{ company.domain }}
                        </p>
                    </div>

                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-2xl font-black text-cyan-200 shadow-xl backdrop-blur">
                        {{ company.name?.charAt(0)?.toUpperCase() || 'H' }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="px-5 py-4 sm:px-7">
                    <p class="text-xs font-medium text-slate-500">Workspace status</p>
                    <p class="mt-1 text-sm font-semibold capitalize" :class="isActive ? 'text-emerald-700' : 'text-red-700'">
                        {{ company.status || 'Unknown' }}
                    </p>
                </div>
                <div class="px-5 py-4 sm:px-7">
                    <p class="text-xs font-medium text-slate-500">Registered on</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ formattedCreatedAt }}</p>
                </div>
                <div class="px-5 py-4 sm:px-7">
                    <p class="text-xs font-medium text-slate-500">Workspace type</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">Humexa HRMS</p>
                </div>
            </div>
        </section>

        <!-- Workspace stats -->
        <section class="mb-8">
            <div class="mb-4">
                <h2 class="text-base font-bold text-slate-900">Workspace at a glance</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Live information currently available for your company.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="stat in statCards"
                    :key="stat.label"
                    class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-500">{{ stat.label }}</p>
                            <p
                                class="mt-3 break-words font-bold tracking-tight"
                                :class="[
                                    stat.compact ? 'text-base' : 'text-2xl',
                                    stat.status === 'success' ? 'text-emerald-700' : '',
                                    stat.status === 'danger' ? 'text-red-700' : 'text-slate-900',
                                ]"
                            >
                                {{ stat.value }}
                            </p>
                            <p class="mt-2 text-xs leading-5 text-slate-400">{{ stat.description }}</p>
                        </div>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-xs font-bold text-cyan-800">
                            {{ stat.shortcut }}
                        </span>
                    </div>
                    <div class="pointer-events-none absolute -bottom-8 -end-8 h-24 w-24 rounded-full bg-cyan-500/5 blur-2xl transition group-hover:bg-cyan-500/10" />
                </article>
            </div>
        </section>

        <!-- Modules -->
        <section class="mb-8">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Your HR modules</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Your company's HR tools, progressively rolling out.
                    </p>
                </div>
                <span class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-500">
                    More modules coming soon
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="module in modules"
                    :key="module.name"
                    class="flex min-h-36 items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 opacity-80 shadow-sm"
                >
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-600">
                        {{ module.shortcut }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-800">{{ module.name }}</h3>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                                Coming soon
                            </span>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ module.description }}</p>
                    </div>
                </article>
            </div>
        </section>

        <!-- Empty state / roadmap -->
        <section class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 p-6 sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white text-sm font-black text-cyan-700 shadow-sm ring-1 ring-slate-200">
                    HX
                </div>
                <div class="min-w-0">
                    <h2 class="font-semibold text-slate-900">Your workspace is ready</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                        Your company workspace is connected and secure. Employee, attendance, leave and payroll data will appear here as those modules are implemented and enabled.
                    </p>
                </div>
            </div>
        </section>
    </CompanyLayout>
</template>
