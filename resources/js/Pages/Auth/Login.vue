
<script setup>
import { ref } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
        canResetPassword: {
        type: Boolean,
    },
    companySuspended: {
    type: String,
    default: null,
},
    status: {
        type: String,
    },
    loginUrl: {
        type: String,
        default: null,
    },
    showRegistrationLink: {
        type: Boolean,
        default: true,
    },
});

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(props.loginUrl || route('login'), {
        onFinish: () => {
            form.reset('password');
            showPassword.value = false;
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Sign In')" />

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-900/5 sm:p-7">

            <!-- Header -->
            <div class="mb-6">
                <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#2BD9A9]/40 bg-[#2BD9A9]/15 text-[#0B7A60]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <path d="m10 17 5-5-5-5m5 5H3" />
                    </svg>
                </div>

                <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-[#0B7A60]">{{ $t('Welcome Back') }}</p>

                <h2 class="text-2xl font-bold tracking-tight text-[#0E2A47] sm:text-3xl">{{ $t('Sign in to Afrad') }}</h2>

                <p class="mt-2 text-sm leading-5 text-slate-600">{{ $t('Access your company workspace and manage your team.') }}</p>
            </div>

            <!-- Status -->
            <div
                v-if="status"
                class="mb-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-medium text-emerald-300"
                role="status"
            >
                {{ status }}
            </div>

            <!-- Company Suspended -->
<div
    v-if="companySuspended"
    class="mb-5 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-sm font-medium text-red-300"
    role="alert"
>
    {{ companySuspended }}
</div>

            <!-- Login form -->
            <form @submit.prevent="submit" class="space-y-4">

                <!-- Email -->
                <div>
                    <InputLabel
                        for="email"
                        value="Work Email"
                        class="!text-slate-700"
                    />

                    <TextInput
                        id="email"
                        type="email"
                        class="mt-2 block w-full !border-slate-300 !bg-white !text-[#0E2A47] placeholder:!text-slate-600 focus:!border-[#0B7A60] focus:!ring-[#2BD9A9]/30"
                        v-model="form.email"
                        :placeholder="$t('you@company.com')"
                        required
                        autofocus
                        autocomplete="username"
                    />

                    <InputError
                        class="mt-1"
                        :message="form.errors.email"
                    />
                </div>

                <!-- Password -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <InputLabel
                            for="password"
                            value="Password"
                            class="!text-slate-700"
                        />

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs font-medium text-[#0B7A60] transition hover:text-[#0E2A47]"
                        >{{ $t('Forgot password?') }}</Link>
                    </div>

                    <div class="relative">
                        <TextInput
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            class="block w-full !border-slate-300 !bg-white !pe-16 !text-[#0E2A47] placeholder:!text-slate-600 focus:!border-[#0B7A60] focus:!ring-[#2BD9A9]/30"
                            v-model="form.password"
                            :placeholder="$t('Enter your password')"
                            required
                            autocomplete="current-password"
                        />

                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            :aria-label="showPassword ? $t('Hide password') : $t('Show password')"
                            class="absolute end-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-600 transition hover:text-[#0B7A60] focus:outline-none focus:text-[#0B7A60]"
                        >
                            {{ showPassword ? $t('Hide') : $t('Show') }}
                        </button>
                    </div>

                    <InputError
                        class="mt-1"
                        :message="form.errors.password"
                    />
                </div>

                <!-- Remember me -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex cursor-pointer items-center gap-2">
                        <Checkbox
                            name="remember"
                            v-model:checked="form.remember"
                        />

                        <span class="text-sm text-slate-600">{{ $t('Remember me') }}</span>
                    </label>
                </div>

                <!-- Submit -->
                <div class="pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#2BD9A9] px-5 py-3.5 text-sm font-bold text-[#0A2038] shadow-sm transition hover:bg-[#4BE5BB] focus:outline-none focus:ring-2 focus:ring-[#0B7A60] focus:ring-offset-2 focus:ring-offset-white disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <svg
                            v-if="form.processing"
                            class="h-5 w-5 animate-spin"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />

                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4Z"
                            />
                        </svg>

                        {{ form.processing ? $t('Signing in...') : $t('Sign In to Afrad') }}

                        <svg
                            v-if="!form.processing"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </button>

                    <p
                        v-if="form.hasErrors"
                        class="mt-3 text-center text-xs text-red-600"
                        role="alert"
                    >{{ $t('Please check your email and password.') }}</p>
                </div>
            </form>

            <!-- Registration link -->
            <div v-if="showRegistrationLink" class="mt-6 border-t border-slate-200 pt-5 text-center">
                <p class="text-sm text-slate-600">{{ $t('Don\'t have a company account?') }}<Link
                        :href="route('register')"
                        class="ms-1 font-semibold text-[#0B7A60] transition hover:text-[#0E2A47]"
                    >{{ $t('Create account') }}</Link>
                </p>
            </div>
        </div>
    </GuestLayout>
</template>

