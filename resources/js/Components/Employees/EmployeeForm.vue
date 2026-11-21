<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    employee: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    managers: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const form = useForm({
    employee_number: props.employee?.employee_number || '',
    user_id: props.employee?.user_id || '',
    first_name: props.employee?.first_name || '',
    middle_name: props.employee?.middle_name || '',
    last_name: props.employee?.last_name || '',
    work_email: props.employee?.work_email || '',
    personal_email: props.employee?.personal_email || '',
    phone: props.employee?.phone || '',
    alternate_phone: props.employee?.alternate_phone || '',
    date_of_birth: props.employee?.date_of_birth?.slice(0, 10) || '',
    gender: props.employee?.gender || '',
    nationality: props.employee?.nationality || '',
    marital_status: props.employee?.marital_status || '',
    hire_date: props.employee?.hire_date?.slice(0, 10) || '',
    probation_end_date: props.employee?.probation_end_date?.slice(0, 10) || '',
    employment_status: props.employee?.employment_status || 'draft',
    termination_date: props.employee?.termination_date?.slice(0, 10) || '',
    termination_reason: props.employee?.termination_reason || '',
    branch_id: props.employee?.branch_id || '',
    department_id: props.employee?.department_id || '',
    position_id: props.employee?.position_id || '',
    manager_id: props.employee?.manager_id || '',
    status_change_reason: '',
});

const filteredDepartments = computed(() => props.departments.filter((item) =>
    !form.branch_id || !item.branch_id || String(item.branch_id) === String(form.branch_id)
));
const filteredPositions = computed(() => props.positions.filter((item) =>
    !form.department_id || String(item.department_id) === String(form.department_id)
));

watch(() => form.branch_id, (branchId) => {
    if (!form.department_id) return;
    const selectedDepartment = props.departments.find((item) => String(item.id) === String(form.department_id));
    if (selectedDepartment?.branch_id && String(selectedDepartment.branch_id) !== String(branchId)) {
        form.department_id = '';
        form.position_id = '';
    }
});

watch(() => form.department_id, (departmentId) => {
    if (!form.position_id) return;
    const selectedPosition = props.positions.find((item) => String(item.id) === String(form.position_id));
    if (selectedPosition && String(selectedPosition.department_id) !== String(departmentId)) {
        form.position_id = '';
    }
});

function submit() {
    if (props.employee) form.put('/employees/' + props.employee.id);
    else form.post('/employees');
}
</script>

<template>
    <form class="space-y-7" @submit.prevent="submit">
        <section>
            <h2 class="text-base font-bold text-slate-900">{{ $t('Personal information') }}</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="text-sm font-medium text-slate-700">{{ $t('First name *') }}<input v-model="form.first_name" required maxlength="100" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.first_name" class="mt-1 block text-xs text-red-600">{{ form.errors.first_name }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Middle name') }}<input v-model="form.middle_name" maxlength="100" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.middle_name" class="mt-1 block text-xs text-red-600">{{ form.errors.middle_name }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Last name *') }}<input v-model="form.last_name" required maxlength="100" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.last_name" class="mt-1 block text-xs text-red-600">{{ form.errors.last_name }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Work email') }}<input v-model="form.work_email" type="email" maxlength="190" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.work_email" class="mt-1 block text-xs text-red-600">{{ form.errors.work_email }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Phone') }}<input v-model="form.phone" maxlength="30" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.phone" class="mt-1 block text-xs text-red-600">{{ form.errors.phone }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Alternate phone') }}<input v-model="form.alternate_phone" maxlength="30" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.alternate_phone" class="mt-1 block text-xs text-red-600">{{ form.errors.alternate_phone }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Personal email') }}<input v-model="form.personal_email" type="email" maxlength="190" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.personal_email" class="mt-1 block text-xs text-red-600">{{ form.errors.personal_email }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Date of birth') }}<input v-model="form.date_of_birth" type="date" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.date_of_birth" class="mt-1 block text-xs text-red-600">{{ form.errors.date_of_birth }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Gender') }}<select v-model="form.gender" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                        <option value="">{{ $t('Not specified') }}</option><option value="male">{{ $t('Male') }}</option><option value="female">{{ $t('Female') }}</option><option value="other">{{ $t('Other') }}</option><option value="prefer_not_to_say">{{ $t('Prefer not to say') }}</option>
                    </select>
                    <span v-if="form.errors.gender" class="mt-1 block text-xs text-red-600">{{ form.errors.gender }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Nationality') }}<input v-model="form.nationality" maxlength="100" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.nationality" class="mt-1 block text-xs text-red-600">{{ form.errors.nationality }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Marital status') }}<select v-model="form.marital_status" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                        <option value="">{{ $t('Not specified') }}</option><option value="single">{{ $t('Single') }}</option><option value="married">{{ $t('Married') }}</option><option value="divorced">{{ $t('Divorced') }}</option><option value="widowed">{{ $t('Widowed') }}</option><option value="other">{{ $t('Other') }}</option>
                    </select>
                    <span v-if="form.errors.marital_status" class="mt-1 block text-xs text-red-600">{{ form.errors.marital_status }}</span>
                </label>
            </div>
        </section>

        <section class="border-t border-slate-100 pt-6">
            <h2 class="text-base font-bold text-slate-900">{{ $t('Employment details') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $t('Leave Employee Number empty to generate it automatically (EMP-0001).') }}</p>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="text-sm font-medium text-slate-700">{{ $t('Employee number') }}<input v-model="form.employee_number" maxlength="50" :placeholder="$t('Auto-generated')" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.employee_number" class="mt-1 block text-xs text-red-600">{{ form.errors.employee_number }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Employment status *') }}<select v-model="form.employment_status" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                        <option v-for="item in statuses" :key="item.value" :value="item.value">{{ $t(item.label) }}</option>
                    </select>
                    <span v-if="form.errors.employment_status" class="mt-1 block text-xs text-red-600">{{ form.errors.employment_status }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Hire date') }}<span v-if="form.employment_status !== 'draft'">*</span>
                    <input v-model="form.hire_date" type="date" :required="form.employment_status !== 'draft'" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.hire_date" class="mt-1 block text-xs text-red-600">{{ form.errors.hire_date }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Probation end date') }}<input v-model="form.probation_end_date" type="date" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.probation_end_date" class="mt-1 block text-xs text-red-600">{{ form.errors.probation_end_date }}</span>
                </label>
                <label v-if="['resigned', 'terminated'].includes(form.employment_status)" class="text-sm font-medium text-slate-700">{{ $t('Termination date *') }}<input v-model="form.termination_date" type="date" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm">
                    <span v-if="form.errors.termination_date" class="mt-1 block text-xs text-red-600">{{ form.errors.termination_date }}</span>
                </label>
                <label v-if="['resigned', 'terminated'].includes(form.employment_status)" class="text-sm font-medium text-slate-700 md:col-span-2">{{ $t('Termination reason') }}<textarea v-model="form.termination_reason" rows="2" maxlength="5000" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></textarea>
                    <span v-if="form.errors.termination_reason" class="mt-1 block text-xs text-red-600">{{ form.errors.termination_reason }}</span>
                </label>
                <label v-if="employee" class="text-sm font-medium text-slate-700 md:col-span-2">{{ $t('Status change reason') }}<textarea v-model="form.status_change_reason" rows="2" maxlength="5000" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm" :placeholder="$t('Optional reason for changing employment status')"></textarea>
                    <span v-if="form.errors.status_change_reason" class="mt-1 block text-xs text-red-600">{{ form.errors.status_change_reason }}</span>
                </label>
            </div>
        </section>

        <section class="border-t border-slate-100 pt-6">
            <h2 class="text-base font-bold text-slate-900">{{ $t('Organization assignment') }}</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="text-sm font-medium text-slate-700">{{ $t('Branch') }}<select v-model="form.branch_id" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="">{{ $t('Not assigned') }}</option><option v-for="item in branches" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    <span v-if="form.errors.branch_id" class="mt-1 block text-xs text-red-600">{{ form.errors.branch_id }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Department') }}<select v-model="form.department_id" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="">{{ $t('Not assigned') }}</option><option v-for="item in filteredDepartments" :key="item.id" :value="item.id">{{ item.name }}{{ item.branch?.name ? ' — ' + item.branch.name : ' — Company-wide' }}</option></select>
                    <span v-if="form.errors.department_id" class="mt-1 block text-xs text-red-600">{{ form.errors.department_id }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Position') }}<select v-model="form.position_id" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="">{{ $t('Not assigned') }}</option><option v-for="item in filteredPositions" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    <span v-if="form.errors.position_id" class="mt-1 block text-xs text-red-600">{{ form.errors.position_id }}</span>
                </label>
                <label class="text-sm font-medium text-slate-700">{{ $t('Direct manager') }}<select v-model="form.manager_id" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"><option value="">{{ $t('No manager') }}</option><option v-for="item in managers.filter((manager) => manager.id !== employee?.id)" :key="item.id" :value="item.id">{{ item.first_name }} {{ item.last_name }} · {{ item.employee_number }}</option></select>
                    <span v-if="form.errors.manager_id" class="mt-1 block text-xs text-red-600">{{ form.errors.manager_id }}</span>
                </label>
            </div>
        </section>

        <section class="border-t border-slate-100 pt-6">
            <h2 class="font-bold text-slate-900">{{ $t('Login account') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $t('Optional. An employee can exist without an Afrad login.') }}</p>
            <label class="mt-4 block text-sm font-medium text-slate-700">{{ $t('Link tenant user account') }}<select v-model="form.user_id" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm md:max-w-xl">
                    <option value="">{{ $t('No login account') }}</option><option v-for="item in accounts" :key="item.id" :value="item.id">{{ item.name }} ({{ item.email }})</option>
                    <option v-if="employee?.user" :value="employee.user_id">{{ employee.user.name }} ({{ employee.user.email }})</option>
                </select>
                <span v-if="form.errors.user_id" class="mt-1 block text-xs text-red-600">{{ form.errors.user_id }}</span>
            </label>
        </section>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6">
            <button :disabled="form.processing" class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50">{{ form.processing ? 'Saving...' : employee ? 'Save changes' : 'Create employee' }}</button>
            <a href="/employees" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">{{ $t('Cancel') }}</a>
        </div>
        <p v-if="form.hasErrors" class="text-sm text-red-600" role="alert">{{ $t('Please review the highlighted fields.') }}</p>
    </form>
</template>
