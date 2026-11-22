<script setup>
import { t } from '@/lib/i18n';
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';
import { formatDate } from '@/lib/date';
import EmployeeProfilePhoto from '@/Components/Employees/EmployeeProfilePhoto.vue';
import EmployeeRecordsPanel from '@/Components/Employees/EmployeeRecordsPanel.vue';

const props = defineProps({
    employee: { type: Object, required: true },
    canUpdate: { type: Boolean, default: false },
    hasPhoto: { type: Boolean, default: false },
});

const page = usePage();
const activeTab = ref('overview');
const baseTabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'personal', label: 'Personal' },
    { key: 'organization', label: 'Organization' },
    { key: 'employment', label: 'Employment' },
    { key: 'addresses', label: 'Addresses' },
    { key: 'contacts', label: 'Emergency contacts' },
    { key: 'identity', label: 'Identity' },
    { key: 'documents', label: 'Documents' },
];
// Personal-data tabs are HR only on the server; hide them for everyone else.
const personalDataTabs = ['addresses', 'contacts', 'identity', 'documents'];
const tabs = computed(() => baseTabs.filter((t) => !personalDataTabs.includes(t.key) || page.props.can?.['employees.manage']));

const fullName = computed(() => [
    props.employee.first_name,
    props.employee.middle_name,
    props.employee.last_name,
].filter(Boolean).join(' '));

const statusLabel = (value) => t((value || 'unknown')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase()));

const statusClasses = computed(() => {
    const styles = {
        active: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        probation: 'bg-amber-50 text-amber-700 ring-amber-200',
        on_leave: 'bg-blue-50 text-blue-700 ring-blue-200',
        suspended: 'bg-orange-50 text-orange-700 ring-orange-200',
        terminated: 'bg-red-50 text-red-700 ring-red-200',
        resigned: 'bg-slate-100 text-slate-700 ring-slate-200',
        draft: 'bg-violet-50 text-violet-700 ring-violet-200',
    };

    return styles[props.employee.employment_status] || 'bg-slate-100 text-slate-700 ring-slate-200';
});

function archiveEmployee() {
    const message = `Archive ${fullName.value} (${props.employee.employee_number})? This will remove the employee from the active employee list.`;

    if (!window.confirm(message)) return;

    router.delete(`/employees/${props.employee.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="fullName + ' · Employee profile'" />

    <CompanyLayout :title="$t('Employee profile')" :subtitle="$t('Employee information and records')">
        <div v-if="page.props.flash?.success" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
            {{ $t(page.props.flash.success) }}
        </div>

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <Link href="/employees" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-cyan-700">
                <span aria-hidden="true">←</span>{{ $t('All employees') }}</Link>
            <div class="flex items-center gap-2">
                <Link href="/employees" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">{{ $t('Directory') }}</Link>
                <Link v-if="canUpdate" :href="'/employees/' + employee.id + '/edit'" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-800">{{ $t('Edit profile') }}</Link>
            </div>
        </div>

        <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="h-2 bg-gradient-to-r from-cyan-700 via-sky-500 to-teal-400"></div>
            <div class="flex flex-col gap-6 p-5 sm:flex-row sm:items-center sm:p-7">
                <EmployeeProfilePhoto
                    :employee-id="employee.id"
                    :employee-name="fullName"
                    :can-update="canUpdate"
                    :has-photo="hasPhoto"
                />

                <div class="min-w-0 flex-1 text-center sm:text-start">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-700">{{ $t('Employee profile') }}</p>
                    <h1 class="mt-2 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ fullName }}</h1>
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                        <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold tracking-wide text-slate-700">{{ employee.employee_number || $t('No employee number') }}</span>
                        <span class="rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ring-inset" :class="statusClasses">{{ statusLabel(employee.employment_status) }}</span>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">
                        {{ employee.position?.name || $t('Position not assigned') }}
                        <span v-if="employee.department?.name"> · {{ employee.department.name }}</span>
                        <span v-if="employee.branch?.name"> · {{ employee.branch.name }}</span>
                    </p>
                </div>

                <div v-if="canUpdate" class="flex justify-center sm:justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                        @click.stop.prevent="archiveEmployee"
                    >{{ $t('Archive employee') }}</button>
                </div>
            </div>
        </section>

        <nav class="mb-5 flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm" :aria-label="$t('Employee profile sections')">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="shrink-0 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                :class="activeTab === tab.key ? 'bg-cyan-700 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                :aria-current="activeTab === tab.key ? 'page' : undefined"
                @click="activeTab = tab.key"
            >
                {{ $t(tab.label) }}
            </button>
        </nav>

        <section v-if="activeTab === 'overview'" class="grid grid-cols-1 gap-5 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">{{ $t('At a glance') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('Employee summary') }}</h2>
                    </div>
                    <span class="rounded-lg bg-cyan-50 px-2.5 py-1.5 text-xs font-semibold text-cyan-800">{{ $t('Overview') }}</span>
                </div>
                <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-medium text-slate-500">{{ $t('Work email') }}</dt><dd class="mt-1 break-all text-sm font-semibold text-slate-800">{{ employee.work_email || '—' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">{{ $t('Phone') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.phone || '—' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">{{ $t('Department') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.department?.name || '—' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">{{ $t('Position') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.position?.name || '—' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">{{ $t('Branch') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.branch?.name || '—' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">{{ $t('Hire date') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ formatDate(employee.hire_date) }}</dd></div>
                </dl>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">{{ $t('Account') }}</p>
                <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('System access') }}</h2>
                <div class="mt-5 rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-medium text-slate-500">{{ $t('Linked login account') }}</p>
                    <p class="mt-1 break-all text-sm font-semibold text-slate-800">{{ employee.user?.email || $t('Not linked') }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ $t('A login account can be linked to this employee when access to the company workspace is required.') }}</p>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">{{ $t('Audit trail') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('Employment history') }}</h2>
                    </div>
                    <span class="text-xs text-slate-400">{{ $t('{n} records', { n: employee.employment_histories?.length || 0 }) }}</span>
                </div>
                <ol v-if="employee.employment_histories?.length" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <li v-for="history in employee.employment_histories" :key="history.id" class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-sm font-bold text-slate-800">{{ statusLabel(history.new_status) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ formatDate(history.effective_date || history.created_at) }}</p>
                        <p v-if="history.reason" class="mt-2 text-sm text-slate-600">{{ $t(history.reason) }}</p>
                        <p v-if="history.changed_by" class="mt-2 text-xs text-slate-400">{{ $t('Updated by {name}', { name: history.changed_by.name }) }}</p>
                    </li>
                </ol>
                <p v-else class="mt-5 rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">{{ $t('No employment history has been recorded yet.') }}</p>
            </article>
        </section>

        <section v-else-if="activeTab === 'personal'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">{{ $t('Personal details') }}</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('Personal information') }}</h2>
            <dl class="mt-6 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('First name') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.first_name || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Middle name') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.middle_name || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Last name') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.last_name || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Work email') }}</dt><dd class="mt-1 break-all text-sm font-semibold text-slate-800">{{ employee.work_email || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Personal email') }}</dt><dd class="mt-1 break-all text-sm font-semibold text-slate-800">{{ employee.personal_email || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Phone') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.phone || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Alternate phone') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.alternate_phone || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Date of birth') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.date_of_birth || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Gender') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ statusLabel(employee.gender) }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Nationality') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.nationality || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Marital status') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ statusLabel(employee.marital_status) }}</dd></div>
            </dl>
        </section>

        <section v-else-if="activeTab === 'organization'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">{{ $t('Company structure') }}</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('Organization assignment') }}</h2>
            <dl class="mt-6 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Branch') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.branch?.name || $t('Not assigned') }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Department') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.department?.name || $t('Not assigned') }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Position') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.position?.name || $t('Not assigned') }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Direct manager') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.manager ? [employee.manager.first_name, employee.manager.last_name].filter(Boolean).join(' ') : $t('Not assigned') }}</dd></div>
            </dl>
        </section>

        <section v-else-if="activeTab === 'employment'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">{{ $t('Employment details') }}</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('Employment information') }}</h2>
            <dl class="mt-6 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Employee number') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.employee_number || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Employment status') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ statusLabel(employee.employment_status) }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Hire date') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ formatDate(employee.hire_date) }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Probation end date') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.probation_end_date || '—' }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">{{ $t('Termination date') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ employee.termination_date || '—' }}</dd></div>
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-medium text-slate-500">{{ $t('Termination reason') }}</dt><dd class="mt-1 whitespace-pre-line text-sm font-semibold text-slate-800">{{ employee.termination_reason || '—' }}</dd></div>
            </dl>
        </section>

        <EmployeeRecordsPanel
            v-if="['addresses', 'contacts', 'identity', 'documents'].includes(activeTab)"
            :employee-id="employee.id"
            :can-update="canUpdate"
            :section="activeTab"
        />
    </CompanyLayout>
</template>
