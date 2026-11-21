<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({ forced: { type: Boolean, default: false } });

const form = useForm({ current_password: '', password: '', password_confirmation: '' });
const submit = () => form.post('/password/change', { onFinish: () => form.reset() });
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Change password')" />
        <h1 class="text-xl font-bold text-slate-900">{{ $t('Choose your own password') }}</h1>
        <p v-if="forced" class="mt-2 text-sm text-slate-500">{{ $t('Your account was created with a password someone else set. Replace it now to continue.') }}</p>
        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <label class="block text-sm font-medium text-slate-600">{{ $t('Current password') }}<input v-model="form.current_password" type="password" autocomplete="current-password" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <p v-if="form.errors.current_password" class="text-xs text-red-600">{{ form.errors.current_password }}</p>
            <label class="block text-sm font-medium text-slate-600">{{ $t('New password (12+ characters)') }}<input v-model="form.password" type="password" autocomplete="new-password" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <p v-if="form.errors.password" class="text-xs text-red-600">{{ form.errors.password }}</p>
            <label class="block text-sm font-medium text-slate-600">{{ $t('Repeat new password') }}<input v-model="form.password_confirmation" type="password" autocomplete="new-password" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white" :disabled="form.processing">{{ $t('Save password') }}</button>
        </form>
    </GuestLayout>
</template>
