<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

defineProps({ items: { type: Array, required: true } });

const tones = { amber: 'bg-amber-50 text-amber-700', cyan: 'bg-cyan-50 text-cyan-700', red: 'bg-red-50 text-red-700' };
</script>

<template>
    <Head title="Notifications" />

    <CompanyLayout title="Notifications" subtitle="What needs your attention">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">TO DO</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Needs your attention</h1>
            <p class="mt-2 text-sm text-slate-500">Counted live from current records and limited to what your role can open. An item disappears when it is dealt with.</p>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <ul class="divide-y divide-slate-100">
                <li v-for="item in items" :key="item.key">
                    <Link :href="item.url" class="flex items-center justify-between gap-4 p-4 hover:bg-slate-50">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ item.group }}</p>
                            <p class="font-semibold text-slate-800">{{ item.title }}</p>
                            <p class="text-xs text-slate-500">{{ item.detail }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-1 text-sm font-bold" :class="tones[item.tone] ?? tones.amber">{{ item.count }}</span>
                    </Link>
                </li>
                <li v-if="!items.length" class="p-10 text-center text-sm text-slate-500">You are all caught up. Nothing is waiting for you.</li>
            </ul>
        </section>
    </CompanyLayout>
</template>
