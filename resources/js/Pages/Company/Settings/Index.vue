<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    weekend_days: { type: Array, required: true },
    timezones: { type: Array, required: true },
});

const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const form = useForm({ ...props.settings });
const save = () => form.put('/settings', { preserveScroll: true });
</script>

<template>
    <Head :title="$t('Settings')" />

    <CompanyLayout :title="$t('Settings')" :subtitle="$t('Company preferences')">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('COMPANY') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Settings') }}</h1>
        </section>

        <section v-if="$page.props.planUsage" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700" aria-label="Plan">
            <p class="font-bold text-slate-900">{{ $t('Your plan') }}: <bdi>{{ $page.props.planUsage.plan ? $page.props.planUsage.plan.name : $t('No plan assigned (no employee/account limits)') }}</bdi></p>
            <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                <div class="rounded-lg bg-slate-50 px-3 py-2"><dt class="text-xs text-slate-500">{{ $t('Employees') }}</dt><dd class="font-semibold"><bdi dir="ltr">{{ $page.props.planUsage.employees }}</bdi> {{ $t('used') }} · {{ $t('limit') }}: <bdi dir="ltr">{{ $page.props.planUsage.plan?.max_employees ?? $t('No limit') }}</bdi></dd></div>
                <div class="rounded-lg bg-slate-50 px-3 py-2"><dt class="text-xs text-slate-500">{{ $t('Sign-in accounts') }}</dt><dd class="font-semibold"><bdi dir="ltr">{{ $page.props.planUsage.accounts }}</bdi> {{ $t('used') }} · {{ $t('limit') }}: <bdi dir="ltr">{{ $page.props.planUsage.plan?.max_accounts ?? $t('No limit') }}</bdi></dd></div>
            </dl>
            <p class="mt-1 text-xs text-slate-500">{{ $t('Limits apply only to adding new employees and accounts. Nothing is hidden or removed. Billing is not set up yet.') }}</p>
        </section>

        <p v-if="$page.props.flash?.status" class="mb-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ $page.props.flash.status }}</p>

        <form class="mb-6 grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2" @submit.prevent="save">
            <label class="text-xs font-medium text-slate-500 md:col-span-2">{{ $t('Company name shown in the app') }}<input v-model="form.display_name" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
            <label class="text-xs font-medium text-slate-500">{{ $t('Time zone') }}<select v-model="form.timezone" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option v-for="t in timezones" :key="t" :value="t">{{ t }}</option></select>
            </label>
            <label class="text-xs font-medium text-slate-500">{{ $t('Display currency code') }}<input v-model="form.currency" maxlength="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm uppercase" required /></label>
            <label class="text-xs font-medium text-slate-500">{{ $t('Work day starts') }}<input v-model="form.work_start" type="time" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
            <label class="text-xs font-medium text-slate-500">{{ $t('Work day ends') }}<input v-model="form.work_end" type="time" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
            <label class="text-xs font-medium text-slate-500">{{ $t('HR contact email') }}<input v-model="form.contact_email" type="email" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
            <label class="text-xs font-medium text-slate-500">{{ $t('HR contact phone') }}<input v-model="form.contact_phone" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
            <div class="md:col-span-2">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">{{ $t('Save settings') }}</button>
            </div>
            <p v-for="(m, k) in form.errors" :key="k" class="text-xs text-red-600 md:col-span-2">{{ m }}</p>
        </form>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">{{ $t('Weekend and leave rules') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $t('Weekend') }}: {{ weekend_days.map((d) => $t(days[d])).join($t(' and ')) }}. {{ $t('Change it in') }} <Link href="/leave" class="font-semibold text-cyan-700">{{ $t('Leaves') }}</Link>. {{ $t('Payroll rules are in') }} <Link href="/payroll" class="font-semibold text-cyan-700">{{ $t('Payroll') }}</Link>.</p>
            <p class="mt-2 text-xs text-slate-500">{{ $t('The mobile attendance policy API sends the time zone and work hours to the app. Stored attendance times and past records are not changed, and the web attendance screens do not convert times yet. Payroll always uses EGP and does not read the currency here.') }}</p>
        </section>
    </CompanyLayout>
</template>
