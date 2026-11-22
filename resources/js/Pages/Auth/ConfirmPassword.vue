<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-900/5 sm:p-7">
        <Head :title="$t('Confirm Password')" />

        <div class="mb-4 text-sm text-slate-600">{{ $t('This is a secure area of the application. Please confirm your password before continuing.') }}</div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full focus:!border-[#0B7A60] focus:!ring-[#2BD9A9]/30"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 flex justify-end">
                <PrimaryButton
                    class="!w-full !justify-center !rounded-xl !bg-[#2BD9A9] !px-5 !py-3 !text-sm !font-bold !normal-case !tracking-normal !text-[#0A2038] hover:!bg-[#4BE5BB] focus:!bg-[#4BE5BB] focus:!ring-[#0B7A60]"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >{{ $t('Confirm') }}</PrimaryButton>
            </div>
        </form>
        </div>
    </GuestLayout>
</template>
