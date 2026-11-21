<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({
    corrections: { type: Array, required: true },
    overtime: { type: Array, required: true },
});

const styles = { pending: 'bg-amber-50 text-amber-700', approved: 'bg-emerald-50 text-emerald-700', rejected: 'bg-red-50 text-red-700' };
const note = reactive({});
const decide = (kind, id, decision) =>
    router.post(`/attendance/requests/${kind}/${id}/review`, { decision, note: note[`${kind}${id}`] ?? null }, { preserveScroll: true });
</script>

<template>
    <Head :title="$t('Attendance requests')" />
    <CompanyLayout :title="$t('Attendance requests')" :subtitle="$t('Corrections and overtime')">
        <section class="mb-6 flex items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('REVIEW') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Attendance requests') }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $t('Approving a correction changes the recorded times. Approving overtime adds it to the day.') }}</p>
            </div>
            <a href="/attendance" class="text-sm font-semibold text-cyan-700">{{ $t('Back to attendance') }}</a>
        </section>

        <template v-for="block in [{ key: 'correction', title: 'Time corrections', rows: corrections }, { key: 'overtime', title: 'Overtime', rows: overtime }]" :key="block.key">
            <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" :data-testid="block.key + '-list'">
                <h2 class="border-b border-slate-100 px-5 py-4 text-base font-bold text-slate-900">{{ block.title }}</h2>
                <ul class="divide-y divide-slate-100">
                    <li v-for="r in block.rows" :key="r.id" class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-800">{{ r.employee }}
                                <span class="ml-2 rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="styles[r.status]">{{ $t(r.status) }}</span>
                            </p>
                            <p v-if="block.key === 'correction'" class="mt-1 text-xs text-slate-500">
                                {{ $t('In') }}: {{ r.requested_check_in ?? $t('unchanged') }} · {{ $t('Out') }}: {{ r.requested_check_out ?? $t('unchanged') }}
                            </p>
                            <p v-else class="mt-1 text-xs text-slate-500">{{ $t('{n} minutes requested', { n: r.minutes }) }}</p>
                            <p v-if="r.reason" class="mt-1 text-sm text-slate-600">{{ r.reason }}</p>
                            <p v-if="r.review_note" class="mt-1 text-xs italic text-slate-400">{{ $t('Note') }}: {{ r.review_note }}</p>
                        </div>
                        <div v-if="r.status === 'pending'" class="flex items-center gap-2">
                            <input v-model="note[block.key + r.id]" :placeholder="$t('Note')" class="w-32 rounded-lg border-slate-300 py-1 text-xs" />
                            <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white" @click="decide(block.key, r.id, 'approve')">{{ $t('Approve') }}</button>
                            <button class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-red-600 ring-1 ring-red-200" @click="decide(block.key, r.id, 'reject')">{{ $t('Reject') }}</button>
                        </div>
                    </li>
                    <li v-if="!block.rows.length" class="p-8 text-center text-sm text-slate-500">{{ $t('Nothing here yet.') }}</li>
                </ul>
            </section>
        </template>
    </CompanyLayout>
</template>
