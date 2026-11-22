<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/Dashboard/PlatformLayout.vue';

defineProps({ info: { type: Object, required: true } });
</script>

<template>
    <Head :title="$t('Settings')" />
    <PlatformLayout :title="$t('Settings')" :subtitle="$t('How the platform is set up')">
        <section class="mb-6">
            <p class="mb-2 text-sm font-medium text-cyan-400">{{ $t('PLATFORM ADMINISTRATION') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $t('Settings') }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ $t('The current platform setup. These values come from the server configuration and are shown here for reference only.') }}</p>
        </section>
        <section class="hx-card p-5" data-testid="platform-settings">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-slate-500">{{ $t('Company address base') }}</dt><dd class="mt-1 text-white" dir="ltr">{{ info.tenant_domain || '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ $t('Environment') }}</dt><dd class="mt-1 text-white" dir="ltr">{{ info.environment }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ $t('Default language') }}</dt><dd class="mt-1 text-white" dir="ltr">{{ info.default_locale }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ $t('Email sending') }}</dt><dd class="mt-1" :class="info.email_sending ? 'text-emerald-300' : 'text-amber-300'">{{ info.email_sending ? $t('Configured') : $t('Not sending real email yet') }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ $t('Companies') }}</dt><dd class="mt-1 text-white">{{ info.companies }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ $t('Plans') }}</dt><dd class="mt-1 text-white">{{ info.plans }} · <Link :href="route('platform.plans.index')" class="text-cyan-400">{{ $t('Manage plans') }}</Link></dd></div>
                <div><dt class="text-xs text-slate-500">{{ $t('Platform Users') }}</dt><dd class="mt-1 text-white">{{ info.platform_users }} · <Link :href="route('platform.users.index')" class="text-cyan-400">{{ $t('View') }}</Link></dd></div>
            </dl>
        </section>
    </PlatformLayout>
</template>
