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
const setEnd = (company, event) => router.patch(route('platform.companies.subscription', company.id), { subscription_ends_at: event.target.value || null }, { preserveScroll: true });
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

        <section class="mb-8">
            <h2 class="mb-3 font-semibold text-white">{{ $t('Plans') }}</h2>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="p in plans" :key="p.id" class="hx-card p-5">
                    <template v-if="editing[p.id]">
                        <div class="grid gap-3">
                            <label class="text-xs text-slate-400">{{ $t('Name') }}<input v-model="editing[p.id].name" :class="input + ' mt-1 w-full'" maxlength="80" /></label>
                            <label class="text-xs text-slate-400">{{ $t('Max employees') }}<input v-model="editing[p.id].max_employees" :class="input + ' mt-1 w-full'" type="number" min="1" :placeholder="$t('No limit')" /></label>
                            <label class="text-xs text-slate-400">{{ $t('Max accounts') }}<input v-model="editing[p.id].max_accounts" :class="input + ' mt-1 w-full'" type="number" min="1" :placeholder="$t('No limit')" /></label>
                            <label class="text-xs text-slate-400">{{ $t('Reference price (EGP / month)') }}<input v-model="editing[p.id].indicative_price_egp" :class="input + ' mt-1 w-full'" type="number" min="0" /></label>
                            <label class="text-xs text-slate-300"><input v-model="editing[p.id].is_active" type="checkbox" /> {{ $t('Active') }}</label>
                            <button class="rounded-lg bg-cyan-500 px-3 py-2 text-sm font-bold text-slate-900" @click="save(p)">{{ $t('Save') }}</button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-lg font-bold text-white"><bdi>{{ p.name }}</bdi></h3>
                            <span class="rounded-full bg-slate-800 px-2 py-0.5 text-xs text-slate-300">{{ p.is_active ? $t('Active') : $t('Inactive') }}</span>
                        </div>
                        <dl class="mt-3 space-y-1 text-sm text-slate-300">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $t('Employees') }}</dt><dd><bdi dir="ltr">{{ limit(p.max_employees) ?? $t('No limit') }}</bdi></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $t('Sign-in accounts') }}</dt><dd><bdi dir="ltr">{{ limit(p.max_accounts) ?? $t('No limit') }}</bdi></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $t('Reference price (EGP / month)') }}</dt><dd><bdi dir="ltr">{{ p.indicative_price_egp ?? '—' }}</bdi></dd></div>
                        </dl>
                        <button class="mt-4 text-sm font-semibold text-cyan-400" @click="startEdit(p)">{{ $t('Edit') }}</button>
                    </template>
                </article>
            </div>

            <form class="hx-card mt-4 grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="create">
                <input v-model="form.name" :class="input" maxlength="80" required :placeholder="$t('New plan name')" :aria-label="$t('New plan name')" />
                <input v-model="form.max_employees" :class="input" type="number" min="1" :placeholder="$t('Max employees')" :aria-label="$t('Max employees')" />
                <input v-model="form.max_accounts" :class="input" type="number" min="1" :placeholder="$t('Max accounts')" :aria-label="$t('Max accounts')" />
                <input v-model="form.indicative_price_egp" :class="input" type="number" min="0" :placeholder="$t('Reference price')" :aria-label="$t('Reference price (EGP / month)')" />
                <button type="submit" class="rounded-lg bg-cyan-500 px-3 py-2 text-sm font-bold text-slate-900" :disabled="form.processing">{{ $t('Add plan') }}</button>
                <p v-if="form.errors.name" class="text-xs text-red-300 sm:col-span-2 lg:col-span-5">{{ form.errors.name }}</p>
            </form>
        </section>

        <section>
            <h2 class="mb-1 font-semibold text-white">{{ $t('Company plans') }}</h2>
            <p class="mb-3 text-xs text-slate-500">{{ $t('A company with no plan has no limits. Changing a plan never removes anything a company already has. Payment happens outside the app. The paid-until date controls access: after it passes, the company can still view and export its data, but cannot add or change records until you enter a later date.') }}</p>
            <div class="grid gap-3 md:grid-cols-2">
                <article v-for="c in companies" :key="c.id" class="hx-card p-4">
                    <h3 class="font-semibold text-white"><bdi>{{ c.name }}</bdi></h3>
                    <p class="mt-1 text-sm text-slate-400">{{ $t('Employees') }}: <bdi dir="ltr">{{ c.employees }}</bdi></p>
                    <label class="mt-3 block text-xs text-slate-400">{{ $t('Plan') }}
                        <select :class="input + ' mt-1 w-full'" :value="c.plan_id ?? ''" @change="assign(c, $event)">
                            <option value="">{{ $t('No plan (no limits)') }}</option>
                            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                    <p v-if="c.trial_ends_at && !c.subscription_ends_at" class="mt-2 text-xs text-slate-400">{{ $t('Free trial ends on {date}. Without a paid-until date the company becomes view and export only after that day.', { date: c.trial_ends_at }) }}</p>
                    <label class="mt-3 block text-xs text-slate-400">{{ $t('Paid until (you enter it after you receive payment)') }}
                        <input :class="input + ' mt-1 w-full'" type="date" :value="c.subscription_ends_at ?? ''" @change="setEnd(c, $event)" />
                    </label>
                </article>
            </div>
        </section>
    </PlatformLayout>
</template>
