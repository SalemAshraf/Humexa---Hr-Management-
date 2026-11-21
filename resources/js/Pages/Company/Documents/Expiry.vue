<script setup>
import { t } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({ rows: { type: Array, required: true }, window_days: { type: Number, required: true } });

const label = (r) => (r.expired ? t('Expired {n} day(s) ago', { n: Math.abs(r.days) }) : r.days === 0 ? t('Expires today') : t('In {n} day(s)', { n: r.days }));
</script>

<template>
    <Head :title="$t('Document expiry')" />

    <CompanyLayout :title="$t('Document expiry')" :subtitle="$t('Employee documents that need renewal')">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('DOCUMENTS') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Document expiry') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $t('Expired documents and those expiring within {n} days. Archived documents are not listed. Open the employee to upload a renewed copy.', { n: window_days }) }}</p>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-testid="expiry-list">
            <ul class="divide-y divide-slate-100">
                <li v-for="r in rows" :key="r.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800">{{ r.title }} <span v-if="r.type" class="text-xs font-normal text-slate-400">({{ r.type }})</span></p>
                        <Link :href="`/employees/${r.employee_id}`" class="text-sm text-cyan-700 hover:underline">{{ r.employee }}<span v-if="r.employee_number" class="text-slate-400"> · {{ r.employee_number }}</span></Link>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold" :class="r.expired ? 'text-red-600' : 'text-amber-600'">{{ label(r) }}</p>
                        <p class="text-xs text-slate-500">{{ r.expiry_date }}</p>
                    </div>
                </li>
                <li v-if="!rows.length" class="p-10 text-center text-sm text-slate-500">{{ $t('No documents are expired or expiring soon.') }}</li>
            </ul>
        </section>
    </CompanyLayout>
</template>
