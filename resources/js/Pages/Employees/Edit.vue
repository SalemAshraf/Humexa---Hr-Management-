<script setup>
import { Head } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';
import EmployeeForm from '@/Components/Employees/EmployeeForm.vue';

defineProps({
    employee: Object,
    branches: Array,
    departments: Array,
    positions: Array,
    managers: Array,
    accounts: Array,
    statuses: Array,
});
</script>

<template>
    <Head :title="'Edit ' + employee.first_name + ' ' + employee.last_name" />
    <CompanyLayout title="Edit employee" subtitle="Update employee profile">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">PEOPLE MANAGEMENT</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Edit employee</h1>
            <p class="mt-2 text-sm text-slate-500">{{ employee.employee_number }} · {{ employee.first_name }} {{ employee.last_name }}</p>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <EmployeeForm v-bind="$props" />
        </section>
    </CompanyLayout>
</template>
