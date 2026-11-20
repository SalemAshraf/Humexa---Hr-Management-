
<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canRegister: {
        type: Boolean,
        default: true,
    },
});

const features = [
    {
        number: '01',
        icon: '👥',
        title: 'Employee Management',
        description: 'Keep employee profiles, documents, and organizational information organized in one secure place.',
    },
    {
        number: '02',
        icon: '⏱',
        title: 'Smart Attendance',
        description: 'Track working hours, shifts, late arrivals, and overtime with flexible attendance policies.',
    },
    {
        number: '03',
        icon: '🏖',
        title: 'Leave Management',
        description: 'Simplify leave requests, approvals, balances, and employee absence tracking.',
    },
    {
        number: '04',
        icon: '💰',
        title: 'Payroll Management',
        description: 'Organize payroll workflows and keep compensation data accessible to authorized users.',
    },
    {
        number: '05',
        icon: '📊',
        title: 'Reports & Analytics',
        description: 'Turn workforce information into clear insights that support better decisions.',
    },
    {
        number: '06',
        icon: '✦',
        title: 'AI Assistant',
        description: 'Explore a smarter way to understand HR information through an intelligent assistant.',
    },
];

const stats = [
    { value: '01', label: 'Unified HR Platform' },
    { value: '24/7', label: 'Access to Your Workspace' },
    { value: '100%', label: 'Tenant Data Isolation' },
];
</script>

<template>
    <Head>
        <title>Humexa — Human Resources, Simplified</title>
        <meta
            name="description"
            content="Manage your people, attendance, leaves, and HR operations with Humexa, your unified human resources platform."
        />
        <meta name="theme-color" content="#080D1B" />
    </Head>

    <div class="min-h-screen overflow-hidden bg-[#080D1B] font-sans text-white selection:bg-cyan-400/30 selection:text-cyan-100">

        <!-- Background -->
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute -left-40 -top-40 h-[500px] w-[500px] rounded-full bg-blue-600/10 blur-[130px]"></div>
            <div class="absolute -right-40 top-40 h-[500px] w-[500px] rounded-full bg-cyan-500/10 blur-[130px]"></div>
            <div class="absolute bottom-0 left-1/2 h-[400px] w-[600px] -translate-x-1/2 rounded-full bg-indigo-600/10 blur-[140px]"></div>
            <div class="absolute inset-0 opacity-[0.035]" style="background-image: linear-gradient(rgba(255,255,255,.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.5) 1px, transparent 1px); background-size: 55px 55px;"></div>
        </div>

        <!-- Navigation -->
        <header class="relative z-20 mx-auto max-w-7xl px-6 lg:px-8">
            <nav class="flex h-20 items-center justify-between border-b border-white/[0.07]">

                <a href="#" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/30 bg-gradient-to-br from-blue-500/30 to-cyan-400/10 shadow-lg shadow-cyan-500/10">
                        <span class="text-xl font-black text-cyan-300">H</span>
                    </div>
                    <span class="text-2xl font-bold tracking-tight">
                        Humexa<span class="text-cyan-400">.</span>
                    </span>
                </a>

                <div class="hidden items-center gap-9 text-sm text-slate-400 md:flex">
                    <a href="#features" class="transition hover:text-white">Features</a>
                    <a href="#platform" class="transition hover:text-white">Platform</a>
                    <a href="#about" class="transition hover:text-white">About</a>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        href="/platform/login"
                        class="hidden rounded-lg px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:text-white sm:block"
                    >
                        Sign in
                    </a>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-lg bg-gradient-to-r from-blue-500 to-cyan-400 px-5 py-2.5 text-sm font-bold text-[#07111f] shadow-lg shadow-cyan-500/20 transition hover:-translate-y-0.5 hover:shadow-cyan-400/30"
                    >
                        Get Started
                    </Link>
                </div>
            </nav>
        </header>

        <main class="relative z-10">

            <!-- Hero -->
            <section class="mx-auto grid max-w-7xl items-center gap-16 px-6 pb-28 pt-20 lg:grid-cols-2 lg:px-8 lg:pb-36 lg:pt-28">

                <div>
                    <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-4 py-2 text-xs font-semibold tracking-wide text-cyan-300">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-cyan-400"></span>
                        THE FUTURE OF HUMAN RESOURCES
                    </div>

                    <h1 class="max-w-2xl text-5xl font-extrabold leading-[1.12] tracking-tight sm:text-6xl lg:text-[68px]">
                        Your People.
                        <br />
                        Your Business.
                        <br />
                        <span class="bg-gradient-to-r from-blue-400 via-cyan-300 to-sky-400 bg-clip-text text-transparent">
                            One Platform.
                        </span>
                    </h1>

                    <p class="mt-8 max-w-xl text-base leading-8 text-slate-400 sm:text-lg">
                        Simplify your HR operations, empower your workforce, and manage your entire organization with one intelligent platform built for modern businesses.
                    </p>

                    <div class="mt-10 flex flex-col gap-4 sm:flex-row">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="group flex items-center justify-center gap-3 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-400 px-7 py-4 font-bold text-[#07111f] shadow-xl shadow-blue-500/20 transition hover:-translate-y-1 hover:shadow-cyan-400/30"
                        >
                            Start Your Journey
                            <span class="transition group-hover:translate-x-1">→</span>
                        </Link>

                        <a
                            href="#features"
                            class="flex items-center justify-center rounded-xl border border-white/10 bg-white/[0.03] px-7 py-4 font-semibold text-white transition hover:border-cyan-400/40 hover:bg-white/[0.07]"
                        >
                            Explore Features
                        </a>
                    </div>

                    <div class="mt-12 flex flex-wrap items-center gap-x-8 gap-y-4 text-sm text-slate-500">
                        <span class="flex items-center gap-2">
                            <span class="text-cyan-400">✓</span>
                            Unified HR workspace
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="text-cyan-400">✓</span>
                            Secure by design
                        </span>
                    </div>
                </div>

                <!-- Dashboard Preview -->
                <div id="platform" class="relative mx-auto w-full max-w-xl lg:max-w-none">

                    <div class="absolute -inset-8 rounded-full bg-blue-500/10 blur-[90px]"></div>

                    <div class="relative rounded-2xl border border-white/10 bg-[#10182A]/90 p-3 shadow-2xl shadow-blue-950/50 backdrop-blur-xl sm:p-5">

                        <div class="flex items-center justify-between border-b border-white/[0.07] px-2 pb-5">
                            <div class="flex items-center gap-2">
                                <div class="h-2.5 w-2.5 rounded-full bg-red-400/80"></div>
                                <div class="h-2.5 w-2.5 rounded-full bg-yellow-400/80"></div>
                                <div class="h-2.5 w-2.5 rounded-full bg-green-400/80"></div>
                            </div>
                            <div class="rounded-md border border-white/10 bg-white/[0.04] px-4 py-1.5 text-[10px] text-slate-500">
                                app.humexa.com
                            </div>
                            <div class="text-xs text-cyan-300">●</div>
                        </div>

                        <div class="grid grid-cols-[100px_1fr] gap-4 pt-5 sm:grid-cols-[145px_1fr]">

                            <!-- Sidebar -->
                            <div class="border-r border-white/[0.07] pr-3">
                                <div class="mb-7 flex items-center gap-2">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500/20 text-xs font-bold text-cyan-300">H</div>
                                    <span class="text-xs font-bold">Humexa</span>
                                </div>

                                <div class="space-y-2 text-[10px] sm:text-xs">
                                    <div class="rounded-lg bg-blue-500/15 px-2 py-2.5 text-cyan-300">
                                        ◈ Dashboard
                                    </div>
                                    <div class="px-2 py-2 text-slate-500">♙ Employees</div>
                                    <div class="px-2 py-2 text-slate-500">◷ Attendance</div>
                                    <div class="px-2 py-2 text-slate-500">▣ Leaves</div>
                                    <div class="px-2 py-2 text-slate-500">◇ Payroll</div>
                                    <div class="px-2 py-2 text-slate-500">▤ Reports</div>
                                </div>

                                <div class="mt-12 rounded-lg border border-cyan-400/10 bg-cyan-400/[0.04] p-2">
                                    <div class="text-[9px] text-cyan-300">HUMEXA AI</div>
                                    <div class="mt-1 text-[9px] text-slate-500">Your smart HR assistant</div>
                                </div>
                            </div>

                            <!-- Main Dashboard -->
                            <div class="min-w-0">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-[10px] text-slate-500">Overview</div>
                                        <div class="mt-1 text-sm font-bold sm:text-lg">Good morning 👋</div>
                                    </div>
                                    <div class="rounded-lg border border-white/10 bg-white/[0.04] px-2 py-1.5 text-[9px] text-slate-400">
                                        Today, Nov 20
                                    </div>
                                </div>

                                <div class="mt-6 grid grid-cols-2 gap-2 sm:gap-3">
                                    <div class="rounded-xl border border-white/[0.07] bg-white/[0.03] p-3">
                                        <div class="text-[9px] text-slate-500 sm:text-xs">Total Employees</div>
                                        <div class="mt-2 text-xl font-bold sm:text-2xl">248</div>
                                        <div class="mt-1 text-[9px] text-emerald-400">↑ Workforce overview</div>
                                    </div>
                                    <div class="rounded-xl border border-white/[0.07] bg-white/[0.03] p-3">
                                        <div class="text-[9px] text-slate-500 sm:text-xs">Present Today</div>
                                        <div class="mt-2 text-xl font-bold sm:text-2xl">216</div>
                                        <div class="mt-1 text-[9px] text-cyan-400">Attendance overview</div>
                                    </div>
                                    <div class="rounded-xl border border-white/[0.07] bg-white/[0.03] p-3">
                                        <div class="text-[9px] text-slate-500 sm:text-xs">On Leave</div>
                                        <div class="mt-2 text-xl font-bold sm:text-2xl">12</div>
                                        <div class="mt-1 text-[9px] text-amber-400">Leave overview</div>
                                    </div>
                                    <div class="rounded-xl border border-white/[0.07] bg-white/[0.03] p-3">
                                        <div class="text-[9px] text-slate-500 sm:text-xs">Departments</div>
                                        <div class="mt-2 text-xl font-bold sm:text-2xl">08</div>
                                        <div class="mt-1 text-[9px] text-blue-400">Organization overview</div>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-xl border border-white/[0.07] bg-white/[0.025] p-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold">Workforce Overview</span>
                                        <span class="text-[9px] text-slate-500">Illustrative data</span>
                                    </div>

                                    <div class="mt-5 flex h-24 items-end gap-2 sm:h-32">
                                        <div v-for="(height, index) in [42, 65, 52, 85, 60, 95, 72, 88, 57, 76, 92, 68]" :key="index" class="flex-1 rounded-t-sm bg-gradient-to-t from-blue-600/30 to-cyan-400/80" :style="{ height: `${height}%` }"></div>
                                    </div>

                                    <div class="mt-3 flex justify-between text-[8px] text-slate-600">
                                        <span>Jan</span>
                                        <span>Mar</span>
                                        <span>May</span>
                                        <span>Jul</span>
                                        <span>Sep</span>
                                        <span>Dec</span>
                                    </div>
                                </div>

                                <div class="mt-4 flex items-center justify-between rounded-xl border border-cyan-400/10 bg-cyan-400/[0.04] p-3">
                                    <div>
                                        <div class="text-[10px] font-semibold text-cyan-200">Everything in one place</div>
                                        <div class="mt-1 text-[9px] text-slate-500">A unified view of your workforce</div>
                                    </div>
                                    <div class="text-xl text-cyan-300">✦</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute -bottom-7 -left-5 hidden rounded-xl border border-white/10 bg-[#111D31] p-4 shadow-2xl sm:block">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-400/10 text-lg text-emerald-300">✓</div>
                            <div>
                                <div class="text-xs font-bold">Workspace secured</div>
                                <div class="mt-1 text-[10px] text-slate-500">Tenant-aware architecture</div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute -right-4 top-20 hidden rounded-xl border border-cyan-400/20 bg-[#111D31] p-3 shadow-xl sm:block">
                        <div class="text-[10px] text-cyan-300">✦ Intelligent HR</div>
                        <div class="mt-1 text-xs font-semibold">Built for growth</div>
                    </div>
                </div>
            </section>

            <!-- Stats -->
            <section class="relative border-y border-white/[0.07] bg-white/[0.015]">
                <div class="mx-auto grid max-w-7xl gap-8 px-6 py-12 sm:grid-cols-3 lg:px-8">
                    <div v-for="stat in stats" :key="stat.label" class="text-center">
                        <div class="text-3xl font-extrabold text-cyan-300">{{ stat.value }}</div>
                        <div class="mt-2 text-sm text-slate-500">{{ stat.label }}</div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section id="features" class="mx-auto max-w-7xl px-6 py-28 lg:px-8">

                <div class="mx-auto max-w-2xl text-center">
                    <div class="text-xs font-bold tracking-[0.25em] text-cyan-400">POWERFUL CAPABILITIES</div>
                    <h2 class="mt-5 text-4xl font-extrabold tracking-tight sm:text-5xl">
                        Everything HR.
                        <span class="text-cyan-300">One System.</span>
                    </h2>
                    <p class="mt-6 leading-8 text-slate-400">
                        From employee management to workforce insights, bring your essential HR operations together in one connected workspace.
                    </p>
                </div>

                <div class="mt-16 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="feature in features"
                        :key="feature.number"
                        class="group relative overflow-hidden rounded-2xl border border-white/[0.08] bg-gradient-to-br from-white/[0.045] to-white/[0.015] p-7 transition duration-300 hover:-translate-y-1 hover:border-cyan-400/30 hover:bg-cyan-400/[0.04]"
                    >
                        <div class="absolute right-6 top-6 text-xs font-bold text-slate-700">{{ feature.number }}</div>
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-cyan-400/15 bg-cyan-400/[0.07] text-2xl transition group-hover:border-cyan-400/30 group-hover:bg-cyan-400/10">
                            {{ feature.icon }}
                        </div>
                        <h3 class="mt-7 text-lg font-bold">{{ feature.title }}</h3>
                        <p class="mt-4 text-sm leading-7 text-slate-400">{{ feature.description }}</p>
                    </div>
                </div>
            </section>

            <!-- Security -->
            <section id="about" class="mx-auto max-w-7xl px-6 pb-28 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl border border-cyan-400/15 bg-gradient-to-br from-blue-950/70 via-[#10182A] to-[#0B1424] p-8 sm:p-14 lg:p-16">
                    <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-cyan-500/10 blur-[100px]"></div>

                    <div class="relative grid items-center gap-12 lg:grid-cols-2">
                        <div>
                            <div class="text-xs font-bold tracking-[0.2em] text-cyan-300">BUILT WITH SECURITY IN MIND</div>
                            <h2 class="mt-6 text-3xl font-extrabold leading-tight sm:text-5xl">
                                Your business data.
                                <br />
                                <span class="text-cyan-300">Your private space.</span>
                            </h2>
                            <p class="mt-6 max-w-lg leading-8 text-slate-400">
                                Humexa is designed around a multi-tenant architecture, helping keep each company's workspace and business data isolated.
                            </p>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="mt-9 inline-flex items-center gap-3 rounded-xl bg-cyan-400 px-6 py-3.5 font-bold text-[#07111f] transition hover:bg-cyan-300"
                            >
                                Create Your Workspace
                                <span>→</span>
                            </Link>
                        </div>

                        <div class="relative mx-auto w-full max-w-md">
                            <div class="rounded-2xl border border-white/10 bg-[#080D1B]/70 p-6 shadow-2xl backdrop-blur-xl">
                                <div class="flex items-center justify-between border-b border-white/10 pb-5">
                                    <span class="text-sm font-bold">Security Overview</span>
                                    <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-[10px] font-semibold text-emerald-300">Designed for isolation</span>
                                </div>
                                <div class="mt-6 space-y-5">
                                    <div v-for="item in ['Tenant-aware access', 'Role-based permissions', 'Protected employee data', 'Secure workspace boundaries']" :key="item" class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-400/10 text-cyan-300">✓</span>
                                        <span class="text-sm text-slate-300">{{ item }}</span>
                                    </div>
                                </div>
                                <div class="mt-7 rounded-xl border border-cyan-400/10 bg-cyan-400/[0.04] p-4">
                                    <div class="text-xs text-slate-500">Architecture</div>
                                    <div class="mt-2 font-semibold text-cyan-200">Multi-Tenant HRMS</div>
                                    <div class="mt-1 text-xs text-slate-500">A dedicated workspace for every organization</div>
                                </div>
                            </div>
                            <div class="absolute -bottom-5 -right-5 -z-10 h-full w-full rounded-2xl border border-cyan-400/10"></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Final CTA -->
            <section class="px-6 pb-28">
                <div class="mx-auto max-w-4xl text-center">
                    <div class="text-xs font-bold tracking-[0.25em] text-cyan-400">READY TO GET STARTED?</div>
                    <h2 class="mt-5 text-4xl font-extrabold tracking-tight sm:text-5xl">
                        Make HR work
                        <span class="text-cyan-300">for you.</span>
                    </h2>
                    <p class="mx-auto mt-6 max-w-xl leading-8 text-slate-400">
                        Bring your people, processes, and organization together with Humexa.
                    </p>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="mt-9 inline-flex items-center gap-3 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-400 px-8 py-4 font-bold text-[#07111f] shadow-xl shadow-cyan-500/20 transition hover:-translate-y-1"
                    >
                        Get Started with Humexa
                        <span>→</span>
                    </Link>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="relative border-t border-white/[0.07]">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 px-6 py-9 sm:flex-row lg:px-8">
                <a href="#" class="flex items-center gap-2 text-xl font-bold">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-400/10 text-cyan-300">H</span>
                    Humexa<span class="text-cyan-400">.</span>
                </a>

                <p class="text-center text-xs text-slate-600">
                    © {{ new Date().getFullYear() }} Humexa. All rights reserved.
                </p>

                <a href="/platform/login" class="text-xs text-slate-500 transition hover:text-cyan-300">
                    Platform Administration
                </a>
            </div>
        </footer>
    </div>
</template>
