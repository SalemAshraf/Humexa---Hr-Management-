<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({ account: { type: Object, required: true } });

const form = useForm({ name: props.account.name, locale: props.account.locale });
const save = () => form.put('/account', { preserveScroll: true, onSuccess: () => window.location.reload() });
</script>

<template>
    <Head :title="$t('My account')" />

    <CompanyLayout :title="$t('My account')" :subtitle="$t('Your own sign-in details')">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('ACCOUNT') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('My account') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $t('These details belong to you only. Company settings and employee records are edited elsewhere.') }}</p>
        </section>

        <p v-if="$page.props.flash?.success" class="mb-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ $page.props.flash.success }}</p>

        <form class="max-w-xl space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="save">
            <label class="block text-sm font-medium text-slate-700">
                {{ $t('Display name') }}
                <input v-model="form.name" type="text" maxlength="120" required class="mt-1 w-full rounded-lg border-slate-300 text-sm" data-testid="account-name" />
                <span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span>
            </label>

            <div class="text-sm font-medium text-slate-700">
                {{ $t('Sign-in email') }}
                <p class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-slate-600" dir="ltr">{{ account.email }}</p>
                <p class="mt-1 text-xs font-normal text-slate-500">{{ $t('The sign-in email cannot be changed here. Ask the company owner if it must change.') }}</p>
            </div>

            <label class="block text-sm font-medium text-slate-700">
                {{ $t('Language') }}
                <select v-model="form.locale" class="mt-1 w-full rounded-lg border-slate-300 text-sm" data-testid="account-locale">
                    <option value="en">English</option>
                    <option value="ar">العربية</option>
                </select>
                <span v-if="form.errors.locale" class="mt-1 block text-xs text-red-600">{{ form.errors.locale }}</span>
            </label>

            <div class="flex flex-wrap items-center gap-3">
                <button :disabled="form.processing" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ $t('Save') }}</button>
                <Link href="/password/change" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200">{{ $t('Change password') }}</Link>
            </div>
        </form>
    </CompanyLayout>
</template>
