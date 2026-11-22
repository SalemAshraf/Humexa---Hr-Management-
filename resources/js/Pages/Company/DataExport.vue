<script setup>
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ exports: Array });
</script>

<template>
    <Head :title="$t('Export data')" />
    <CompanyLayout :title="$t('Export data')" :subtitle="$t('Download your records as CSV')">
        <p class="mb-5 max-w-2xl text-sm text-slate-600">{{ $t('Download your company records as CSV files. Exports include only basic fields. They never include ID numbers, addresses, documents, salaries or sign-in details.') }}</p>
        <p v-if="!exports.length" class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">{{ $t('You do not have permission to export any records.') }}</p>
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="e in exports" :key="e.key" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="font-semibold text-slate-800">{{ $t(e.label) }}</span>
                <a :href="`/data-export/${e.key}`" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">{{ $t('Download CSV') }}</a>
            </li>
        </ul>
    </CompanyLayout>
</template>
