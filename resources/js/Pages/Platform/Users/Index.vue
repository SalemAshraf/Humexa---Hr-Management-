<script setup>
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/Dashboard/PlatformLayout.vue';

defineProps({ users: { type: Array, required: true } });
</script>

<template>
    <Head :title="$t('Platform Users')" />
    <PlatformLayout :title="$t('Platform Users')" :subtitle="$t('Who can sign in to the platform')">
        <section class="mb-6">
            <p class="mb-2 text-sm font-medium text-cyan-400">{{ $t('PLATFORM ADMINISTRATION') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $t('Platform Users') }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ $t('Accounts with access to this admin area. This list is read only. Adding or removing platform accounts is done by the platform owner outside the app.') }}</p>
        </section>
        <section class="hx-card overflow-hidden" data-testid="platform-users">
            <ul class="divide-y divide-slate-800">
                <li v-for="u in users" :key="u.id" class="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-semibold text-white"><bdi>{{ u.name }}</bdi><span v-if="u.is_you" class="ms-2 text-xs text-cyan-400">{{ $t('You') }}</span></p>
                        <p class="truncate text-sm text-slate-400" dir="ltr">{{ u.email }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span v-for="r in u.roles" :key="r" class="rounded-full bg-slate-800 px-2.5 py-1 text-slate-300">{{ $t(r) }}</span>
                        <span class="rounded-full px-2.5 py-1" :class="u.active ? 'bg-emerald-400/10 text-emerald-300' : 'bg-red-400/10 text-red-300'">{{ u.active ? $t('Active') : $t('Disabled') }}</span>
                        <span class="text-slate-500">{{ $t('Added {date}', { date: '\u2066' + u.created_at + '\u2069' }) }}</span>
                    </div>
                </li>
            </ul>
        </section>
    </PlatformLayout>
</template>
