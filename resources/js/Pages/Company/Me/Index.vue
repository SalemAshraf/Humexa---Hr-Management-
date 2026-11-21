<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({
    linked: { type: Boolean, required: true },
    profile: { type: Object, default: null },
    balances: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    requests: { type: Array, default: () => [] },
    attendance: { type: Array, default: () => [] },
    payslips: { type: Array, default: () => [] },
    year: { type: Number, default: 0 },
});

const form = useForm({ leave_type_id: '', start_date: '', end_date: '', reason: '' });
const submit = () => form.post('/me/leave', { preserveScroll: true, onSuccess: () => form.reset() });
const withdraw = (r) => confirm('Withdraw this pending request?') && router.post(`/me/leave/${r.id}/cancel`, {}, { preserveScroll: true });

const statusStyles = {
    pending: 'bg-amber-50 text-amber-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-700',
    cancelled: 'bg-slate-100 text-slate-500',
};
const hours = (m) => `${Math.floor((m ?? 0) / 60)}h ${String((m ?? 0) % 60).padStart(2, '0')}m`;
const money = (v) => `EGP ${Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
</script>

<template>
    <Head :title="$t('My space')" />

    <CompanyLayout :title="$t('My space')" :subtitle="$t('Your own records')">
        <section v-if="!linked" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" data-testid="me-unlinked">
            <h1 class="text-xl font-bold text-slate-900">{{ $t('No employee profile linked') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $t('Your sign-in is not linked to an employee record yet. Ask HR to link it, then your leave, attendance and payslip estimates will show here.') }}</p>
        </section>

        <template v-else>
            <section class="mb-6">
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('MY SPACE') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ profile.name }}</h1>
                <p class="mt-2 text-sm text-slate-500">
                    {{ [profile.position, profile.department, profile.employee_number && `#${profile.employee_number}`].filter(Boolean).join(' · ') || 'Employee' }}
                    <span v-if="profile.hire_date"> · {{ $t('Joined {date}', { date: profile.hire_date }) }}</span>
                </p>
            </section>

            <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="me-balances">
                <article v-for="b in balances" :key="b.leave_type_id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ b.name }} {{ year }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ b.remaining }} <span class="text-sm font-medium text-slate-500">{{ $t('days left') }}</span></p>
                    <p class="text-xs text-slate-500">{{ $t('{e} entitled · {u} used · {p} pending', { e: b.entitled, u: b.used, p: b.pending }) }}</p>
                </article>
            </section>

            <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">{{ $t('Request leave') }}</h2>
                <form class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5" @submit.prevent="submit">
                    <select v-model="form.leave_type_id" class="rounded-lg border-slate-300 text-sm" required>
                        <option value="" disabled>{{ $t('Leave type') }}</option>
                        <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                    <input v-model="form.start_date" type="date" class="rounded-lg border-slate-300 text-sm" required />
                    <input v-model="form.end_date" type="date" class="rounded-lg border-slate-300 text-sm" required />
                    <input v-model="form.reason" :placeholder="$t('Reason (optional)')" class="rounded-lg border-slate-300 text-sm" />
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">{{ $t('Send for approval') }}</button>
                    <p v-for="(m, k) in form.errors" :key="k" class="text-xs text-red-600 md:col-span-5">{{ m }}</p>
                </form>
            </section>

            <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h2 class="p-4 text-base font-bold text-slate-900">{{ $t('My leave requests') }}</h2>
                <p v-if="!requests.length" class="px-4 pb-4 text-sm text-slate-500">{{ $t('No requests yet.') }}</p>
                <ul class="divide-y divide-slate-100">
                    <li v-for="r in requests" :key="r.id" class="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold text-slate-800">{{ r.type }} · {{ $t('{n} day(s)', { n: r.days }) }}</p>
                            <p class="text-xs text-slate-500">{{ r.start_date }} to {{ r.end_date }}<span v-if="r.review_note"> · {{ r.review_note }}</span></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusStyles[r.status]">{{ $t(r.status) }}</span>
                            <button v-if="r.can_cancel" class="text-xs font-semibold text-slate-500 hover:text-red-600" @click="withdraw(r)">{{ $t('Withdraw') }}</button>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <h2 class="p-4 text-base font-bold text-slate-900">My attendance (last 30 records)</h2>
                <p v-if="!attendance.length" class="px-4 pb-4 text-sm text-slate-500">{{ $t('No attendance records yet.') }}</p>
                <ul class="divide-y divide-slate-100">
                    <li v-for="a in attendance" :key="a.date" class="flex items-center justify-between p-4 text-sm">
                        <span class="font-semibold text-slate-800">{{ a.date }}</span>
                        <span class="text-slate-500">{{ $t(a.status) }} · {{ hours(a.worked_minutes) }}<span v-if="a.late_minutes"> · {{ $t('late {n}m', { n: a.late_minutes }) }}</span><span v-if="a.overtime_minutes"> · {{ $t('overtime {n}m', { n: a.overtime_minutes }) }}</span></span>
                    </li>
                </ul>
            </section>

            <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm" data-testid="me-payslips">
                <h2 class="p-4 text-base font-bold text-slate-900">{{ $t('Payslip estimates') }}</h2>
                <p class="mx-4 mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ $t('These are estimates in EGP from an experimental calculator. They are not official payslips and may differ from what you are paid.') }}</p>
                <p v-if="!payslips.length" class="px-4 pb-4 text-sm text-slate-500">{{ $t('No locked estimate for you yet.') }}</p>
                <ul class="divide-y divide-slate-100">
                    <li v-for="p in payslips" :key="p.id" class="grid grid-cols-2 gap-2 p-4 text-sm sm:grid-cols-5">
                        <span class="font-semibold text-slate-800">{{ p.period }}</span>
                        <span class="text-slate-500">{{ $t('Gross') }} {{ money(p.gross) }}</span>
                        <span class="text-slate-500">{{ $t('Insurance') }} {{ money(p.insurance_employee) }}</span>
                        <span class="text-slate-500">{{ $t('Tax') }} {{ money(p.income_tax) }}</span>
                        <span class="font-semibold text-slate-900">{{ $t('Net') }} {{ money(p.net_pay) }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </CompanyLayout>
</template>
