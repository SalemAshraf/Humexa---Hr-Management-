<script setup>
import { Head } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';
import EmployeeForm from '@/Components/Employees/EmployeeForm.vue';

const props = defineProps({
    branches: Array,
    departments: Array,
    positions: Array,
    managers: Array,
    accounts: Array,
    statuses: Array,
});
</script>

<template>
    <Head :title="$t('Add employee')" />
    <CompanyLayout :title="$t('Add employee')" :subtitle="$t('Create a new employee profile')">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('PEOPLE MANAGEMENT') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Add employee') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $t('Create an employee profile and optionally link a login account.') }}</p>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <EmployeeForm v-bind="props" />
        </section>
    </CompanyLayout>
</template>
