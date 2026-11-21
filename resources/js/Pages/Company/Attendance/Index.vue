<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    date: { type: String, required: true },
    employees: { type: Object, required: true },
    stats: { type: Object, required: true },
    policy: { type: Object, required: true },
});

const formattedDate = computed(() => new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date(`${props.date}T00:00:00`)));

function formatTime(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatDuration(minutes) {
    const value = Number(minutes || 0);
    const hours = Math.floor(value / 60);
    const remainder = value % 60;

    if (!hours) return `${remainder}m`;
    if (!remainder) return `${hours}h`;

    return `${hours}h ${remainder}m`;
}

function attendanceState(employee) {
    const state = employee.attendance?.state;

    if (state === 'checked_in') return { label: 'Checked in', classes: 'bg-emerald-50 text-emerald-700 ring-emerald-200' };
    if (state === 'checked_out') return { label: 'Checked out', classes: 'bg-slate-100 text-slate-600 ring-slate-200' };

    return { label: 'No record yet', classes: 'bg-amber-50 text-amber-700 ring-amber-200' };
}

function latestSession(employee) {
    return employee.attendance?.sessions?.at(-1) ?? null;
}
</script>

<template>
    <Head title="Attendance" />

    <CompanyLayout title="Attendance" subtitle="Daily workforce attendance">
        <section class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">WORKFORCE MANAGEMENT</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Attendance overview</h1>
                <p class="mt-2 text-sm text-slate-500">Track today's attendance activity across your organization.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-xs font-medium text-slate-500">Attendance date</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ formattedDate }}</p>
            </div>
        </section>

        <section class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Active employees</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ stats.total_employees }}</p>
                <p class="mt-2 text-xs text-slate-400">Employees excluding resigned and terminated</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Attendance records</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ stats.with_records }}</p>
                <p class="mt-2 text-xs text-slate-400">Employees with a record today</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Currently checked in</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-emerald-700">{{ stats.currently_checked_in }}</p>
                <p class="mt-2 text-xs text-slate-400">Employees with an open work session</p>
            </article>
        </section>

        <section class="mb-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-slate-900">Attendance policy</h2>
                    <p class="mt-1 text-sm text-slate-500">The policy currently applied to this company.</p>
                </div>
                <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-800">Tenant policy</span>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="item in [
                    { label: 'Mobile check-in', enabled: policy.mobile_checkin_enabled },
                    { label: 'Manual attendance', enabled: policy.manual_attendance_enabled },
                    { label: 'GPS verification', enabled: policy.location_verification_enabled },
                    { label: 'Selfie verification', enabled: policy.selfie_verification_enabled },
                    { label: 'Attendance corrections', enabled: policy.attendance_correction_enabled },
                    { label: 'Overtime approval required', enabled: policy.overtime_requires_approval },
                ]" :key="item.label" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3">
                    <span class="text-sm text-slate-600">{{ item.label }}</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="item.enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'">
                        {{ item.enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
            </div>

            <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                This page is the company attendance overview. Employee self check-in and break actions remain on the authenticated mobile API; manual attendance entry and approval workflows are not exposed here yet.
            </p>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col justify-between gap-2 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
                <div>
                    <h2 class="font-bold text-slate-900">Employee attendance</h2>
                    <p class="mt-1 text-sm text-slate-500">Daily sessions and recorded working time.</p>
                </div>
                <span class="text-xs font-medium text-slate-400">{{ employees.total }} employees</span>
            </div>

            <div v-if="employees.data.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Department</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Last check-in</th>
                            <th class="px-5 py-3">Last check-out</th>
                            <th class="px-5 py-3">Worked</th>
                            <th class="px-5 py-3">Breaks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="employee in employees.data" :key="employee.id" class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-4">
                                <p class="text-sm font-semibold text-slate-800">{{ employee.name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ employee.employee_number || '—' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ employee.department || '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    :class="attendanceState(employee).classes">
                                    {{ attendanceState(employee).label }}
                                </span>
                                <p v-if="latestSession(employee)?.active_break" class="mt-1 text-xs font-medium text-amber-600">On break</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm tabular-nums text-slate-600">{{ formatTime(latestSession(employee)?.check_in_at) }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm tabular-nums text-slate-600">{{ formatTime(latestSession(employee)?.check_out_at) }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold tabular-nums text-slate-700">{{ formatDuration(employee.attendance.worked_minutes) }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm tabular-nums text-slate-600">{{ formatDuration(employee.attendance.break_minutes) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="px-6 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-sm font-bold text-slate-500">AT</div>
                <h3 class="mt-4 text-sm font-semibold text-slate-800">No employees found</h3>
                <p class="mt-1 text-sm text-slate-500">Add employees to your workspace to see attendance here.</p>
                <Link href="/employees/create" class="mt-4 inline-flex text-sm font-semibold text-cyan-700 hover:text-cyan-800">Add an employee</Link>
            </div>

            <div v-if="employees.links?.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                <p class="text-xs text-slate-500">Showing {{ employees.from }}–{{ employees.to }} of {{ employees.total }}</p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in employees.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                            class="rounded-lg px-3 py-2 text-xs font-semibold"
                            :class="link.active ? 'bg-cyan-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            v-html="link.label" />
                        <span v-else class="rounded-lg px-3 py-2 text-xs text-slate-300" v-html="link.label" />
                    </template>
                </div>
            </div>
        </section>
    </CompanyLayout>
</template>
