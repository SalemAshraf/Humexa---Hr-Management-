<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    status: {
        type: String,
        default: null,
    },
});

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('platform.login.store'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Platform Administrator Login')" />

        <div class="rounded-3xl border border-cyan-400/15 bg-[#111C30]/95 p-6 shadow-2xl shadow-black/30 backdrop-blur-xl sm:p-8">
            <div class="mb-7">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl border border-cyan-400/20 bg-cyan-400/10 text-lg font-black text-cyan-300">
                    H
                </div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.22em] text-cyan-400">{{ $t('Humexa Control Center') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $t('Platform sign in') }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-400">{{ $t('Sign in with your authorized platform administrator account.') }}</p>
            </div>

            <div
                v-if="status"
                class="mb-5 rounded-xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-200"
                role="status"
            >
                {{ status }}
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Administrator email" class="!text-slate-300" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        :placeholder="$t('admin@humexa.com')"
                        class="mt-2 block w-full !border-white/10 !bg-[#0B1425] !text-white placeholder:!text-slate-600 focus:!border-cyan-400 focus:!ring-cyan-400/20"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <InputLabel for="password" value="Password" class="!text-slate-300" />
                        <button
                            type="button"
                            class="text-xs font-medium text-cyan-400 hover:text-cyan-300"
                            @click="showPassword = !showPassword"
                        >
                            {{ showPassword ? $t('Hide') : $t('Show') }}
                        </button>
                    </div>
                    <TextInput
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        :placeholder="$t('Enter your password')"
                        class="block w-full !border-white/10 !bg-[#0B1425] !text-white placeholder:!text-slate-600 focus:!border-cyan-400 focus:!ring-cyan-400/20"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <label class="flex cursor-pointer items-center gap-2">
                    <Checkbox v-model:checked="form.remember" name="remember" />
                    <span class="text-sm text-slate-400">{{ $t('Keep me signed in') }}</span>
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-900/30 transition hover:from-cyan-400 hover:to-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ form.processing ? $t('Signing in...') : $t('Sign in to Platform') }}
                </button>

                <p v-if="form.hasErrors" class="text-center text-xs text-red-400" role="alert">{{ $t('Authentication failed. Check your credentials and try again.') }}</p>
            </form>

            <div class="mt-7 border-t border-white/10 pt-5">
                <p class="text-center text-xs leading-5 text-slate-500">{{ $t('Restricted area. Access is available only to authorized Humexa platform administrators.') }}</p>
            </div>
        </div>
    </GuestLayout>
</template>
