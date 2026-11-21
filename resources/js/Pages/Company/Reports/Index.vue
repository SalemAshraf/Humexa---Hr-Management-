<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    scope: { type: String, required: true },
    sections: { type: Object, required: true },
    no_bars: { type: Array, default: () => [] },
    notes: { type: Object, default: () => ({}) },
});

const range = reactive({ from: props.from, to: props.to });
const apply = () => router.get('/reports', { ...range }, { preserveState: true, replace: true });
const csvUrl = () => `/reports/export?from=${range.from}&to=${range.to}`;
const show = (v) => (typeof v === 'number' ? v.toLocaleString('en-US') : v);
const max = (items) => Math.max(1, ...items.map((i) => Number(i.value) || 0));
</script>

<template>
    <Head title="Reports" />

    <CompanyLayout title="Reports" subtitle="People numbers for a period">
        <section class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">INSIGHTS</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Reports</h1>
                <p class="mt-2 text-sm text-slate-500">{{ scope === 'company' ? 'Whole company' : 'Your direct reports only' }}. Headcount shows today. Attendance and leave use the period.</p>
            </div>
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="apply">
                <label class="text-xs font-medium text-slate-500">From<input v-model="range.from" type="date" class="mt-1 rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">To<input v-model="range.to" type="date" class="mt-1 rounded-lg border-slate-300 text-sm" /></label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply</button>
                <a :href="csvUrl()" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200">Download CSV</a>
            </form>
        </section>

        <p v-for="(m, k) in $page.props.errors" :key="k" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ m }}</p>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section v-for="(items, name) in sections" :key="name" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">{{ name }}</h2>
                <p v-if="notes[name]" class="mt-1 text-xs text-slate-500">{{ notes[name] }}</p>
                <ul class="mt-3 space-y-3">
                    <li v-for="i in items" :key="i.label">
                        <div class="flex items-center justify-between text-sm"><span class="text-slate-600">{{ i.label }}</span><b class="text-slate-900">{{ show(i.value) }}</b></div>
                        <div v-if="!no_bars.includes(name)" class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-emerald-500" :style="{ width: `${Math.min(100, (Number(i.value) / max(items)) * 100)}%` }"></div></div>
                    </li>
                    <li v-if="!items.length" class="text-sm text-slate-500">Nothing to show yet.</li>
                </ul>
            </section>
        </div>
    </CompanyLayout>
</template>
