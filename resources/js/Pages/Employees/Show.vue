<script setup>
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({ employee: { type: Object, required: true } });
const page = usePage();
const statusLabel = (value) => (value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
function archive(employee) {
    if (!window.confirm('Archive this employee profile?')) return;
    useForm({}).delete('/employees/' + employee.id);
}
</script>

<template>
    <Head :title="employee.first_name + ' ' + employee.last_name" />
    <CompanyLayout title="Employee profile" subtitle="Employee details and employment history">
        <div v-if="page.props.flash?.success" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ page.props.flash.success }}</div>
        <section class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">EMPLOYEE PROFILE</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ employee.first_name }} {{ employee.middle_name }} {{ employee.last_name }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ employee.employee_number }} · {{ statusLabel(employee.employment_status) }}</p>
            </div>
            <div class="flex gap-2">
                <Link :href="'/employees/' + employee.id + '/edit'" class="rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white">Edit employee</Link>
                <button type="button" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600" @click="archive(employee)">Archive</button>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <h2 class="font-bold text-slate-900">Personal information</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Work email</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.work_email || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Phone</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.phone || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Date of birth</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.date_of_birth || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Gender</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ statusLabel(employee.gender) || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Login account</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.user?.email || 'Not linked' }}</dd></div>
                </dl>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">Organization</h2>
                <dl class="mt-4 space-y-4">
                    <div><dt class="text-xs text-slate-500">Branch</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.branch?.name || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Department</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.department?.name || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Position</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.position?.name || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Direct manager</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.manager ? employee.manager.first_name + ' ' + employee.manager.last_name : '—' }}</dd></div>
                </dl>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <h2 class="font-bold text-slate-900">Employment information</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Hire date</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.hire_date || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Probation end date</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.probation_end_date || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Termination date</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.termination_date || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Termination reason</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ employee.termination_reason || '—' }}</dd></div>
                </dl>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">Employment history</h2>
                <ol class="mt-4 space-y-4">
                    <li v-for="history in employee.employment_histories" :key="history.id" class="border-s border-slate-200 ps-4">
                        <p class="text-sm font-semibold text-slate-800">{{ statusLabel(history.new_status) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ history.effective_date || history.created_at }}</p>
                        <p v-if="history.reason" class="mt-1 text-xs text-slate-600">{{ history.reason }}</p>
                        <p v-if="history.changed_by" class="mt-1 text-xs text-slate-400">By {{ history.changed_by.name }}</p>
                    </li>
                </ol>
            </section>
        </div>
        <div class="mt-5"><Link href="/employees" class="text-sm font-semibold text-cyan-700">← Back to employees</Link></div>
    </CompanyLayout>
</template>
