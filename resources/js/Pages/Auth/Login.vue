
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
        <Head title="Sign In" />

        <div class="rounded-3xl border border-white/10 bg-[#111C30]/90 p-5 shadow-2xl shadow-black/20 backdrop-blur-xl sm:p-7">

            <!-- Header -->
            <div class="mb-6">
                <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/20 bg-cyan-400/10 text-cyan-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <path d="m10 17 5-5-5-5m5 5H3" />
                    </svg>
                </div>

                <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-cyan-400">
                    Welcome Back
                </p>

                <h2 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Sign in to Humexa
                </h2>

                <p class="mt-2 text-sm leading-5 text-slate-400">
                    Access your company workspace and manage your team.
                </p>
            </div>

            <!-- Status -->
            <div
                v-if="status"
                class="mb-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-medium text-emerald-300"
                role="status"
            >
                {{ status }}
            </div>

            <!-- Login form -->
            <form @submit.prevent="submit" class="space-y-4">

                <!-- Email -->
                <div>
                    <InputLabel
                        for="email"
                        value="Work Email"
                        class="!text-slate-300"
                    />

                    <TextInput
                        id="email"
                        type="email"
                        class="mt-2 block w-full !border-white/10 !bg-[#0B1425] !text-white placeholder:!text-slate-600 focus:!border-cyan-400 focus:!ring-cyan-400/20"
                        v-model="form.email"
                        placeholder="you@company.com"
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
                            class="!text-slate-300"
                        />

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs font-medium text-cyan-400 transition hover:text-cyan-300"
                        >
                            Forgot password?
                        </Link>
                    </div>

                    <div class="relative">
                        <TextInput
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            class="block w-full !border-white/10 !bg-[#0B1425] !pe-16 !text-white placeholder:!text-slate-600 focus:!border-cyan-400 focus:!ring-cyan-400/20"
                            v-model="form.password"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        />

                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            class="absolute end-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400 transition hover:text-cyan-400 focus:outline-none focus:text-cyan-400"
                        >
                            {{ showPassword ? 'Hide' : 'Show' }}
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

                        <span class="text-sm text-slate-400">
                            Remember me
                        </span>
                    </label>
                </div>

                <!-- Submit -->
                <div class="pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-900/30 transition hover:from-cyan-400 hover:to-blue-500 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-[#111C30] disabled:cursor-not-allowed disabled:opacity-50"
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

                        {{ form.processing ? 'Signing in...' : 'Sign In to Humexa' }}

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
                        class="mt-3 text-center text-xs text-red-400"
                        role="alert"
                    >
                        Please check your email and password.
                    </p>
                </div>
            </form>

            <!-- Registration link -->
            <div v-if="showRegistrationLink" class="mt-6 border-t border-white/10 pt-5 text-center">
                <p class="text-sm text-slate-400">
                    Don't have a company account?

                    <Link
                        :href="route('register')"
                        class="ms-1 font-semibold text-cyan-400 transition hover:text-cyan-300"
                    >
                        Create account
                    </Link>
                </p>
            </div>
        </div>
    </GuestLayout>
</template>

