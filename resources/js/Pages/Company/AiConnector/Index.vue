<script setup>
import { Head, router } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({
    connections: { type: Array, required: true },
    tools: { type: Array, required: true },
    url: { type: String, required: true },
});
const labels = {
    workforce_headcount: 'Headcount totals by status and department',
    attendance_today: "Today's attendance totals",
    leave_overview: 'Pending leave and people on leave today',
    open_positions: 'Open positions with candidate counts per stage',
};
const disconnect = (family) => router.delete(`/ai-connections/${family}`, { preserveScroll: true });
</script>

<template>
    <Head :title="$t('AI connections')" />

    <CompanyLayout :title="$t('AI connections')" :subtitle="$t('Read-only Claude connection')">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('SETTINGS') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('AI connections') }}</h1>
        </section>

        <p v-if="$page.props.flash?.status" class="mb-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ $t($page.props.flash.status) }}</p>

        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">{{ $t('Connect Claude') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $t('In Claude, add a custom connector with this address, then approve it here when asked. You can then ask questions about your company totals.') }}</p>
            <p class="mt-3 select-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs" dir="ltr">{{ url }}</p>
            <p v-if="!tools.length" class="mt-3 text-sm text-amber-800">{{ $t('Your role has no company totals to share, so you cannot connect.') }}</p>
            <ul v-else class="mt-3 list-disc space-y-1 ps-5 text-sm text-slate-700">
                <li v-for="tl in tools" :key="tl">{{ $t(labels[tl] ?? tl) }}</li>
            </ul>
            <p class="mt-3 text-xs text-slate-500">{{ $t('Read-only. It never receives names, contact details, salaries, ID numbers or documents. Answers go to the AI app you connect, under its own terms.') }}</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">{{ $t('Your connected apps') }}</h2>
            <p v-if="!connections.length" class="mt-2 text-sm text-slate-500">{{ $t('No app is connected.') }}</p>
            <ul v-else class="mt-3 divide-y divide-slate-100">
                <li v-for="c in connections" :key="c.family" class="flex items-center justify-between gap-3 py-3 text-sm">
                    <div>
                        <p class="font-semibold"><bdi>{{ c.app }}</bdi></p>
                        <p class="text-xs text-slate-500">{{ $t('Connected') }} <bdi dir="ltr">{{ c.connected_at }}</bdi><template v-if="c.last_used_at"> · {{ $t('Last used') }} <bdi dir="ltr">{{ c.last_used_at }}</bdi></template></p>
                    </div>
                    <button type="button" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" @click="disconnect(c.family)">{{ $t('Disconnect') }}</button>
                </li>
            </ul>
        </section>
    </CompanyLayout>
</template>
