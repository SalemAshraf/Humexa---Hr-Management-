<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/Dashboard/PlatformLayout.vue';
import { t } from '@/lib/i18n';

defineProps({ items: { type: Array, required: true } });

const text = (i) => {
    switch (i.kind) {
        case 'new': return t('{company} registered on {date}.', { company: i.company, date: '\u2066' + i.date + '\u2069' });
        case 'suspended': return t('{company} is suspended.', { company: i.company });
        case 'sub_expired': return t('{company} paid-until date passed on {date}. The company is view and export only until you enter a later date.', { company: i.company, date: '\u2066' + i.date + '\u2069' });
        case 'sub_expiring': return t('{company} is paid until {date}. Ask them about renewal.', { company: i.company, date: '\u2066' + i.date + '\u2069' });
        case 'trial_ended': return t('{company} 7-day trial ended on {date} and no paid-until date is set. The company is view and export only until you enter one.', { company: i.company, date: '\u2066' + i.date + '\u2069' });
        case 'trial_ending': return t('{company} 7-day trial ends on {date}.', { company: i.company, date: '\u2066' + i.date + '\u2069' });
        case 'no_plan': return t('{company} has no plan assigned, so nothing limits it.', { company: i.company });
        case 'at_limit': return t('{company} reached its plan limit for {what}: {used} of {max}.', { company: i.company, what: t(i.what === 'employees' ? 'employees' : 'sign-in accounts'), used: i.used, max: i.max });
        default: return t('{company} is close to its plan limit for {what}: {used} of {max}.', { company: i.company, what: t(i.what === 'employees' ? 'employees' : 'sign-in accounts'), used: i.used, max: i.max });
    }
};
const tone = (k) => ({ trial_ended: 'text-red-300', trial_ending: 'text-amber-300', sub_expired: 'text-red-300', sub_expiring: 'text-amber-300', suspended: 'text-red-300', at_limit: 'text-amber-300', near_limit: 'text-amber-200', no_plan: 'text-slate-300', new: 'text-emerald-300' }[k]);
</script>

<template>
    <Head :title="$t('Notifications')" />
    <PlatformLayout :title="$t('Notifications')" :subtitle="$t('What needs attention')">
        <section class="mb-6">
            <p class="mb-2 text-sm font-medium text-cyan-400">{{ $t('PLATFORM ADMINISTRATION') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $t('Notifications') }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ $t('A live list built from current company data: new companies from the last 14 days, suspended companies, companies without a plan, and companies near or at a plan limit. Nothing is emailed.') }}</p>
        </section>
        <section class="hx-card overflow-hidden" data-testid="platform-notifications">
            <p v-if="!items.length" class="p-6 text-sm text-slate-400">{{ $t('Nothing needs attention right now.') }}</p>
            <ul class="divide-y divide-slate-800">
                <li v-for="(i, n) in items" :key="n" class="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm" :class="tone(i.kind)">{{ text(i) }}</p>
                    <Link :href="route('platform.companies.index')" class="text-sm font-semibold text-cyan-400">{{ $t('Open companies') }}</Link>
                </li>
            </ul>
        </section>
    </PlatformLayout>
</template>
