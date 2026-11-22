<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-900/5 sm:p-7">
        <Head :title="$t('Email Verification')" />

        <div class="mb-4 text-sm text-slate-600">{{ $t('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}</div>

        <div
            class="mb-4 text-sm font-medium text-emerald-700"
            v-if="verificationLinkSent"
        >{{ $t('A new verification link has been sent to the email address you provided during registration.') }}</div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    class="!justify-center !rounded-xl !bg-[#2BD9A9] !px-5 !py-3 !text-sm !font-bold !normal-case !tracking-normal !text-[#0A2038] hover:!bg-[#4BE5BB] focus:!bg-[#4BE5BB] focus:!ring-[#0B7A60]"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >{{ $t('Resend Verification Email') }}</PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-slate-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >{{ $t('Log Out') }}</Link
                >
            </div>
        </form>
        </div>
    </GuestLayout>
</template>
