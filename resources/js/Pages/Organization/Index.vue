<script setup>
import { t } from '@/lib/i18n';
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});
const page = usePage();
const canEdit = () => !!page.props.can?.['organization.manage'];
const tab = ref('branches');
const editing = ref({ type: null, id: null });
const branch = useForm({ name: '', code: '', city: '', address: '', is_active: true });
const department = useForm({ branch_id: '', name: '', code: '', description: '', is_active: true });
const position = useForm({ department_id: '', name: '', code: '', description: '', is_active: true });

function clear(type = tab.value) {
    editing.value = { type: null, id: null };
    ({ branches: branch, departments: department, positions: position })[type].reset();
    ({ branches: branch, departments: department, positions: position })[type].clearErrors();
}
function edit(type, item) {
    tab.value = type;
    editing.value = { type, id: item.id };
    const values = type === 'branches'
        ? { name: item.name, code: item.code || '', city: item.city || '', address: item.address || '', is_active: item.is_active }
        : type === 'departments'
            ? { branch_id: item.branch_id || '', name: item.name, code: item.code || '', description: item.description || '', is_active: item.is_active }
            : { department_id: item.department_id, name: item.name, code: item.code || '', description: item.description || '', is_active: item.is_active };
    const form = ({ branches: branch, departments: department, positions: position })[type];
    form.defaults(values);
    form.reset();
    form.clearErrors();
}
function submit(type) {
    const form = ({ branches: branch, departments: department, positions: position })[type];
    const id = editing.value.type === type ? editing.value.id : null;
    const options = { preserveScroll: true, onSuccess: () => clear(type) };
    if (id) form.put('/organization/' + type + '/' + id, options);
    else form.post('/organization/' + type, options);
}
function archive(type, id) {
    const label = type.slice(0, -1);
    if (!window.confirm(t('Archive this {label}?', { label: t(label) }))) return;
    useForm({}).delete('/organization/' + type + '/' + id, { preserveScroll: true });
}
const tabItems = [
    { key: 'branches', label: 'Branches', short: 'BR' },
    { key: 'departments', label: 'Departments', short: 'DE' },
    { key: 'positions', label: 'Positions', short: 'PO' },
];
</script>

<template>
    <Head :title="$t('Organization')" />
    <CompanyLayout :title="$t('Organization')" :subtitle="$t('Manage your company structure')">
        <div v-if="page.props.flash?.success" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ $t(page.props.flash.success) }}</div>
        <section class="mb-7">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('COMPANY STRUCTURE') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Organization') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $t('Set up branches, departments and job positions for your workforce.') }}</p>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <article v-for="item in [{label:'Branches',value:summary.branches,code:'BR'},{label:'Departments',value:summary.departments,code:'DE'},{label:'Positions',value:summary.positions,code:'PO'}]" :key="item.code" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">{{ $t(item.label) }}</span><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-50 text-xs font-bold text-cyan-800">{{ item.code }}</span></div>
                <p class="mt-3 text-2xl font-bold text-slate-900">{{ item.value || 0 }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap gap-2 border-b border-slate-200 p-4">
                <button v-for="item in tabItems" :key="item.key" type="button" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold" :class="tab === item.key ? 'bg-cyan-50 text-cyan-800 ring-1 ring-cyan-200' : 'text-slate-500 hover:bg-slate-50'" @click="tab = item.key; clear(item.key)">
                    <span class="text-xs font-bold">{{ item.short }}</span>{{ $t(item.label) }}
                </button>
            </div>

            <div class="p-5 sm:p-6">
                <template v-if="tab === 'branches'">
                    <h2 class="text-lg font-bold text-slate-900">{{ editing.id ? $t('Edit branch') : $t('Add branch') }}</h2>
                    <form v-if="canEdit()" class="mb-8 mt-4 grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submit('branches')">
                        <label class="text-sm font-medium text-slate-700">{{ $t('Branch name') }}<input v-model="branch.name" required maxlength="150" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('Alexandria HQ')"><span v-if="branch.errors.name" class="mt-1 block text-xs text-red-600">{{ branch.errors.name }}</span></label>
                        <label class="text-sm font-medium text-slate-700">{{ $t('Code') }}<input v-model="branch.code" maxlength="50" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('ALX-01')"><span v-if="branch.errors.code" class="mt-1 block text-xs text-red-600">{{ branch.errors.code }}</span></label>
                        <label class="text-sm font-medium text-slate-700">{{ $t('City') }}<input v-model="branch.city" maxlength="100" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('Alexandria')"></label>
                        <label class="text-sm font-medium text-slate-700">{{ $t('Address') }}<input v-model="branch.address" maxlength="255" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('Street and building')"></label>
                        <label class="flex items-center gap-2 text-sm text-slate-600 md:col-span-2"><input v-model="branch.is_active" type="checkbox" class="rounded border-slate-300 text-cyan-600">{{ $t('Active branch') }}</label>
                        <div class="flex gap-2 md:col-span-2"><button :disabled="branch.processing" class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ editing.id ? $t('Save changes') : $t('Create branch') }}</button><button v-if="editing.id" type="button" class="rounded-xl border px-5 py-2.5 text-sm" @click="clear()">{{ $t('Cancel') }}</button></div>
                    </form>
                    <div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">{{ $t('Branch') }}</th><th class="px-4 py-3">{{ $t('Code') }}</th><th class="px-4 py-3">{{ $t('City') }}</th><th class="px-4 py-3">{{ $t('Departments') }}</th><th class="px-4 py-3">{{ $t('Status') }}</th><th class="px-4 py-3 text-right">{{ $t('Actions') }}</th></tr></thead><tbody class="divide-y divide-slate-100">
                        <tr v-for="item in branches" :key="item.id"><td class="px-4 py-3 font-semibold">{{ item.name }}</td><td class="px-4 py-3 text-slate-500">{{ item.code || '—' }}</td><td class="px-4 py-3 text-slate-500">{{ item.city || '—' }}</td><td class="px-4 py-3">{{ item.departments_count }}</td><td class="px-4 py-3">{{ item.is_active ? $t('Active') : $t('Inactive') }}</td><td class="px-4 py-3 text-right"><button v-if="canEdit()" class="mr-3 font-semibold text-cyan-700" @click="edit('branches', item)">{{ $t('Edit') }}</button><button v-if="canEdit()" class="font-semibold text-red-600" @click="archive('branches', item.id)">{{ $t('Archive') }}</button></td></tr>
                        <tr v-if="!branches.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">{{ $t('No branches yet. Add your first branch above.') }}</td></tr>
                    </tbody></table></div>
                </template>

                <template v-else-if="tab === 'departments'">
                    <h2 class="text-lg font-bold text-slate-900">{{ editing.id ? $t('Edit department') : $t('Add department') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $t('A department may belong to a branch or remain company-wide.') }}</p>
                    <form v-if="canEdit()" class="mb-8 mt-4 grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submit('departments')">
                        <label class="text-sm font-medium text-slate-700">{{ $t('Department name') }}<input v-model="department.name" required maxlength="150" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('Human Resources')"><span v-if="department.errors.name" class="mt-1 block text-xs text-red-600">{{ department.errors.name }}</span></label>
                        <label class="text-sm font-medium text-slate-700">Branch (optional)<select v-model="department.branch_id" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="">{{ $t('Company-wide') }}</option><option v-for="item in branches" :key="item.id" :value="item.id">{{ item.name }}</option></select><span v-if="department.errors.branch_id" class="mt-1 block text-xs text-red-600">{{ department.errors.branch_id }}</span></label>
                        <label class="text-sm font-medium text-slate-700">{{ $t('Code') }}<input v-model="department.code" maxlength="50" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('HR')"><span v-if="department.errors.code" class="mt-1 block text-xs text-red-600">{{ department.errors.code }}</span></label>
                        <label class="text-sm font-medium text-slate-700">{{ $t('Description') }}<input v-model="department.description" maxlength="2000" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('Department responsibilities')"></label>
                        <label class="flex items-center gap-2 text-sm text-slate-600 md:col-span-2"><input v-model="department.is_active" type="checkbox" class="rounded border-slate-300 text-cyan-600">{{ $t('Active department') }}</label>
                        <div class="flex gap-2 md:col-span-2"><button :disabled="department.processing" class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ editing.id ? $t('Save changes') : $t('Create department') }}</button><button v-if="editing.id" type="button" class="rounded-xl border px-5 py-2.5 text-sm" @click="clear()">{{ $t('Cancel') }}</button></div>
                    </form>
                    <div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">{{ $t('Department') }}</th><th class="px-4 py-3">{{ $t('Branch') }}</th><th class="px-4 py-3">{{ $t('Code') }}</th><th class="px-4 py-3">{{ $t('Positions') }}</th><th class="px-4 py-3">{{ $t('Status') }}</th><th class="px-4 py-3 text-right">{{ $t('Actions') }}</th></tr></thead><tbody class="divide-y divide-slate-100">
                        <tr v-for="item in departments" :key="item.id"><td class="px-4 py-3 font-semibold">{{ item.name }}</td><td class="px-4 py-3">{{ item.branch?.name || $t('Company-wide') }}</td><td class="px-4 py-3">{{ item.code || '—' }}</td><td class="px-4 py-3">{{ item.positions_count }}</td><td class="px-4 py-3">{{ item.is_active ? $t('Active') : $t('Inactive') }}</td><td class="px-4 py-3 text-right"><button v-if="canEdit()" class="mr-3 font-semibold text-cyan-700" @click="edit('departments', item)">{{ $t('Edit') }}</button><button v-if="canEdit()" class="font-semibold text-red-600" @click="archive('departments', item.id)">{{ $t('Archive') }}</button></td></tr>
                        <tr v-if="!departments.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">{{ $t('No departments yet.') }}</td></tr>
                    </tbody></table></div>
                </template>

                <template v-else>
                    <h2 class="text-lg font-bold text-slate-900">{{ editing.id ? $t('Edit position') : $t('Add position') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $t('Define job titles under the relevant department.') }}</p>
                    <form v-if="canEdit()" class="mb-8 mt-4 grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submit('positions')">
                        <label class="text-sm font-medium text-slate-700">{{ $t('Position name') }}<input v-model="position.name" required maxlength="150" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('HR Specialist')"><span v-if="position.errors.name" class="mt-1 block text-xs text-red-600">{{ position.errors.name }}</span></label>
                        <label class="text-sm font-medium text-slate-700">{{ $t('Department') }}<select v-model="position.department_id" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="" disabled>{{ $t('Select department') }}</option><option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option></select><span v-if="position.errors.department_id" class="mt-1 block text-xs text-red-600">{{ position.errors.department_id }}</span></label>
                        <label class="text-sm font-medium text-slate-700">{{ $t('Code') }}<input v-model="position.code" maxlength="50" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('HR-SPEC')"><span v-if="position.errors.code" class="mt-1 block text-xs text-red-600">{{ position.errors.code }}</span></label>
                        <label class="text-sm font-medium text-slate-700">{{ $t('Description') }}<input v-model="position.description" maxlength="2000" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('Role responsibilities')"></label>
                        <label class="flex items-center gap-2 text-sm text-slate-600 md:col-span-2"><input v-model="position.is_active" type="checkbox" class="rounded border-slate-300 text-cyan-600">{{ $t('Active position') }}</label>
                        <div class="flex gap-2 md:col-span-2"><button :disabled="position.processing" class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ editing.id ? $t('Save changes') : $t('Create position') }}</button><button v-if="editing.id" type="button" class="rounded-xl border px-5 py-2.5 text-sm" @click="clear()">{{ $t('Cancel') }}</button></div>
                    </form>
                    <div class="overflow-x-auto"><table class="w-full min-w-[600px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">{{ $t('Position') }}</th><th class="px-4 py-3">{{ $t('Department') }}</th><th class="px-4 py-3">{{ $t('Code') }}</th><th class="px-4 py-3">{{ $t('Status') }}</th><th class="px-4 py-3 text-right">{{ $t('Actions') }}</th></tr></thead><tbody class="divide-y divide-slate-100">
                        <tr v-for="item in positions" :key="item.id"><td class="px-4 py-3 font-semibold">{{ item.name }}</td><td class="px-4 py-3">{{ item.department?.name || '—' }}</td><td class="px-4 py-3">{{ item.code || '—' }}</td><td class="px-4 py-3">{{ item.is_active ? $t('Active') : $t('Inactive') }}</td><td class="px-4 py-3 text-right"><button v-if="canEdit()" class="mr-3 font-semibold text-cyan-700" @click="edit('positions', item)">{{ $t('Edit') }}</button><button v-if="canEdit()" class="font-semibold text-red-600" @click="archive('positions', item.id)">{{ $t('Archive') }}</button></td></tr>
                        <tr v-if="!positions.length"><td colspan="5" class="px-4 py-10 text-center text-slate-400">{{ $t('No positions yet.') }}</td></tr>
                    </tbody></table></div>
                </template>
            </div>
        </section>
    </CompanyLayout>
</template>
