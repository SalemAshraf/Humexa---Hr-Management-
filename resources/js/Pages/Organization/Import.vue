<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    entity: { type: String, required: true },
    columns: { type: Array, default: () => [] },
    maxRows: { type: Number, default: 500 },
    report: { type: Object, default: null },
});

const isDept = props.entity === 'departments';
const form = useForm({ file: null, commit: false });
const send = (commit) => {
    form.commit = commit;
    form.post(`/organization/import/${props.entity}`, { forceFormData: true, preserveScroll: true });
};
</script>

<template>
    <Head :title="isDept ? $t('Import departments') : $t('Import positions')" />
    <CompanyLayout :title="isDept ? $t('Import departments') : $t('Import positions')" :subtitle="isDept ? $t('Add many departments from a CSV file') : $t('Add many positions from a CSV file')">
        <p class="mb-6 text-sm text-slate-500">{{ $t('Upload a CSV file. We check every row first, and nothing is saved until all rows are valid.') }}</p>

        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="font-bold text-slate-900">{{ $t('1. Get the template') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ isDept
                ? $t('Columns: {cols}. Required: name. Names and codes that already exist are errors, and nothing existing is changed or deleted. Up to {max} rows.', { cols: columns.join(', '), max: maxRows })
                : $t('Columns: {cols}. Required: name and department (it must already exist). Names and codes that already exist are errors, and nothing existing is changed or deleted. Up to {max} rows.', { cols: columns.join(', '), max: maxRows }) }}</p>
            <a :href="`/organization/import/${entity}/template`" class="mt-3 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-testid="import-template">{{ $t('Download template') }}</a>
        </section>

        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="font-bold text-slate-900">{{ $t('2. Check your file') }}</h2>
            <form class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center" @submit.prevent="send(false)">
                <input type="file" accept=".csv,text/csv" class="text-sm" data-testid="import-file" @change="form.file = $event.target.files[0]" />
                <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="!form.file || form.processing" data-testid="import-check">{{ $t('Check file') }}</button>
            </form>
            <p v-for="(m, k) in form.errors" :key="k" class="mt-2 text-sm text-red-600">{{ m }}</p>
        </section>

        <section v-if="report" class="mb-6 rounded-2xl border p-5 shadow-sm sm:p-6" :class="report.error || report.errors?.length ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50'" data-testid="import-report">
            <p v-if="report.error" class="text-sm font-semibold text-red-700">{{ report.error }}</p>
            <template v-else>
                <p v-if="report.imported" class="text-base font-bold text-emerald-800">{{ $t('{n} rows imported.', { n: report.valid }) }}</p>
                <p v-else class="text-base font-bold text-slate-900">{{ $t('{ok} of {total} rows are valid.', { ok: report.valid, total: report.total }) }}</p>
                <ul v-if="report.errors?.length" class="mt-3 space-y-2 text-sm text-red-700">
                    <li v-for="e in report.errors" :key="e.line"><span class="font-semibold">{{ $t('Line {n}', { n: e.line }) }}<span v-if="e.name"> ({{ e.name }})</span>:</span> {{ e.messages.join(' ') }}</li>
                </ul>
                <p v-if="report.errors?.length" class="mt-3 text-sm text-slate-600">{{ $t('Fix these rows in your file and check it again. Nothing was saved.') }}</p>
                <div v-if="!report.imported && !report.errors?.length && report.valid" class="mt-3">
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="!form.file || form.processing" data-testid="import-commit" @click="send(true)">{{ $t('Import {n} rows', { n: report.valid }) }}</button>
                    <p class="mt-2 text-xs text-slate-500">{{ $t('This uses the file you just checked. Choose it again if the page was reloaded.') }}</p>
                </div>
                <Link v-if="report.imported" href="/organization" class="mt-3 inline-flex rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white">{{ $t('View organization') }}</Link>
            </template>
        </section>
    </CompanyLayout>
</template>
