<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({
    employees: { type: Object, required: true },
});

const page = usePage();

function restoreEmployee(employee) {
    const name = [employee.first_name, employee.middle_name, employee.last_name].filter(Boolean).join(' ');
    if (!window.confirm('Restore ' + name + ' (' + employee.employee_number + ')?')) return;

    router.post('/employees/' + employee.id + '/restore', {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="$t('Archived employees')" />
    <CompanyLayout :title="$t('Archived employees')" :subtitle="$t('Review and restore archived employee profiles')">
        <div v-if="page.props.flash?.success" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
            {{ $t(page.props.flash.success) }}
        </div>

        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-700">{{ $t('People management') }}</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ $t('Archived employees') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $t('Archived profiles are retained and can be restored by authorized tenant users.') }}</p>
            </div>
            <Link href="/employees" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">{{ $t('Back to employees') }}</Link>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ $t('Employee') }}</th>
                            <th class="px-4 py-3">{{ $t('Employee No.') }}</th>
                            <th class="px-4 py-3">{{ $t('Department / Position') }}</th>
                            <th class="px-4 py-3">{{ $t('Archived at') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="employee in employees.data" :key="employee.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ [employee.first_name, employee.middle_name, employee.last_name].filter(Boolean).join(' ') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ employee.work_email || $t('No work email') }}</p>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-600">{{ employee.employee_number }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-700">{{ employee.department?.name || '—' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ employee.position?.name || $t('No position') }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ employee.deleted_at }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="rounded-lg bg-cyan-700 px-3 py-2 text-xs font-semibold text-white hover:bg-cyan-800" @click="restoreEmployee(employee)">{{ $t('Restore') }}</button>
                            </td>
                        </tr>
                        <tr v-if="!employees.data.length">
                            <td colspan="5" class="px-4 py-12 text-center">
                                <p class="font-semibold text-slate-700">{{ $t('No archived employees') }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $t('Employees you archive will appear here.') }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="employees.links?.length > 3" class="flex flex-wrap justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">{{ $t('Showing {a}–{b} of {c}', { a: employees.from || 0, b: employees.to || 0, c: employees.total }) }}</p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="(link, index) in employees.links" :key="index">
                        <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-lg border px-3 py-1.5 text-xs" :class="link.active ? 'border-cyan-600 bg-cyan-50 font-bold text-cyan-800' : 'border-slate-200 text-slate-600'" v-html="link.label" />
                        <span v-else class="rounded-lg border border-slate-100 px-3 py-1.5 text-xs text-slate-300" v-html="link.label" />
                    </template>
                </div>
            </div>
        </section>
    </CompanyLayout>
</template>
