<script setup>
import { reactive } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/Dashboard/PlatformLayout.vue';

const props = defineProps({
    plans: { type: Array, required: true },
    companies: { type: Array, required: true },
});

const page = usePage();
const blank = () => ({ name: '', max_employees: '', max_accounts: '', indicative_price_egp: '', notes: '', is_active: true });
const form = useForm(blank());
const editing = reactive({});

const clean = (v) => ({
    ...v,
    max_employees: v.max_employees === '' ? null : v.max_employees,
    max_accounts: v.max_accounts === '' ? null : v.max_accounts,
    indicative_price_egp: v.indicative_price_egp === '' ? null : v.indicative_price_egp,
});

const create = () => form.transform(clean).post(route('platform.plans.store'), { preserveScroll: true, onSuccess: () => form.reset() });
const startEdit = (p) => { editing[p.id] = { name: p.name, max_employees: p.max_employees ?? '', max_accounts: p.max_accounts ?? '', indicative_price_egp: p.indicative_price_egp ?? '', notes: p.notes ?? '', is_active: p.is_active }; };
const save = (p) => router.patch(route('platform.plans.update', p.id), clean(editing[p.id]), { preserveScroll: true, onSuccess: () => delete editing[p.id] });
const assign = (company, event) => router.patch(route('platform.companies.plan', company.id), { plan_id: event.target.value || null }, { preserveScroll: true });
const limit = (v) => (v === null || v === undefined ? null : v);
const input = 'rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-cyan-500 focus:outline-none';
</script>

<template>
    <Head :title="$t('Plans')" />
    <PlatformLayout :title="$t('Plans')" :subtitle="$t('Limits for new employees and accounts')">
        <section class="mb-6">
            <p class="mb-2 text-sm font-medium text-cyan-400">{{ $t('PLATFORM ADMINISTRATION') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $t('Plans') }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ $t('A plan limits how many employees and sign-in accounts a company can add. Existing data is never hidden or removed. Prices are for reference only; nothing is charged and there is no billing yet.') }}</p>
        </section>

        <div v-if="page.props.flash?.success" class="mb-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300" role="status">{{ page.props.flash.success }}</div>

        <section class="hx-card mb-8 overflow-x-auto p-5">
            <h2 class="mb-3 font-semibold text-white">{{ $t('Plans') }}</h2>
            <table class="w-full min-w-[640px] text-start text-sm text-slate-300">
                <thead class="text-xs text-slate-500">
                    <tr>
                        <th class="px-2 py-2 text-start">{{ $t('Name') }}</th>
                        <th class="px-2 py-2 text-start">{{ $t('Employees') }}</th>
                        <th class="px-2 py-2 text-start">{{ $t('Sign-in accounts') }}</th>
                        <th class="px-2 py-2 text-start">{{ $t('Reference price (EGP / month)') }}</th>
                        <th class="px-2 py-2 text-start">{{ $t('Status') }}</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in plans" :key="p.id" class="border-t border-slate-800 align-top">
                        <template v-if="editing[p.id]">
                            <td class="px-2 py-2"><input v-model="editing[p.id].name" :class="input" maxlength="80" /></td>
                            <td class="px-2 py-2"><input v-model="editing[p.id].max_employees" :class="input + ' w-24'" type="number" min="1" :placeholder="$t('No limit')" /></td>
                            <td class="px-2 py-2"><input v-model="editing[p.id].max_accounts" :class="input + ' w-24'" type="number" min="1" :placeholder="$t('No limit')" /></td>
                            <td class="px-2 py-2"><input v-model="editing[p.id].indicative_price_egp" :class="input + ' w-28'" type="number" min="0" /></td>
                            <td class="px-2 py-2"><label class="text-xs"><input v-model="editing[p.id].is_active" type="checkbox" /> {{ $t('Active') }}</label></td>
                            <td class="px-2 py-2"><button class="rounded-lg bg-cyan-500 px-3 py-1.5 text-xs font-bold text-slate-900" @click="save(p)">{{ $t('Save') }}</button></td>
                        </template>
                        <template v-else>
                            <td class="px-2 py-3 font-medium text-white">{{ p.name }}</td>
                            <td class="px-2 py-3">{{ limit(p.max_employees) ?? $t('No limit') }}</td>
                            <td class="px-2 py-3">{{ limit(p.max_accounts) ?? $t('No limit') }}</td>
                            <td class="px-2 py-3">{{ p.indicative_price_egp ?? '—' }}</td>
                            <td class="px-2 py-3">{{ p.is_active ? $t('Active') : $t('Inactive') }}</td>
                            <td class="px-2 py-3"><button class="text-xs font-semibold text-cyan-400" @click="startEdit(p)">{{ $t('Edit') }}</button></td>
                        </template>
                    </tr>
                </tbody>
            </table>

            <form class="mt-5 grid gap-3 border-t border-slate-800 pt-4 sm:grid-cols-5" @submit.prevent="create">
                <input v-model="form.name" :class="input" maxlength="80" required :placeholder="$t('New plan name')" />
                <input v-model="form.max_employees" :class="input" type="number" min="1" :placeholder="$t('Max employees')" />
                <input v-model="form.max_accounts" :class="input" type="number" min="1" :placeholder="$t('Max accounts')" />
                <input v-model="form.indicative_price_egp" :class="input" type="number" min="0" :placeholder="$t('Reference price (EGP / month)')" />
                <button type="submit" class="rounded-lg bg-cyan-500 px-3 py-2 text-sm font-bold text-slate-900" :disabled="form.processing">{{ $t('Add plan') }}</button>
                <p v-if="form.errors.name" class="text-xs text-red-300 sm:col-span-5">{{ form.errors.name }}</p>
            </form>
        </section>

        <section class="hx-card overflow-x-auto p-5">
            <h2 class="mb-1 font-semibold text-white">{{ $t('Company plans') }}</h2>
            <p class="mb-3 text-xs text-slate-500">{{ $t('A company with no plan has no limits. Changing a plan never removes anything a company already has.') }}</p>
            <table class="w-full min-w-[520px] text-sm text-slate-300">
                <thead class="text-xs text-slate-500">
                    <tr>
                        <th class="px-2 py-2 text-start">{{ $t('Company') }}</th>
                        <th class="px-2 py-2 text-start">{{ $t('Employees') }}</th>
                        <th class="px-2 py-2 text-start">{{ $t('Plan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in companies" :key="c.id" class="border-t border-slate-800">
                        <td class="px-2 py-3 font-medium text-white">{{ c.name }}</td>
                        <td class="px-2 py-3">{{ c.employees }}</td>
                        <td class="px-2 py-3">
                            <select :class="input" :value="c.plan_id ?? ''" @change="assign(c, $event)">
                                <option value="">{{ $t('No plan (no limits)') }}</option>
                                <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </PlatformLayout>
</template>
