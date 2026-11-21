<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    records: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const form = reactive({
    date: props.filters.date,
    search: props.filters.search ?? '',
});

const formatMinutes = (minutes) => {
    const value = Number(minutes ?? 0);
    const hours = Math.floor(value / 60);
    const remainder = value % 60;
    return hours ? `${hours}h ${remainder}m` : `${remainder}m`;
};

const applyFilters = () => {
    router.get('/attendance/records', form, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const pagination = computed(() => props.records.links ?? []);
</script>

<template>
    <Head title="Attendance records" />

    <CompanyLayout title="Attendance records" subtitle="Employee attendance records">
        <section class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">WORKFORCE MANAGEMENT</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Attendance records</h1>
                <p class="mt-2 text-sm text-slate-500">Daily attendance details for employees in this company.</p>
            </div>
            <a href="/attendance" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Back to overview</a>
        </section>

        <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_2fr_auto]" @submit.prevent="applyFilters">
                <label class="text-sm font-medium text-slate-600">
                    Attendance date
                    <input v-model="form.date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-cyan-600 focus:ring-cyan-600" />
                </label>
                <label class="text-sm font-medium text-slate-600">
                    Search employee
                    <input v-model="form.search" type="search" placeholder="Name or employee number" class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-cyan-600 focus:ring-cyan-600" />
                </label>
                <button type="submit" class="self-end rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Apply filters</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="font-bold text-slate-900">Daily attendance</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ records.total }} record(s) found</p>
                </div>
            </div>

            <div v-if="records.data.length" class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Employee</th>
                            <th class="px-5 py-3 font-semibold">Department / Branch</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Worked</th>
                            <th class="px-5 py-3 font-semibold">Break</th>
                            <th class="px-5 py-3 font-semibold">Late</th>
                            <th class="px-5 py-3 font-semibold">Early leave</th>
                            <th class="px-5 py-3 font-semibold">Overtime</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="record in records.data" :key="record.id" class="hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-800">{{ record.employee?.full_name ?? 'Employee unavailable' }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ record.employee?.employee_number ?? '—' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <p>{{ record.employee?.department ?? '—' }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ record.employee?.branch ?? '—' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ record.status }}</span>
                            </td>
                            <td class="px-5 py-4 tabular-nums text-slate-700">{{ formatMinutes(record.total_worked_minutes) }}</td>
                            <td class="px-5 py-4 tabular-nums text-slate-700">{{ formatMinutes(record.total_break_minutes) }}</td>
                            <td class="px-5 py-4 tabular-nums text-slate-700">{{ formatMinutes(record.late_minutes) }}</td>
                            <td class="px-5 py-4 tabular-nums text-slate-700">{{ formatMinutes(record.early_leave_minutes) }}</td>
                            <td class="px-5 py-4 tabular-nums text-slate-700">{{ formatMinutes(record.overtime_minutes) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="px-6 py-14 text-center">
                <h3 class="font-semibold text-slate-800">No attendance records found</h3>
                <p class="mt-2 text-sm text-slate-500">Try another date or adjust your employee search.</p>
            </div>

            <div v-if="pagination.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                <template v-for="(link, index) in pagination" :key="index">
                    <button
                        v-if="link.url"
                        type="button"
                        class="rounded-lg border px-3 py-2 text-xs font-medium"
                        :class="link.active ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                        @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                        v-html="link.label"
                    />
                    <span v-else class="rounded-lg border border-slate-100 px-3 py-2 text-xs text-slate-300" v-html="link.label" />
                </template>
            </div>
        </section>
    </CompanyLayout>
</template>
