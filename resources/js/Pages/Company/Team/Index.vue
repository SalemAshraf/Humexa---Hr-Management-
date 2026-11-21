<script setup>
import { t } from '@/lib/i18n';
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({
    accounts: { type: Array, required: true },
    roles: { type: Array, required: true },
});

const form = useForm({ name: '', email: '', password: '', role: 'Employee' });
const submit = () => form.post('/team', { preserveScroll: true, onSuccess: () => form.reset('name', 'email', 'password') });
const setActive = (a, active) => router.put(`/team/${a.id}/active`, { active }, { preserveScroll: true });
const resetFor = ref(null);
const resetForm = useForm({ password: '' });
const resetAccess = (a) => resetForm.put(`/team/${a.id}/reset-access`, { preserveScroll: true, onSuccess: () => { resetFor.value = null; resetForm.reset(); } });
const changeRole = (account, role) => router.put(`/team/${account.id}/role`, { role }, { preserveScroll: true });
const label = (p) => p.split('.').map((x) => t(x)).join(' · ');
</script>

<template>
    <Head :title="$t('Team and roles')" />
    <CompanyLayout :title="$t('Team')" :subtitle="$t('Accounts and roles')">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('ACCESS') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Team and roles') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $t('Each account has exactly one role. The role decides which modules and data it can open.') }}</p>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="r in roles" :key="r.name" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900">{{ $t(r.name) }}</h2>
                <p v-if="!r.permissions.length" class="mt-2 text-xs text-slate-500">{{ $t('No company-wide access. Self-service only.') }}</p>
                <p v-else class="mt-2 text-xs leading-5 text-slate-500">{{ r.permissions.map(label).join($t(', ')) }}</p>
            </article>
        </section>

        <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <ul class="divide-y divide-slate-100">
                <li v-for="a in accounts" :key="a.id" class="flex flex-col gap-2 p-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-slate-800">{{ a.name }} <span v-if="a.is_you" class="ml-1 rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-semibold text-cyan-800">{{ $t('You') }}</span></p>
                        <p class="text-xs text-slate-500">{{ a.email }}</p>
                        <p class="text-xs" :class="a.employee ? 'text-emerald-700' : 'text-slate-400'">
                            <a v-if="a.employee" :href="`/employees/${a.employee.id}`" class="underline">{{ $t('Linked to {name}', { name: a.employee.name }) }}</a>
                            <span v-else>{{ $t('Not linked to an employee. Link it from the employee\'s Edit page ("Login account").') }}</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                    <span v-if="!a.is_active" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $t('Deactivated') }}</span>
                    <template v-if="!a.is_you">
                        <button class="text-xs font-semibold text-cyan-700" @click="resetFor = resetFor === a.id ? null : a.id">{{ $t('Reset access') }}</button>
                        <button class="text-xs font-semibold" :class="a.is_active ? 'text-red-600' : 'text-emerald-700'" @click="setActive(a, !a.is_active)">{{ a.is_active ? $t('Deactivate') : $t('Reactivate') }}</button>
                    </template>
                    <select :value="a.role" :disabled="a.is_you" class="rounded-lg border-slate-300 text-sm disabled:bg-slate-50" @change="changeRole(a, $event.target.value)">
                        <option v-for="r in roles" :key="r.name" :value="r.name">{{ $t(r.name) }}</option>
                    </select>
                    </div>
                    <form v-if="resetFor === a.id" class="flex w-full flex-wrap items-center gap-2 sm:basis-full" @submit.prevent="resetAccess(a)">
                        <input v-model="resetForm.password" type="password" minlength="12" :placeholder="$t('New starting password (12+ characters)')" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm" required />
                        <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">{{ $t('Set password') }}</button>
                        <span v-if="resetForm.errors.password" class="w-full text-xs text-red-600">{{ resetForm.errors.password }}</span>
                    </form>
                </li>
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">{{ $t('Add an account') }}</h2>
            <form class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5" @submit.prevent="submit">
                <input v-model="form.name" :placeholder="$t('Full name')" class="rounded-lg border-slate-300 text-sm" />
                <input v-model="form.email" type="email" :placeholder="$t('Work email')" class="rounded-lg border-slate-300 text-sm" />
                <input v-model="form.password" type="password" :placeholder="$t('Starting password (12+)')" autocomplete="new-password" class="rounded-lg border-slate-300 text-sm" />
                <select v-model="form.role" class="rounded-lg border-slate-300 text-sm">
                    <option v-for="r in roles" :key="r.name" :value="r.name">{{ $t(r.name) }}</option>
                </select>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">{{ $t('Create') }}</button>
            </form>
            <p class="mt-2 text-xs text-slate-500">{{ $t('The person is forced to choose their own password the first time they sign in.') }}</p>
            <p v-for="(m, k) in form.errors" :key="k" class="mt-2 text-xs text-red-600">{{ $t(m) }}</p>
        </section>
    </CompanyLayout>
</template>
