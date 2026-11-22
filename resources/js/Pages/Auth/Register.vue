
<script setup>
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const page = usePage();
const registeredDomain = computed(() => page.props.flash?.registered_domain || null);
const loginUrl = computed(() => {
    if (!registeredDomain.value) return null;
    const port = window.location.port ? `:${window.location.port}` : '';
    return `${window.location.protocol}//${registeredDomain.value}${port}/login`;
});

const form = useForm({
    company_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register.company'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
            showPassword.value = false;
            showPasswordConfirmation.value = false;
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Create Company Account')" />

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-900/5 sm:p-7">
            <!-- Header -->
            <div class="mb-5">
                <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#2BD9A9]/40 bg-[#2BD9A9]/15 text-[#0B7A60]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4" />
                        <path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01M15 12v.01M15 15v.01M15 18v.01" />
                    </svg>
                </div>

                <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-[#0B7A60]">{{ $t('Get started with Afrad') }}</p>

                <h2 class="text-2xl font-bold tracking-tight text-[#0E2A47] sm:text-3xl">{{ $t('Create your company account') }}</h2>

                <p class="mt-2 text-sm leading-5 text-slate-600">{{ $t('Set up your workspace and start managing your people in one place.') }}</p>
            </div>

            <div v-if="registeredDomain" class="mb-5 rounded-2xl border border-[#2BD9A9]/50 bg-[#2BD9A9]/10 p-4" role="status" data-testid="register-success">
                <p class="text-sm font-bold text-[#0B7A60]">{{ $t('Your company account is ready.') }}</p>
                <p class="mt-1 text-sm text-slate-700">{{ $t('Your workspace address is') }} <span dir="ltr" class="font-semibold">{{ registeredDomain }}</span></p>
                <a :href="loginUrl" class="mt-3 inline-flex items-center rounded-xl bg-[#0E2A47] px-4 py-2 text-sm font-semibold text-white hover:bg-[#12395f]">{{ $t('Open your workspace and sign in') }}</a>
            </div>

            <form v-else @submit.prevent="submit" class="space-y-4">
                <!-- Company details -->
                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#0E2A47]/10 text-xs font-bold text-[#0E2A47]">
                            01
                        </span>

                        <h3 class="text-sm font-semibold text-[#0E2A47]">{{ $t('Company information') }}</h3>
                    </div>

                    <div>
                        <InputLabel
                            for="company_name"
                            value="Company Name"
                            class="!text-slate-700"
                        />

                        <TextInput
                            id="company_name"
                            type="text"
                            class="mt-2 block w-full !border-slate-300 !bg-white !text-[#0E2A47] placeholder:!text-slate-600 focus:!border-[#0B7A60] focus:!ring-[#2BD9A9]/30"
                            v-model="form.company_name"
                            :placeholder="$t('e.g. Acme Corporation')"
                            required
                            autofocus
                            autocomplete="organization"
                        />

                        <InputError
                            class="mt-1"
                            :message="form.errors.company_name"
                        />
                    </div>
                </div>

                <div class="border-t border-slate-200"></div>

                <!-- Administrator details -->
                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#2BD9A9]/20 text-xs font-bold text-[#0B7A60]">
                            02
                        </span>

                        <h3 class="text-sm font-semibold text-[#0E2A47]">{{ $t('Administrator account') }}</h3>
                    </div>

                    <div class="space-y-3">
                        <!-- Full name -->
                        <div>
                            <InputLabel
                                for="name"
                                value="Full Name"
                                class="!text-slate-700"
                            />

                            <TextInput
                                id="name"
                                type="text"
                                class="mt-2 block w-full !border-slate-300 !bg-white !text-[#0E2A47] placeholder:!text-slate-600 focus:!border-[#0B7A60] focus:!ring-[#2BD9A9]/30"
                                v-model="form.name"
                                :placeholder="$t('Your full name')"
                                required
                                autocomplete="name"
                            />

                            <InputError
                                class="mt-1"
                                :message="form.errors.name"
                            />
                        </div>

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
                                autocomplete="email"
                            />

                            <InputError
                                class="mt-1"
                                :message="form.errors.email"
                            />
                        </div>

                        <!-- Password fields -->
                        <div class="grid gap-3 sm:grid-cols-2">
                            <!-- Password -->
                            <div>
                                <InputLabel
                                    for="password"
                                    value="Password"
                                    class="!text-slate-700"
                                />

                                <div class="relative mt-2">
                                    <TextInput
                                        id="password"
                                        :type="showPassword ? 'text' : 'password'"
                                        class="block w-full !border-slate-300 !bg-white !pe-16 !text-[#0E2A47] placeholder:!text-slate-600 focus:!border-[#0B7A60] focus:!ring-[#2BD9A9]/30"
                                        v-model="form.password"
                                        :placeholder="$t('Min. 8 characters')"
                                        required
                                        autocomplete="new-password"
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

                            <!-- Confirm Password -->
                            <div>
                                <InputLabel
                                    for="password_confirmation"
                                    value="Confirm Password"
                                    class="!text-slate-700"
                                />

                                <div class="relative mt-2">
                                    <TextInput
                                        id="password_confirmation"
                                        :type="showPasswordConfirmation ? 'text' : 'password'"
                                        class="block w-full !border-slate-300 !bg-white !pe-16 !text-[#0E2A47] placeholder:!text-slate-600 focus:!border-[#0B7A60] focus:!ring-[#2BD9A9]/30"
                                        v-model="form.password_confirmation"
                                        :placeholder="$t('Repeat password')"
                                        required
                                        autocomplete="new-password"
                                    />

                                    <button
                                        type="button"
                                        @click="showPasswordConfirmation = !showPasswordConfirmation"
                                        :aria-label="showPasswordConfirmation ? $t('Hide confirm password') : $t('Show confirm password')"
                                        class="absolute end-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-600 transition hover:text-[#0B7A60] focus:outline-none focus:text-[#0B7A60]"
                                    >
                                        {{ showPasswordConfirmation ? $t('Hide') : $t('Show') }}
                                    </button>
                                </div>

                                <InputError
                                    class="mt-1"
                                    :message="form.errors.password_confirmation"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="pt-1">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#2BD9A9] px-5 py-3 text-sm font-bold text-[#0A2038] shadow-sm transition hover:bg-[#4BE5BB] focus:outline-none focus:ring-2 focus:ring-[#0B7A60] focus:ring-offset-2 focus:ring-offset-white disabled:cursor-not-allowed disabled:opacity-50"
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

                        {{ form.processing ? $t('Creating your account...') : $t('Create Company Account') }}

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
                        class="mt-2 text-center text-xs text-red-600"
                        role="alert"
                    >{{ $t('Please review the highlighted fields.') }}</p>
                </div>
            </form>

            <!-- Login link -->
            <div class="mt-5 border-t border-slate-200 pt-4 text-center">
                <p class="text-sm text-slate-600">{{ $t('Already have an account?') }}<Link
                        :href="route('login')"
                        class="ms-1 font-semibold text-[#0B7A60] transition hover:text-[#0E2A47]"
                    >{{ $t('Sign in') }}</Link>
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
