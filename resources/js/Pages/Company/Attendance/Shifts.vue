<script setup>
import { reactive } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({
    shifts: { type: Array, required: true },
    employees: { type: Array, required: true },
});

const page = usePage();
const form = reactive({ name: '', start_time: '09:00', end_time: '17:00', grace_minutes: '' });
const input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm';
const create = () =>
    router.post('/attendance/shifts', { ...form, grace_minutes: form.grace_minutes === '' ? null : form.grace_minutes }, { preserveScroll: true, onSuccess: () => { form.name = ''; } });
const remove = (s) => router.delete(`/attendance/shifts/${s.id}`, { preserveScroll: true });
const assign = (e, value) => router.put('/attendance/shifts/assign', { employee_id: e.id, shift_id: value === '' ? null : Number(value) }, { preserveScroll: true });
</script>

<template>
    <Head :title="$t('Work shifts')" />
    <CompanyLayout :title="$t('Work shifts')" :subtitle="$t('Optional start and end times')">
        <section class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('ATTENDANCE') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Work shifts') }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $t('A check-in after the shift start plus the grace time is counted as late. Overnight shifts are supported: if the end is before the start, the shift ends the next day. Nobody is assigned automatically, and employees without a shift are not affected. Shifts do not change pay.') }}</p>
            </div>
            <a href="/attendance" class="text-sm font-semibold text-cyan-700">{{ $t('Back to attendance') }}</a>
        </section>

        <p v-if="page.props.errors?.shift" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $t(page.props.errors.shift) }}</p>

        <form class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-5" data-testid="shift-form" @submit.prevent="create">
            <input v-model="form.name" :class="input" maxlength="80" required :placeholder="$t('Shift name')" :aria-label="$t('Shift name')" />
            <input v-model="form.start_time" :class="input" type="time" required :aria-label="$t('Start')" />
            <input v-model="form.end_time" :class="input" type="time" required :aria-label="$t('End')" />
            <input v-model="form.grace_minutes" :class="input" type="number" min="0" max="240" :placeholder="$t('Grace minutes (optional)')" :aria-label="$t('Grace minutes (optional)')" />
            <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white">{{ $t('Add shift') }}</button>
            <p v-if="page.props.errors?.name || page.props.errors?.end_time" class="text-sm text-red-600 sm:col-span-2 lg:col-span-5">{{ page.props.errors.name || page.props.errors.end_time }}</p>
        </form>

        <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-testid="shift-list">
            <p v-if="!shifts.length" class="p-5 text-sm text-slate-500">{{ $t('No shifts yet.') }}</p>
            <ul class="divide-y divide-slate-100">
                <li v-for="s in shifts" :key="s.id" class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-slate-800"><bdi>{{ s.name }}</bdi></p>
                        <p class="text-xs text-slate-500"><bdi dir="ltr">{{ s.start_time }} - {{ s.end_time }}</bdi> · {{ s.grace_minutes === null ? $t('Company grace time') : $t('Grace {n} min', { n: s.grace_minutes }) }} · {{ $t('{n} employees', { n: s.employees }) }}</p>
                    </div>
                    <button class="text-sm font-semibold text-red-600" @click="remove(s)">{{ $t('Delete') }}</button>
                </li>
            </ul>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-testid="shift-assign">
            <h2 class="border-b border-slate-100 px-5 py-4 text-base font-bold text-slate-900">{{ $t('Who works which shift') }}</h2>
            <ul class="divide-y divide-slate-100">
                <li v-for="e in employees" :key="e.id" class="flex items-center justify-between gap-3 p-4">
                    <span class="min-w-0 truncate text-sm font-medium text-slate-800"><bdi>{{ e.name }}</bdi></span>
                    <select :value="e.shift_id ?? ''" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" :aria-label="$t('Shift')" @change="assign(e, $event.target.value)">
                        <option value="">{{ $t('No shift') }}</option>
                        <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </li>
            </ul>
        </section>
    </CompanyLayout>
</template>
