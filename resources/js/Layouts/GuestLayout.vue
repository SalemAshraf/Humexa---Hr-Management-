
<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageSwitch from '@/Components/LanguageSwitch.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#080F1E] text-white">
        <!-- Background effects -->
        <div class="pointer-events-none absolute -left-40 -top-40 h-96 w-96 rounded-full bg-blue-600/20 blur-[120px]"></div>
        <div class="pointer-events-none absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-cyan-500/10 blur-[120px]"></div>

        <div class="absolute end-4 top-4 z-20"><LanguageSwitch dark /></div>

        <div class="relative grid min-h-screen lg:grid-cols-2">
            <!-- Branding panel -->
            <section class="relative hidden flex-col justify-between overflow-hidden border-r border-white/10 p-10 lg:flex xl:p-14">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 via-transparent to-cyan-500/5"></div>

                <div class="relative">
                    <Link href="/" class="inline-flex items-center gap-3">
                        <ApplicationLogo class="h-11 w-11 fill-cyan-400" />

                        <span class="text-2xl font-bold tracking-tight">
                            {{ $t('Afrad') }}
                        </span>
                    </Link>
                </div>

                <div class="relative max-w-lg">
                    <div class="mb-5 inline-flex items-center rounded-full border border-cyan-400/20 bg-cyan-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-300">
                        {{ $t('Human Resources, Simplified') }}
                    </div>

                    <h1 class="text-5xl font-bold leading-tight xl:text-6xl">
                        {{ $t('Build a better') }}
                        <span class="bg-gradient-to-r from-cyan-300 to-blue-400 bg-clip-text text-transparent">
                            {{ $t('workplace.') }}
                        </span>
                    </h1>

                    <p class="mt-5 max-w-md text-base leading-7 text-slate-400 xl:text-lg xl:leading-8">
                        {{ $t('Bring your people, processes, and organization together with one powerful HR management platform.') }}
                    </p>

                    <div class="mt-9 space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/20 bg-cyan-400/10 text-cyan-300">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="10" cy="7" r="4" />
                                    <path d="M20 8v6m3-3h-6" />
                                </svg>
                            </div>

                            <span class="text-sm text-slate-300">{{ $t('Manage your entire workforce') }}</span>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-blue-400/20 bg-blue-400/10 text-blue-300">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="3" y="4" width="18" height="17" rx="2" />
                                    <path d="M16 2v4M8 2v4M3 10h18" />
                                </svg>
                            </div>

                            <span class="text-sm text-slate-300">{{ $t('Simplify everyday HR operations') }}</span>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/20 bg-cyan-400/10 text-cyan-300">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
                                    <path d="m9 12 2 2 4-4" />
                                </svg>
                            </div>

                            <span class="text-sm text-slate-300">{{ $t('Keep your company data organized') }}</span>
                        </div>
                    </div>
                </div>

                <div class="relative text-xs text-slate-500">
                    © {{ new Date().getFullYear() }} {{ $t('Afrad') }}. {{ $t('All rights reserved.') }}
                </div>
            </section>

            <!-- Form panel -->
            <main class="flex min-h-screen flex-col items-center justify-center px-5 py-6 sm:px-10 lg:px-10 lg:py-4 xl:px-12">
                <!-- Mobile branding -->
                <div class="mb-5 flex items-center gap-3 lg:hidden">
                    <ApplicationLogo class="h-10 w-10 fill-cyan-400" />

                    <span class="text-2xl font-bold tracking-tight">
                        {{ $t('Afrad') }}
                    </span>
                </div>

                <div class="w-full max-w-xl">
                    <slot />
                </div>

                <p class="mt-5 text-center text-xs text-slate-500">
                    {{ $t('Secure workspace for your business') }}
                </p>
            </main>
        </div>
    </div>
</template>
