<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ rid: String, blocked: String, app: String, redirectHost: String, user: Object, company: String, tools: { type: Array, default: () => [] } });
const busy = ref(false);
const send = (decision) => { busy.value = true; router.post('/oauth/authorize', { rid: props.rid, decision }, { onFinish: () => (busy.value = false) }); };
const labels = {
    workforce_headcount: 'Headcount totals by status and department',
    attendance_today: "Today's attendance totals",
    leave_overview: 'Pending leave and people on leave today',
    open_positions: 'Open positions with candidate counts per stage',
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Connect an AI app')" />
        <div class="mx-auto w-full max-w-md px-5 py-16">
            <h1 class="text-2xl font-bold">{{ $t('Connect an AI app') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $t('Let this app read company totals from Afrad. It cannot change anything.') }}</p>
            <dl class="mt-6 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $t('App') }}</dt><dd class="font-semibold"><bdi>{{ app }}</bdi></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $t('Returns you to') }}</dt><dd dir="ltr" class="font-mono text-xs">{{ redirectHost }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $t('Signed in as') }}</dt><dd class="text-end"><bdi>{{ user.name }}</bdi><br /><span dir="ltr" class="text-xs text-slate-500">{{ user.email }}</span></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $t('Company') }}</dt><dd class="font-semibold"><bdi>{{ company }}</bdi></dd></div>
            </dl>
            <template v-if="blocked">
                <p class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{{ $t('Your Afrad role has no company totals to share, or this company is not active. Ask your company owner.') }}</p>
                <button type="button" class="mt-3 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold" :disabled="busy" @click="send('deny')">{{ $t('Go back') }}</button>
            </template>
            <template v-else>
                <p class="mt-5 text-sm font-semibold text-slate-800">{{ $t('It will be able to read, for your role:') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-slate-700">
                    <li v-for="tl in tools" :key="tl">{{ $t(labels[tl] ?? tl) }}</li>
                </ul>
                <p class="mt-3 text-sm text-slate-700">{{ $t('It never receives employee names, contact details, salaries, ID numbers or documents.') }}</p>
                <p class="mt-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{{ $t('The answers are shown to the AI app you are connecting, which handles them under its own terms.') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $t('You can disconnect at any time under AI connections in Settings.') }}</p>
                <div class="mt-5 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl bg-[#2BD9A9] px-4 py-2.5 text-sm font-bold text-[#0A2038] hover:bg-[#4BE5BB]" :disabled="busy" @click="send('allow')">{{ $t('Allow') }}</button>
                    <button type="button" class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold" :disabled="busy" @click="send('deny')">{{ $t('Deny') }}</button>
                </div>
            </template>
        </div>
    </GuestLayout>
</template>
