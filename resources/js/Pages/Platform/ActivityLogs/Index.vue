<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/Dashboard/PlatformLayout.vue';

defineProps({
    activity: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="$t('Platform Activity Logs')" />

    <PlatformLayout :title="$t('Activity Logs')" :subtitle="$t('Audit trail for platform administration')">
        <section class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-400">{{ $t('SECURITY & AUDIT') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $t('Platform Activity Logs') }}</h1>
                <p class="mt-2 text-sm text-slate-400">{{ $t('Review company access changes performed by platform administrators.') }}</p>
            </div>
            <Link
                :href="route('platform.companies.index')"
                class="inline-flex items-center justify-center rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-cyan-400/40 hover:text-cyan-300"
            >{{ $t('Company Management') }}</Link>
        </section>

        <section class="hx-card overflow-hidden p-5 sm:p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-white">{{ $t('Audit history') }}</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ $t('{n} recorded events', { n: activity.total }) }}
                    </p>
                </div>
                <span class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-1.5 text-xs text-slate-400">{{ $t('Latest first') }}</span>
            </div>

            <div v-if="activity.data?.length" class="space-y-3">
                <article
                    v-for="entry in activity.data"
                    :key="entry.id"
                    class="rounded-2xl border border-slate-800 bg-slate-900/30 p-4 sm:p-5"
                >
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-black"
                                :class="entry.newStatus === 'suspended'
                                    ? 'bg-red-400/10 text-red-300'
                                    : 'bg-emerald-400/10 text-emerald-300'"
                            >
                                {{ entry.event === 'company.plan_changed' ? $t('PLAN') : (entry.newStatus === 'suspended' ? $t('SUS') : $t('ACT')) }}
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-100">
                                    {{ entry.event === 'company.suspended' ? $t('Company suspended') : (entry.event === 'company.plan_changed' ? $t('Plan changed') : entry.event === 'company.subscription_changed' ? $t('Paid-until date changed') : $t('Company activated')) }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-400">{{ entry.companyName }}</p>
                                <p class="mt-1 break-all text-xs text-slate-600">{{ $t('Company ID') }}: {{ entry.tenantId }}</p>
                            </div>
                        </div>
                        <time class="shrink-0 text-xs text-slate-500">{{ entry.createdAt || '—' }}</time>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 border-t border-slate-800 pt-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $t('Performed by') }}</p>
                            <p class="mt-1 text-sm text-slate-200">{{ entry.actor }}</p>
                            <p v-if="entry.actorEmail" class="mt-0.5 break-all text-xs text-slate-500">{{ entry.actorEmail }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $t('Status transition') }}</p>
                            <p class="mt-1 text-sm text-slate-200">
                                <span class="capitalize">{{ entry.oldStatus || '—' }}</span>
                                <span class="mx-2 text-slate-600">→</span>
                                <span class="font-semibold capitalize" :class="entry.newStatus === 'suspended' ? 'text-red-300' : 'text-emerald-300'">
                                    {{ entry.newStatus }}
                                </span>
                            </p>
                        </div>
                        <div v-if="entry.ipAddress">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $t('IP address') }}</p>
                            <p class="mt-1 font-mono text-sm text-slate-300">{{ entry.ipAddress }}</p>
                        </div>
                    </div>

                    <div v-if="entry.reason" class="mt-4 rounded-xl bg-slate-950/60 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $t('Reason') }}</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-300">{{ entry.reason }}</p>
                    </div>
                </article>
            </div>

            <div v-else class="flex min-h-48 flex-col items-center justify-center text-center">
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-800 text-sm font-bold text-slate-400">{{ $t('LOG') }}</span>
                <p class="text-sm font-semibold text-slate-200">{{ $t('No activity recorded') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $t('Company suspension and activation events will appear here.') }}</p>
            </div>

            <div v-if="activity.links?.length > 3" class="mt-6 flex flex-wrap gap-2 border-t border-slate-800 pt-5">
                <button
                    v-for="link in activity.links"
                    :key="link.label"
                    type="button"
                    :disabled="!link.url"
                    class="rounded-lg border px-3 py-2 text-xs transition disabled:cursor-not-allowed disabled:opacity-40"
                    :class="link.active
                        ? 'border-cyan-400/40 bg-cyan-400/10 text-cyan-300'
                        : 'border-slate-700 text-slate-400 hover:border-slate-500'"
                    @click="link.url && router.visit(link.url, { preserveScroll: true })"
                    v-html="link.label"
                />
            </div>
        </section>
    </PlatformLayout>
</template>
