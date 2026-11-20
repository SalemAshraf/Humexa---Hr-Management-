<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    employees: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});
const page = usePage();
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
let searchTimer;
watch([search, status], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => router.get('/employees', {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true }), 300);
});
onBeforeUnmount(() => clearTimeout(searchTimer));
const statusLabel = (value) => (value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const paginationLinks = computed(() => props.employees.links || []);
</script>

<template>
    <Head title="Employees" />
    <CompanyLayout title="Employees" subtitle="Manage your workforce">
        <div v-if="page.props.flash?.success" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ page.props.flash.success }}</div>
        <section class="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">PEOPLE MANAGEMENT</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Employees</h1>
                <p class="mt-2 text-sm text-slate-500">Manage employee profiles, employment status and organization assignments.</p>
            </div>
            <Link href="/employees/create" class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-cyan-700">+ Add employee</Link>
        </section>

        <section class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
            <article v-for="item in [{label:'Total employees',value:summary.total,code:'ALL'},{label:'Active',value:summary.active,code:'ACT'},{label:'Probation',value:summary.probation,code:'PRO'},{label:'On leave',value:summary.on_leave,code:'LEV'}]" :key="item.code" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">{{ item.label }}</span><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-50 text-xs font-bold text-cyan-800">{{ item.code }}</span></div>
                <p class="mt-3 text-2xl font-bold text-slate-900">{{ item.value || 0 }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row">
                <input v-model="search" type="search" placeholder="Search name, employee number or email..." class="w-full rounded-xl border-slate-300 text-sm sm:max-w-md">
                <select v-model="status" class="rounded-xl border-slate-300 text-sm sm:w-56">
                    <option value="">All statuses</option>
                    <option v-for="item in ['draft','probation','active','on_leave','suspended','resigned','terminated']" :key="item" :value="item">{{ statusLabel(item) }}</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Employee No.</th><th class="px-4 py-3">Department / Position</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in employees.data" :key="item.id">
                            <td class="px-4 py-3"><Link :href="'/employees/' + item.id" class="font-semibold text-slate-900 hover:text-cyan-700">{{ item.first_name }} {{ item.middle_name }} {{ item.last_name }}</Link><p class="mt-1 text-xs text-slate-500">{{ item.work_email || 'No work email' }}</p></td>
                            <td class="px-4 py-3 font-medium text-slate-600">{{ item.employee_number }}</td>
                            <td class="px-4 py-3"><span class="font-medium text-slate-700">{{ item.department?.name || '—' }}</span><p class="mt-1 text-xs text-slate-500">{{ item.position?.name || 'No position' }}</p></td>
                            <td class="px-4 py-3 text-slate-600">{{ item.branch?.name || '—' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ statusLabel(item.employment_status) }}</span></td>
                            <td class="px-4 py-3 text-right"><Link :href="'/employees/' + item.id + '/edit'" class="font-semibold text-cyan-700">Edit</Link></td>
                        </tr>
                        <tr v-if="!employees.data.length"><td colspan="6" class="px-4 py-12 text-center"><p class="font-semibold text-slate-700">No employees found</p><p class="mt-1 text-sm text-slate-500">Add your first employee or adjust the search filters.</p><Link href="/employees/create" class="mt-4 inline-flex rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white">Add employee</Link></td></tr>
                    </tbody>
                </table>
            </div>
            <div v-if="paginationLinks.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">Showing {{ employees.from || 0 }}–{{ employees.to || 0 }} of {{ employees.total }}</p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="(link, index) in paginationLinks" :key="index">
                        <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-lg border px-3 py-1.5 text-xs" :class="link.active ? 'border-cyan-600 bg-cyan-50 font-bold text-cyan-800' : 'border-slate-200 text-slate-600'" v-html="link.label" />
                        <span v-else class="rounded-lg border border-slate-100 px-3 py-1.5 text-xs text-slate-300" v-html="link.label" />
                    </template>
                </div>
            </div>
        </section>
    </CompanyLayout>
</template>
