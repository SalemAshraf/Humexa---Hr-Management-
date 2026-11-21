<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({
    accounts: { type: Array, required: true },
    roles: { type: Array, required: true },
});

const form = useForm({ name: '', email: '', password: '', role: 'Employee' });
const submit = () => form.post('/team', { preserveScroll: true, onSuccess: () => form.reset('name', 'email', 'password') });
const changeRole = (account, role) => router.put(`/team/${account.id}/role`, { role }, { preserveScroll: true });
const label = (p) => p.replace('.', ' · ');
</script>

<template>
    <Head title="Team and roles" />
    <CompanyLayout title="Team" subtitle="Accounts and roles">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">ACCESS</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Team and roles</h1>
            <p class="mt-2 text-sm text-slate-500">Each account has exactly one role. The role decides which modules and data it can open.</p>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="r in roles" :key="r.name" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900">{{ r.name }}</h2>
                <p v-if="!r.permissions.length" class="mt-2 text-xs text-slate-500">No company-wide access. Self-service only.</p>
                <p v-else class="mt-2 text-xs leading-5 text-slate-500">{{ r.permissions.map(label).join(', ') }}</p>
            </article>
        </section>

        <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <ul class="divide-y divide-slate-100">
                <li v-for="a in accounts" :key="a.id" class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-slate-800">{{ a.name }} <span v-if="a.is_you" class="ml-1 rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-semibold text-cyan-800">You</span></p>
                        <p class="text-xs text-slate-500">{{ a.email }}</p>
                    </div>
                    <select :value="a.role" :disabled="a.is_you" class="rounded-lg border-slate-300 text-sm disabled:bg-slate-50" @change="changeRole(a, $event.target.value)">
                        <option v-for="r in roles" :key="r.name" :value="r.name">{{ r.name }}</option>
                    </select>
                </li>
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Add an account</h2>
            <form class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5" @submit.prevent="submit">
                <input v-model="form.name" placeholder="Full name" class="rounded-lg border-slate-300 text-sm" />
                <input v-model="form.email" type="email" placeholder="Work email" class="rounded-lg border-slate-300 text-sm" />
                <input v-model="form.password" type="password" placeholder="Temporary password (12+)" autocomplete="new-password" class="rounded-lg border-slate-300 text-sm" />
                <select v-model="form.role" class="rounded-lg border-slate-300 text-sm">
                    <option v-for="r in roles" :key="r.name" :value="r.name">{{ r.name }}</option>
                </select>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">Create</button>
            </form>
            <p v-for="(m, k) in form.errors" :key="k" class="mt-2 text-xs text-red-600">{{ m }}</p>
        </section>
    </CompanyLayout>
</template>
