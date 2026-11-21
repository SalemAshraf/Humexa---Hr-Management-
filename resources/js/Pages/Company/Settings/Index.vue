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
    <Head title="Settings" />

    <CompanyLayout title="Settings" subtitle="Company preferences">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">COMPANY</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Settings</h1>
        </section>

        <p v-if="$page.props.flash?.status" class="mb-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ $page.props.flash.status }}</p>

        <form class="mb-6 grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2" @submit.prevent="save">
            <label class="text-xs font-medium text-slate-500 md:col-span-2">Company name shown in the app<input v-model="form.display_name" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
            <label class="text-xs font-medium text-slate-500">Time zone
                <select v-model="form.timezone" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option v-for="t in timezones" :key="t" :value="t">{{ t }}</option></select>
            </label>
            <label class="text-xs font-medium text-slate-500">Currency code<input v-model="form.currency" maxlength="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm uppercase" required /></label>
            <label class="text-xs font-medium text-slate-500">Work day starts<input v-model="form.work_start" type="time" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
            <label class="text-xs font-medium text-slate-500">Work day ends<input v-model="form.work_end" type="time" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
            <label class="text-xs font-medium text-slate-500">HR contact email<input v-model="form.contact_email" type="email" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
            <label class="text-xs font-medium text-slate-500">HR contact phone<input v-model="form.contact_phone" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
            <div class="md:col-span-2">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">Save settings</button>
            </div>
            <p v-for="(m, k) in form.errors" :key="k" class="text-xs text-red-600 md:col-span-2">{{ m }}</p>
        </form>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Weekend and leave rules</h2>
            <p class="mt-1 text-sm text-slate-600">Weekend: {{ weekend_days.map((d) => days[d]).join(' and ') }}. Change it in <Link href="/leave" class="font-semibold text-cyan-700">Leaves</Link>. Payroll rules are in <Link href="/payroll" class="font-semibold text-cyan-700">Payroll</Link>.</p>
            <p class="mt-2 text-xs text-slate-500">Time zone and work hours are saved for the company. Attendance and payroll do not read them yet.</p>
        </section>
    </CompanyLayout>
</template>
