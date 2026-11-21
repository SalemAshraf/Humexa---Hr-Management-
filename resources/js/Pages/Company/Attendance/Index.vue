<script setup>
import { dateLocale } from '@/lib/i18n';
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    canViewRecords: { type: Boolean, default: false },
    canReviewRequests: { type: Boolean, default: false },
    date: { type: String, required: true },
    stats: { type: Object, required: true },
    policy: { type: Object, required: true },
});

const formattedDate = computed(() => new Intl.DateTimeFormat(dateLocale(), {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date(`${props.date}T00:00:00`)));
</script>

<template>
    <Head :title="$t('Attendance')" />

    <CompanyLayout :title="$t('Attendance')" :subtitle="$t('Daily workforce attendance')">
        <section class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('WORKFORCE MANAGEMENT') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Attendance overview') }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $t('A tenant-scoped snapshot of today\'s attendance activity.') }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-xs font-medium text-slate-500">{{ $t('Attendance date') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ formattedDate }}</p>
            </div>
        </section>

        <section class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ $t('Employees in scope') }}</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ stats.total_employees }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ $t('Excludes resigned and terminated employees') }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ $t('Attendance records') }}</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ stats.with_records }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ $t('Employees with a record today') }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ $t('Currently checked in') }}</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-emerald-700">{{ stats.currently_checked_in }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ $t('Employees with an open work session') }}</p>
            </article>
        </section>

        <section class="mb-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-slate-900">{{ $t('Attendance policy') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $t('The policy currently applied to this company.') }}</p>
                </div>
                <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-800">{{ $t('Tenant policy') }}</span>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="item in [
                    { label: 'Mobile check-in', enabled: policy.mobile_checkin_enabled },
                    { label: 'Manual attendance', enabled: policy.manual_attendance_enabled },
                    { label: 'GPS verification', enabled: policy.location_verification_enabled },
                    { label: 'Selfie verification', enabled: policy.selfie_verification_enabled },
                    { label: 'Attendance corrections', enabled: policy.attendance_correction_enabled },
                    { label: 'Overtime approval required', enabled: policy.overtime_requires_approval },
                ]" :key="item.label" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3">
                    <span class="text-sm text-slate-600">{{ $t(item.label) }}</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="item.enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'">
                        {{ item.enabled ? $t('Enabled') : $t('Disabled') }}
                    </span>
                </div>
            </div>
        </section>

        <section v-if="canReviewRequests" class="mb-4 flex flex-col justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center">
            <div>
                <h2 class="font-semibold text-slate-900">{{ $t('Corrections and overtime') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $t('Review requests employees sent from the mobile app.') }}</p>
            </div>
            <a href="/attendance/requests" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">{{ $t('Review requests') }}</a>
        </section>

        <section v-if="canViewRecords" class="mb-7 flex flex-col justify-between gap-3 rounded-2xl border border-cyan-200 bg-cyan-50 p-5 sm:flex-row sm:items-center">
            <div>
                <h2 class="font-semibold text-cyan-950">{{ $t('Employee attendance records') }}</h2>
                <p class="mt-1 text-sm text-cyan-800">{{ $t('Your account has permission to view detailed attendance records.') }}</p>
            </div>
            <a href="/attendance/records" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">{{ $t('View records') }}</a>
        </section>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-sm font-bold text-amber-700">i</span>
                <div>
                    <h2 class="font-semibold text-amber-900">{{ $t('Employee-level details are not enabled yet') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-amber-800">{{ $t('This overview shows company-level totals and policy. Detailed employee records are available only to tenant users assigned the attendance.view permission. Manual edits and approval actions remain separate protected workflows.') }}</p>
                </div>
            </div>
        </section>
    </CompanyLayout>
</template>
