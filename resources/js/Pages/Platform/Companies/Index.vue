<script setup>
import { reactive } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/Dashboard/PlatformLayout.vue';

const props = defineProps({
    companies: {
        type: Object,
        required: true,
    },
    activity: {
        type: Array,
        default: () => [],
    },
    canViewActivityLogs: {
        type: Boolean,
        default: false,
    },
    canSuspendCompanies: {
        type: Boolean,
        default: false,
    },
    canActivateCompanies: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const reasons = reactive({});
const processing = reactive({});

function changeStatus(company, action) {
    const isSuspending = action === 'suspend';
    const message = isSuspending
        ? `Suspend ${company.companyName}? All active company sessions will be revoked.`
        : `Activate ${company.companyName}? All users will need to sign in again.`;

    if (!window.confirm(message) || processing[company.id]) {
        return;
    }

    processing[company.id] = true;

    router.patch(
        route(`platform.companies.${action}`, company.id),
        { reason: reasons[company.id] || null },
        {
            preserveScroll: true,
            onFinish: () => {
                processing[company.id] = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="$t('Manage Companies')" />

    <PlatformLayout
        :title="$t('Companies')"
        :subtitle="$t('Manage tenant access and company status')"
    >
        <section class="mb-8">
            <p class="mb-2 text-sm font-medium text-cyan-400">{{ $t('PLATFORM ADMINISTRATION') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $t('Company Management') }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ $t('Suspend access without deleting company data, or reactivate a company securely.') }}</p>
        </section>

        <div
            v-if="page.props.flash?.status"
            class="mb-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300"
            role="status"
        >
            {{ page.props.flash.status }}
        </div>

        <section class="hx-card overflow-hidden p-5 sm:p-6">
            <div class="mb-5">
                <h2 class="font-semibold text-white">{{ $t('Registered Companies') }}</h2>
                <p class="mt-1 text-sm text-slate-400">
                    {{ $t('{n} companies registered on Afrad', { n: companies.total }) }}
                </p>
            </div>

            <div v-if="companies.data?.length" class="overflow-x-auto">
                <table class="w-full text-start text-sm md:min-w-[850px] max-md:block">
                    <thead class="max-md:hidden">
                        <tr class="border-b border-slate-800 text-slate-400">
                            <th class="px-3 py-3 font-medium">{{ $t('Company') }}</th>
                            <th class="px-3 py-3 font-medium">{{ $t('Domain') }}</th>
                            <th class="px-3 py-3 font-medium">{{ $t('Status') }}</th>
                            <th class="px-3 py-3 font-medium">{{ $t('Registered') }}</th>
                            <th class="px-3 py-3 font-medium">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="max-md:block">
                        <tr
                            v-for="company in companies.data"
                            :key="company.id"
                            class="border-b border-slate-800/70 align-top last:border-0 max-md:mb-3 max-md:block max-md:rounded-xl max-md:border max-md:border-slate-800 max-md:p-3"
                        >
                            <td class="px-3 py-4 font-medium text-slate-200 max-md:block max-md:px-1 max-md:py-2 max-md:before:mb-1 max-md:before:block max-md:before:text-xs max-md:before:text-slate-500 max-md:before:content-[attr(data-label)]" :data-label="$t('Company')">
                                {{ company.companyName }}
                            </td>
                            <td class="px-3 py-4 text-cyan-400 max-md:block max-md:px-1 max-md:py-2 max-md:before:mb-1 max-md:before:block max-md:before:text-xs max-md:before:text-slate-500 max-md:before:content-[attr(data-label)]" :data-label="$t('Domain')">
                                <div v-for="domain in company.domains" :key="domain">
                                    {{ domain }}
                                </div>
                                <span v-if="!company.domains.length" class="text-slate-500">—</span>
                            </td>
                            <td class="px-3 py-4 max-md:block max-md:px-1 max-md:py-2 max-md:before:mb-1 max-md:before:block max-md:before:text-xs max-md:before:text-slate-500 max-md:before:content-[attr(data-label)]" :data-label="$t('Status')">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="company.status === 'active'
                                        ? 'bg-emerald-400/10 text-emerald-300'
                                        : 'bg-red-400/10 text-red-300'"
                                >
                                    {{ $t(company.status) }}
                                </span>
                            </td>
                            <td class="px-3 py-4 text-slate-400 max-md:block max-md:px-1 max-md:py-2 max-md:before:mb-1 max-md:before:block max-md:before:text-xs max-md:before:text-slate-500 max-md:before:content-[attr(data-label)]" :data-label="$t('Registered')">
                                {{ company.createdAt || '—' }}
                            </td>
                            <td class="px-3 py-4 max-md:block max-md:px-1 max-md:py-2 max-md:before:mb-1 max-md:before:block max-md:before:text-xs max-md:before:text-slate-500 max-md:before:content-[attr(data-label)]" :data-label="$t('Actions')">
                                <div
                                    v-if="(company.status === 'active' && canSuspendCompanies) || (company.status === 'suspended' && canActivateCompanies)"
                                    class="flex min-w-48 flex-col gap-2"
                                >
                                    <input
                                        v-model="reasons[company.id]"
                                        type="text"
                                        maxlength="1000"
                                        :placeholder="$t('Reason (optional)')"
                                        class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-white placeholder:text-slate-500 focus:border-cyan-500 focus:outline-none"
                                    />
                                    <button
                                        v-if="company.status === 'active' && canSuspendCompanies"
                                        type="button"
                                        :disabled="processing[company.id]"
                                        class="rounded-lg border border-red-400/20 bg-red-400/10 px-3 py-2 text-xs font-semibold text-red-300 transition hover:bg-red-400/20 disabled:opacity-50"
                                        @click="changeStatus(company, 'suspend')"
                                    >
                                        {{ processing[company.id] ? $t('Processing...') : $t('Suspend Company') }}
                                    </button>
                                    <button
                                        v-else-if="company.status === 'suspended' && canActivateCompanies"
                                        type="button"
                                        :disabled="processing[company.id]"
                                        class="rounded-lg border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-400/20 disabled:opacity-50"
                                        @click="changeStatus(company, 'activate')"
                                    >
                                        {{ processing[company.id] ? $t('Processing...') : $t('Activate Company') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="py-12 text-center text-sm text-slate-400">{{ $t('No companies registered yet.') }}</div>

            <div v-if="companies.links?.length > 3" class="mt-5 flex flex-wrap gap-2">
                <button
                    v-for="link in companies.links"
                    :key="link.label"
                    type="button"
                    :disabled="!link.url"
                    class="rounded-lg border px-3 py-2 text-xs"
                    :class="link.active
                        ? 'border-cyan-400/40 bg-cyan-400/10 text-cyan-300'
                        : 'border-slate-700 text-slate-400 disabled:opacity-40'"
                    @click="link.url && router.visit(link.url, { preserveScroll: true })"
                    v-html="link.label"
                />
            </div>
        </section>

        <section v-if="canViewActivityLogs" class="hx-card mt-6 p-5 sm:p-6">
            <div class="mb-5">
                <h2 class="font-semibold text-white">{{ $t('Company Status Audit Log') }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ $t('Latest suspension and activation events') }}</p>
            </div>

            <div v-if="activity.length" class="space-y-3">
                <article
                    v-for="entry in activity"
                    :key="entry.id"
                    class="rounded-xl border border-slate-800 p-4"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-200">
                            {{ entry.event === 'company.suspended' ? $t('Company suspended') : (entry.event === 'company.plan_changed' ? $t('Plan changed') : $t('Company activated')) }}
                        </p>
                        <span class="text-xs text-slate-500">{{ entry.createdAt }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ $t('Company ID') }}: {{ entry.tenantId }} · {{ $t('By') }}: {{ entry.actor }}
                    </p>
                    <p v-if="entry.reason" class="mt-2 text-sm text-slate-300">
                        {{ entry.reason }}
                    </p>
                </article>
            </div>
            <p v-else class="text-sm text-slate-500">{{ $t('No company status changes recorded yet.') }}</p>
        </section>
    </PlatformLayout>
</template>
