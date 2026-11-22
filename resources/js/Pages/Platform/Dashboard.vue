<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlatformLayout from '@/Layouts/Dashboard/PlatformLayout.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    growth: { type: Array, default: () => [] },
    registrationActivity: { type: Array, default: () => [] },
    recentCompanies: { type: Array, default: () => [] },
    recentActivity: { type: Array, default: () => [] },
    canViewActivityLogs: { type: Boolean, default: false },
});

const statsCards = computed(() => [
    { label: 'Registered companies', value: props.stats.totalCompanies, description: 'All company workspaces', icon: 'CO' },
    { label: 'Active workspaces', value: props.stats.activeCompanies, description: '{rate}% of registered', icon: 'AC', accent: true },
    { label: 'New this month', value: props.stats.newCompaniesThisMonth, description: 'Company registrations this month', icon: 'NM' },
    { label: 'Suspended', value: props.stats.suspendedCompanies, description: 'Company access disabled', icon: 'SU', warning: true },
]);

const chartPoints = computed(() => {
    const values = props.growth.map(item => Number(item.count || 0));
    const max = Math.max(1, ...values);
    return values.map((value, index) => ({
        x: 32 + index * (568 / Math.max(values.length - 1, 1)),
        y: 154 - (value / max) * 120,
        label: props.growth[index]?.label,
        count: value,
    }));
});
const linePath = computed(() => chartPoints.value.map((p, i) => `${i ? 'L' : 'M'} ${p.x} ${p.y}`).join(' '));
const areaPath = computed(() => chartPoints.value.length ? `${linePath.value} L 600 160 L 32 160 Z` : '');
const totalStatus = computed(() => Math.max(0, props.stats.totalCompanies));
const activeAngle = computed(() => totalStatus.value ? (props.stats.activeCompanies / totalStatus.value) * 360 : 0);
const setupAngle = computed(() => totalStatus.value ? (props.stats.settingUpCompanies / totalStatus.value) * 360 : 0);
const donutStyle = computed(() => ({ background: `conic-gradient(#78d9ae 0deg ${activeAngle.value}deg, #416f61 ${activeAngle.value}deg ${activeAngle.value + setupAngle.value}deg, #e57383 ${activeAngle.value + setupAngle.value}deg 360deg)` }));
const maxDay = computed(() => Math.max(1, ...props.registrationActivity.map(d => d.count)));
</script>

<template>
    <Head :title="$t('Platform Overview')" />
    <PlatformLayout :title="$t('Overview')" :subtitle="$t('Platform management and system insights')">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-300">{{ $t('Afrad Control Center') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $t('Platform growth, at a glance.') }}</h1>
                <p class="mt-1 text-sm text-slate-400">{{ $t('Company workspaces, registration activity and platform health.') }}</p>
            </div>
            <Link :href="route('platform.companies.index')" class="inline-flex items-center rounded-xl bg-emerald-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-emerald-200">{{ $t('Manage companies') }}<span class="ms-2">→</span>
            </Link>
        </div>

        <div class="mb-5 flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-3 text-xs">
            <span class="font-bold tracking-wide text-slate-200">{{ $t('Super admin view') }}</span>
        </div>

        <section class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="stat in statsCards" :key="stat.label" class="hx-card p-5">
                <p class="text-xs text-slate-400">{{ $t(stat.label) }}</p>
                <div class="mt-2 flex items-center justify-between">
                    <p class="text-3xl font-bold tabular-nums" :class="stat.accent ? 'text-emerald-300' : stat.warning ? 'text-rose-300' : 'text-slate-100'">{{ Number(stat.value || 0).toLocaleString() }}</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-700 bg-slate-800/70 text-xs font-bold text-slate-400">{{ stat.icon }}</span>
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ $t(stat.description, { rate: props.stats.activeRate }) }}</p>
            </article>
        </section>

        <section class="mb-5 grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article class="hx-card p-5 sm:p-6 xl:col-span-2">
                <div class="mb-4">
                    <h2 class="font-semibold text-slate-100">{{ $t('Company growth') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ $t('New registered workspaces · Last six months') }}</p>
                </div>
                <div class="overflow-hidden">
                    <svg viewBox="0 0 620 196" class="w-full" role="img" :aria-label="$t('Monthly company registrations trend')">
                        <g stroke="#263449" stroke-width="1">
                            <line x1="32" y1="34" x2="600" y2="34"/><line x1="32" y1="74" x2="600" y2="74"/>
                            <line x1="32" y1="114" x2="600" y2="114"/><line x1="32" y1="160" x2="600" y2="160"/>
                        </g>
                        <path v-if="chartPoints.length" :d="areaPath" fill="#78d9ae" fill-opacity=".12"/>
                        <path v-if="chartPoints.length" :d="linePath" fill="none" stroke="#78d9ae" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <g v-for="(point,index) in chartPoints" :key="index">
                            <circle :cx="point.x" :cy="point.y" r="4" fill="#78d9ae"/>
                            <text :x="point.x" :y="point.y-10" text-anchor="middle" fill="#dbe5ef" font-weight="600" class="text-[24px] sm:text-[11px]">{{ point.count }}</text>
                            <text :x="point.x" y="188" text-anchor="middle" fill="#94a3b8" class="text-[22px] sm:text-[10px]">{{ $t(point.label) }}</text>
                        </g>
                    </svg>
                </div>
                <p class="mt-2 text-[10px] text-slate-500">{{ $t('Workspace metadata only · employee records are not exposed at platform level') }}</p>
            </article>

            <article class="hx-card p-5 sm:p-6">
                <h2 class="font-semibold text-slate-100">{{ $t('Workspace status') }}</h2>
                <p class="mt-1 text-xs text-slate-400">{{ $t('{n} registered workspaces', { n: stats.totalCompanies }) }}</p>
                <div class="my-6 flex items-center justify-center gap-5">
                    <div class="relative h-32 w-32 shrink-0 rounded-full" :style="donutStyle">
                        <div class="absolute inset-[17px] flex flex-col items-center justify-center rounded-full bg-[#111c2e]">
                            <span class="text-2xl font-bold text-slate-100">{{ stats.totalCompanies }}</span><span class="text-[10px] text-slate-500">{{ $t('workspaces') }}</span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div><p class="text-xs text-slate-400">{{ $t('Activation rate') }}</p><p class="text-xl font-bold text-emerald-300">{{ stats.activeRate }}%</p></div>
                        <div><p class="text-xs text-slate-400">{{ $t('Suspended share') }}</p><p class="text-lg font-semibold text-slate-100">{{ stats.suspendedRate }}%</p></div>
                    </div>
                </div>
                <div class="space-y-2 text-xs text-slate-400">
                    <p class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-300"></span>{{ $t('Active') }} · {{ stats.activeCompanies }}</p>
                    <p class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[#416f61]"></span>{{ $t('Setting up') }} · {{ stats.settingUpCompanies }}</p>
                    <p class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-rose-400"></span>{{ $t('Suspended') }} · {{ stats.suspendedCompanies }}</p>
                </div>
            </article>
        </section>

        <section class="mb-5 grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article class="hx-card p-5 sm:p-6 xl:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <div><h2 class="font-semibold text-slate-100">{{ $t('Recently registered companies') }}</h2><p class="mt-1 text-xs text-slate-400">{{ $t('Workspace metadata and access status') }}</p></div>
                    <Link :href="route('platform.companies.index')" class="text-xs font-semibold text-emerald-300 hover:text-emerald-200">{{ $t('All companies →') }}</Link>
                </div>
                <ul v-if="recentCompanies.length" class="divide-y divide-slate-800/70 sm:hidden">
                    <li v-for="company in recentCompanies" :key="company.id" class="py-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="min-w-0 break-words font-semibold text-slate-200">{{ company.companyName }}</p>
                            <span class="shrink-0 rounded-full px-2 py-1 text-[11px] font-semibold capitalize" :class="company.status === 'active' ? 'bg-emerald-400/10 text-emerald-300' : company.status === 'suspended' ? 'bg-rose-400/10 text-rose-300' : 'bg-amber-400/10 text-amber-300'">{{ $t(company.status.replace('_',' ')) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">{{ $t('Registered') }}: {{ company.createdAt || '—' }}</p>
                        <p class="mt-0.5 break-all text-xs text-slate-400">{{ company.domains.join(', ') || '—' }}</p>
                    </li>
                </ul>
                <div v-if="recentCompanies.length" class="hidden overflow-x-auto sm:block">
                    <table class="w-full min-w-[520px] text-start text-sm">
                        <thead><tr class="border-b border-slate-800 text-[10px] uppercase tracking-wider text-slate-500"><th class="px-2 py-3">{{ $t('Company / Workspace') }}</th><th class="px-2 py-3">{{ $t('Status') }}</th><th class="px-2 py-3">{{ $t('Registered') }}</th><th class="px-2 py-3">{{ $t('Domains') }}</th></tr></thead>
                        <tbody>
                            <tr v-for="company in recentCompanies" :key="company.id" class="border-b border-slate-800/70 last:border-0">
                                <td class="px-2 py-3 font-semibold text-slate-200">{{ company.companyName }}</td>
                                <td class="px-2 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-semibold capitalize" :class="company.status === 'active' ? 'bg-emerald-400/10 text-emerald-300' : company.status === 'suspended' ? 'bg-rose-400/10 text-rose-300' : 'bg-amber-400/10 text-amber-300'">{{ $t(company.status.replace('_',' ')) }}</span></td>
                                <td class="px-2 py-3 text-xs text-slate-400">{{ company.createdAt || '—' }}</td>
                                <td class="px-2 py-3 text-xs text-slate-400">{{ company.domains.join(', ') || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="py-12 text-center text-sm text-slate-500">{{ $t('No companies registered yet.') }}</div>
                <p class="mt-3 text-[10px] text-slate-500">{{ $t('Showing {n} recent companies · company metadata only', { n: recentCompanies.length }) }}</p>
            </article>

            <article class="hx-card p-5 sm:p-6">
                <h2 class="font-semibold text-slate-100">{{ $t('Registration activity') }}</h2>
                <p class="mt-1 text-xs text-slate-400">{{ $t('Daily signups · Last seven days') }}</p>
                <div class="mt-7 flex h-36 items-end justify-between gap-2">
                    <div v-for="day in registrationActivity" :key="day.label" class="flex flex-1 flex-col items-center gap-2">
                        <span class="text-[10px] text-slate-400">{{ day.count }}</span>
                        <div class="flex h-24 w-full items-end justify-center"><div class="w-full max-w-7 rounded-t-md bg-emerald-300/50 transition hover:bg-emerald-300" :style="{ height: Math.max(5, (day.count/maxDay)*100)+'%' }"></div></div>
                        <span class="text-[10px] text-slate-500">{{ day.label }}</span>
                    </div>
                </div>
                <div class="mt-5 border-t border-slate-800 pt-4"><p class="text-xs text-slate-400">{{ $t('New this week') }}</p><p class="mt-1 text-xl font-bold text-emerald-300">{{ registrationActivity.reduce((sum,d)=>sum+d.count,0) }}</p></div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article v-if="canViewActivityLogs" class="hx-card p-5 sm:p-6 xl:col-span-2">
                <div class="mb-5 flex items-center justify-between"><div><h2 class="font-semibold text-slate-100">{{ $t('Recent admin activity') }}</h2><p class="mt-1 text-xs text-slate-400">{{ $t('Platform actions, recorded with context') }}</p></div><Link :href="route('platform.activity-logs.index')" class="text-xs font-semibold text-emerald-300">{{ $t('Full log →') }}</Link></div>
                <div v-if="recentActivity.length" class="space-y-3">
                    <div v-for="entry in recentActivity" :key="entry.id" class="flex gap-3 border-b border-slate-800 pb-3 last:border-0">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="entry.newStatus === 'suspended' ? 'bg-rose-400' : 'bg-emerald-300'"></span>
                        <div class="min-w-0"><p class="text-sm text-slate-200">{{ entry.event === 'company.suspended' ? $t('Workspace access suspended') : $t('Company registration reviewed / activated') }}</p><p class="mt-1 text-xs text-slate-500">{{ entry.companyName }} · {{ entry.actor }} · {{ entry.createdAt }}</p></div>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-500">{{ $t('No recorded platform actions yet.') }}</p>
            </article>
            <article class="hx-card p-5 sm:p-6">
                <h2 class="font-semibold text-slate-100">{{ $t('Platform health') }}</h2><p class="mt-1 text-xs text-slate-400">{{ $t('Key platform metrics') }}</p>
                <div class="mt-5 space-y-4 text-xs">
                    <div class="flex justify-between"><span class="text-slate-400">{{ $t('Activation rate') }}</span><span class="font-semibold text-emerald-300">{{ stats.activeRate }}%</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">{{ $t('Tenant users') }}</span><span class="font-semibold text-slate-200">{{ stats.totalTenantUsers.toLocaleString() }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">{{ $t('Registrations today') }}</span><span class="font-semibold text-slate-200">{{ stats.newCompaniesToday }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">{{ $t('Suspended share') }}</span><span class="font-semibold text-slate-200">{{ stats.suspendedRate }}%</span></div>
                </div>
            </article>
        </section>
    </PlatformLayout>
</template>
