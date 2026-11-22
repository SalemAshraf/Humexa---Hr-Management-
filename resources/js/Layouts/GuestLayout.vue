
<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageSwitch from '@/Components/LanguageSwitch.vue';
import { Link } from '@inertiajs/vue3';
import '@fontsource/ibm-plex-sans-arabic/arabic-400.css';
import '@fontsource/ibm-plex-sans-arabic/arabic-500.css';
import '@fontsource/ibm-plex-sans-arabic/arabic-600.css';
import '@fontsource/ibm-plex-sans-arabic/arabic-700.css';
import '@fontsource/ibm-plex-sans-arabic/latin-400.css';
import '@fontsource/ibm-plex-sans-arabic/latin-600.css';
import '@fontsource/ibm-plex-sans-arabic/latin-700.css';
</script>

<template>
    <div class="guest-font relative min-h-screen overflow-hidden bg-[#F3F6F8] text-[#0E2A47]">
        <div class="absolute end-4 top-4 z-20"><LanguageSwitch /></div>

        <div class="relative grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <!-- Branding panel -->
            <section class="relative hidden flex-col justify-between overflow-hidden bg-[#0E2A47] p-10 text-white lg:flex xl:p-14">
                <div class="pointer-events-none absolute -bottom-32 -start-24 h-80 w-80 rounded-full bg-[#2BD9A9]/15 blur-[100px]"></div>

                <Link href="/" class="relative inline-flex items-center gap-3">
                    <ApplicationLogo class="h-11 w-11" />
                    <span class="text-2xl font-bold tracking-tight">{{ $t('Afrad') }}</span>
                </Link>

                <div class="relative max-w-md">
                    <p class="mb-5 inline-block rounded-full border border-white/15 px-4 py-1.5 text-xs font-semibold text-[#2BD9A9]">{{ $t('Human Resources, Simplified') }}</p>
                    <h1 class="text-4xl font-bold leading-[1.3] tracking-tight xl:text-5xl xl:leading-[1.25]">
                        {{ $t('Build a better') }}
                        <span class="text-[#2BD9A9]">{{ $t('workplace.') }}</span>
                    </h1>
                    <p class="mt-5 text-base leading-7 text-slate-300">
                        {{ $t('Bring your people, processes, and organization together with one powerful HR management platform.') }}
                    </p>
                    <ul class="mt-8 space-y-3 text-sm text-slate-200">
                        <li v-for="t in ['Manage your entire workforce', 'Simplify everyday HR operations', 'Keep your company data organized']" :key="t" class="flex items-center gap-3">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#2BD9A9] text-xs font-bold text-[#0A2038]" aria-hidden="true">&#10003;</span>
                            {{ $t(t) }}
                        </li>
                    </ul>
                </div>

                <div class="relative text-xs text-slate-400">
                    &copy; {{ new Date().getFullYear() }} {{ $t('Afrad') }}. {{ $t('All rights reserved.') }}
                </div>
            </section>

            <!-- Form panel -->
            <main class="flex min-h-screen flex-col items-center justify-center px-5 pb-6 pt-20 sm:px-10 lg:px-10 lg:pb-4 lg:pt-16 xl:px-12">
                <Link href="/" class="mb-5 flex items-center gap-3 lg:hidden">
                    <span class="rounded-xl bg-[#0E2A47] p-1"><ApplicationLogo class="h-9 w-9" /></span>
                    <span class="text-2xl font-bold tracking-tight">{{ $t('Afrad') }}</span>
                </Link>

                <div class="w-full max-w-xl">
                    <slot />
                </div>

                <p class="mt-5 text-center text-xs text-slate-500">{{ $t('Secure workspace for your business') }}</p>
            </main>
        </div>
    </div>
</template>

<style scoped>
.guest-font {
    font-family: 'IBM Plex Sans Arabic', 'IBM Plex Sans', 'Segoe UI', Tahoma, system-ui, sans-serif;
}
</style>
