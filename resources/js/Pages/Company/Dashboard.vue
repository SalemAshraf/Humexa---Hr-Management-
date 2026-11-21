<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps({
    company: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    analytics: {
        type: Object,
        default: () => ({ headcount: null, attendance: null, leave: null }),
    },
});

const headcount = computed(() => props.analytics?.headcount ?? null);
const attendance = computed(() => props.analytics?.attendance ?? null);

const maxDepartment = computed(() =>
    Math.max(1, ...(headcount.value?.by_department ?? []).map((row) => row.total)),
);
const maxMonthly = computed(() =>
    Math.max(1, ...(headcount.value?.monthly ?? []).flatMap((row) => [row.hires, row.exits])),
);
const presentRate = computed(() => {
    if (!attendance.value || !attendance.value.expected) {
        return 0;
    }

    return Math.min(100, Math.round((attendance.value.present / attendance.value.expected) * 100));
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

const kpis = computed(() => {
    const list = [];
    const h = props.analytics?.headcount;
    const at = props.analytics?.attendance;
    const lv = props.analytics?.leave;
    if (h) list.push({ label: t('Employees'), value: h.current, hint: t('{n} on probation', { n: h.on_probation }), href: '/employees', tone: 'text-slate-900' });
    if (at) list.push({ label: t('Attendance today'), value: `${presentRate.value}%`, hint: t('{p} of {e} expected recorded, {l} late', { p: at.present, e: at.expected, l: at.late }), href: '/attendance', tone: 'text-emerald-700' });
    if (lv) {
        list.push({ label: t('Leave waiting for approval'), value: lv.pending, hint: t('Open the leave page to review'), href: '/leave', tone: lv.pending ? 'text-amber-600' : 'text-slate-900' });
        list.push({ label: t('On leave today'), value: lv.on_leave_today, hint: t('Approved leave covering today'), href: '/leave', tone: 'text-slate-900' });
    }
    return list;
});

const page = usePage();
const allModules = [
    { name: t('Employee Management'), description: t('Employee profiles, employment details and documents'), shortcut: 'EM', href: '/employees', permission: 'employees.view' },
    { name: t('Attendance'), description: t('Check-in, shifts, working hours and overtime'), shortcut: 'AT', href: '/attendance', permission: 'attendance.view' },
    { name: t('Leave Management'), description: t('Leave policies, balances and approval workflows'), shortcut: 'LV', href: '/leave', permission: 'leave.view' },
    { name: t('Recruitment'), description: t('Openings, candidates and hiring stages'), shortcut: 'RE', href: '/recruitment', permission: 'recruitment.view' },
    { name: t('Performance'), description: t('Review cycles, ratings and goals'), shortcut: 'PE', href: '/performance', permission: 'performance.view' },
    { name: t('Payroll estimator'), description: t('Experimental salary estimates. Not verified for real payroll.'), shortcut: 'PA', href: '/payroll', permission: 'payroll.view', note: t('Experimental') },
    { name: t('Reports'), description: t('Workforce numbers for a period, with CSV download'), shortcut: 'RP', href: '/reports', permission: 'reports.view' },
    { name: t('Document expiry'), description: t('Employee documents that expired or expire soon'), shortcut: 'DX', href: '/documents/expiry', permission: 'employees.manage' },
];
const modules = computed(() => allModules.filter((m) => !m.permission || !!page.props.can?.[m.permission]));
</script>

<template>
    <Head :title="$t('Company Overview')" />

    <CompanyLayout :title="$t('Overview')" :subtitle="$t('Your company workspace')">
        <!-- Page heading -->
        <section class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('COMPANY WORKSPACE') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Welcome back') }}</h1>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $t("Here's what's happening in {name}.", { name: company.name }) }}
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
                {{ isActive ? $t('Workspace active') : $t('Workspace suspended') }}
            </div>
        </section>

        <!-- Key numbers -->
        <section v-if="kpis.length" class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-testid="kpi-row">
            <component :is="k.href ? Link : 'article'" v-for="k in kpis" :key="k.label" :href="k.href"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-sm font-medium text-slate-500">{{ k.label }}</p>
                <p class="mt-3 text-3xl font-bold tracking-tight" :class="k.tone">{{ k.value }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ k.hint }}</p>
            </component>
        </section>

        <!-- Workforce analytics -->
        <section v-if="headcount || attendance" class="mb-8 grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article v-if="headcount" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2" data-testid="headcount-card">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">{{ $t('Workforce') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $t('Current employees by department') }}</p>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">{{ headcount.current }}
                        <span class="text-sm font-medium text-slate-500">{{ $t('employees') }}</span>
                    </p>
                </div>

                <ul class="mt-5 space-y-3">
                    <li v-for="row in headcount.by_department" :key="row.name">
                        <div class="mb-1 flex justify-between text-xs font-medium text-slate-600">
                            <span class="truncate">{{ row.name }}</span>
                            <span>{{ row.total }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-500" :style="{ width: (row.total / maxDepartment) * 100 + '%' }" />
                        </div>
                    </li>
                    <li v-if="!headcount.by_department.length" class="text-sm text-slate-500">{{ $t('Add employees to see the department split.') }}</li>
                </ul>

                <div class="mt-6 border-t border-slate-100 pt-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t('Hires and exits, last 6 months') }}</p>
                    <div class="flex items-end gap-3">
                        <div v-for="m in headcount.monthly" :key="m.month" class="flex flex-1 flex-col items-center gap-1">
                            <div class="flex h-24 w-full items-end justify-center gap-1">
                                <div class="w-3 rounded-t bg-emerald-500" :style="{ height: (m.hires / maxMonthly) * 100 + '%', minHeight: m.hires ? '4px' : '0' }" :title="m.hires + ' hires'" />
                                <div class="w-3 rounded-t bg-slate-800" :style="{ height: (m.exits / maxMonthly) * 100 + '%', minHeight: m.exits ? '4px' : '0' }" :title="m.exits + ' exits'" />
                            </div>
                            <span class="text-[10px] text-slate-500">{{ m.month }}</span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        <span class="mr-3"><span class="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-500" />{{ $t('Hires') }}</span>
                        <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-slate-800" />{{ $t('Exits') }}</span>
                    </p>
                </div>
            </article>

            <article v-if="attendance" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-testid="attendance-card">
                <h2 class="text-base font-bold text-slate-900">{{ $t('Attendance today') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $t('Expected: active and probation employees') }}</p>
                <p class="mt-4 text-3xl font-bold text-slate-900">{{ presentRate }}%
                    <span class="text-sm font-medium text-slate-500">{{ $t('recorded') }}</span>
                </p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: presentRate + '%' }" />
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">{{ $t('Present') }}</dt><dd class="font-semibold text-slate-900">{{ attendance.present }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">{{ $t('Checked in now') }}</dt><dd class="font-semibold text-slate-900">{{ attendance.checked_in_now }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">{{ $t('Late') }}</dt><dd class="font-semibold text-slate-900">{{ attendance.late }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">{{ $t('No record yet') }}</dt><dd class="font-semibold text-slate-900">{{ attendance.absent_or_missing }}</dd></div>
                </dl>
            </article>
        </section>

        <!-- Modules -->
        <section class="mb-8">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">{{ $t('Your HR modules') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $t('The tools your role can open.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <component
                    :is="module.href ? Link : 'article'"
                    v-for="module in modules"
                    :key="module.name"
                    :href="module.href"
                    class="flex min-h-36 items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    :class="module.href ? 'transition hover:-translate-y-0.5 hover:shadow-md' : 'opacity-80'"
                >
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-600">
                        {{ module.shortcut }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-800">{{ module.name }}</h3>
                            <span v-if="module.note" class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                {{ module.note }}
                            </span>
                            <span v-else class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">{{ $t('Available') }}</span>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ module.description }}</p>
                    </div>
                </component>
            </div>
        </section>

    </CompanyLayout>
</template>
