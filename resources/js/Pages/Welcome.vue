<script setup>
import AfradIcon from '@/Components/AfradIcon.vue';
import LanguageSwitch from '@/Components/LanguageSwitch.vue';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canRegister: { type: Boolean, default: true },
});

const icons = {
    users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
    org: 'M12 3v6 M5 21v-6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v6 M9 3h6v6H9z M2 21h6 M16 21h6',
    file: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M9 15l2 2 4-4',
    clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20 M12 6v6l4 2',
    calendar: 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
    wallet: 'M2 6h20v12H2z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6 M6 12h.01 M18 12h.01',
    briefcase: 'M3 7h18v13H3z M8 7V4h8v3 M3 13h18',
    target: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20 M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12 M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4',
    chart: 'M3 3v18h18 M7 15l4-4 3 3 5-6',
    bell: 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9 M13.7 21a2 2 0 0 1-3.4 0',
    sparkle: 'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z',
    check: 'M20 6 9 17l-5-5',
    shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
    lock: 'M5 11h14v10H5z M8 11V7a4 4 0 0 1 8 0v4',
    globe: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20 M2 12h20 M12 2a15 15 0 0 1 0 20 M12 2a15 15 0 0 0 0 20',
};

const modules = [
    { icon: 'users', title: 'Employee records', desc: 'Profiles, job details, managers and status in one searchable directory, with archive and restore.' },
    { icon: 'org', title: 'Organization', desc: 'Branches, departments and positions, so the structure of the company is clear.' },
    { icon: 'file', title: 'Document expiry', desc: 'See which employee documents are expiring, before they become a problem.' },
    { icon: 'clock', title: 'Attendance', desc: 'Daily records, late arrivals and attendance requests that managers can review.' },
    { icon: 'calendar', title: 'Leave', desc: 'Employees request, managers approve, and balances update from the approved requests.' },
    { icon: 'wallet', title: 'Payroll (experimental)', desc: 'An estimator for monthly pay runs. It needs an accountant\'s review and is not an official payslip.' },
    { icon: 'briefcase', title: 'Recruitment', desc: 'Open positions, candidates and stages, and one step to turn a candidate into an employee.' },
    { icon: 'target', title: 'Performance', desc: 'Review cycles and reviews that can be finalized, and reopened when something needs to change.' },
    { icon: 'chart', title: 'Reports', desc: 'Headcount, attendance and leave summaries your management can read at a glance.' },
    { icon: 'bell', title: 'Notifications', desc: 'In-app notifications for requests and approvals, so nothing waits unseen.' },
    { icon: 'lock', title: 'Team and roles', desc: 'Give each person the access their job needs, and nothing more.' },
    { icon: 'globe', title: 'Arabic and English', desc: 'The whole interface in Arabic or English, with right-to-left layout and a language switch.' },
];

const facts = [
    { v: 'AR / EN', l: 'Full Arabic and English interface with RTL' },
    { v: '1 : 1', l: 'One isolated workspace per company' },
    { v: '12', l: 'HR areas in one workspace' },
    { v: 'Roles', l: 'Access by job: owner, HR, manager, employee' },
];

const pains = [
    'Employee files scattered across folders and spreadsheets',
    'Leave requests lost in chat messages',
    'Expired documents noticed too late',
    'Reports built by hand at the end of the month',
];
const gains = [
    'One directory for every employee record',
    'Leave requests with a clear approval path',
    'Document expiry visible in one list',
    'Headcount, attendance and leave summaries ready to read',
];

const steps = [
    { title: 'Create your workspace', desc: 'Sign up and get a dedicated space for your company.' },
    { title: 'Add your structure', desc: 'Set up branches, departments, positions and leave types.' },
    { title: 'Bring in your people', desc: 'Add employees and give your team the roles they need.' },
    { title: 'Run HR day to day', desc: 'Handle attendance, leave, hiring and reviews from one place.' },
];

const roles = [
    { t: 'Owner', d: 'Sees the whole company, manages the team and settings.' },
    { t: 'HR', d: 'Runs employee records, attendance, leave and recruitment.' },
    { t: 'Manager', d: 'Reviews requests and follows the people in their team.' },
    { t: 'Employee', d: 'Sees their own space: attendance, leave and profile.' },
];

const securityPoints = [
    { icon: 'shield', t: 'Isolated workspaces', d: 'Each company works in its own workspace. Its data is kept separate from every other company.' },
    { icon: 'lock', t: 'Role-based access', d: 'Permissions follow the role. Sensitive employee data is limited to authorized users.' },
    { icon: 'file', t: 'Activity trail', d: 'The platform keeps activity logs for administration.' },
];

const plans = [
    { name: 'Starter', emp: 'Up to 25 employees', acc: 'Up to 5 user accounts' },
    { name: 'Growth', emp: 'Up to 100 employees', acc: 'Up to 15 user accounts', hot: true },
    { name: 'Business', emp: 'Up to 500 employees', acc: 'Up to 50 user accounts' },
];

const faqs = [
    { q: 'Is my company data separated from other companies?', a: 'Yes. Afrad is built as a multi-tenant platform where each organization has its own workspace, and its data is kept isolated from every other organization.' },
    { q: 'Which HR tasks can I manage in Afrad?', a: 'Employee records, organization structure, attendance, leave, recruitment, performance reviews, document expiry and reports, all inside one workspace.' },
    { q: 'Is payroll ready for production use?', a: 'Not yet. Payroll is an experimental estimator. It is not an official payslip and its results must be reviewed by an accountant before anyone relies on them.' },
    { q: 'Can I use it in Arabic?', a: 'Yes. The interface works in Arabic and English with a language switch and right-to-left layout. Names and other data you enter stay exactly as you typed them.' },
    { q: 'What happens at a plan limit?', a: 'Adding a new employee or user account is blocked once you reach the limit. Existing records are never hidden or deleted.' },
    { q: 'How do I get started?', a: 'Create your workspace, add your departments and employees, and invite your team. You can start with one area and add the others as you need them.' },
];

const employees = [
    { name: 'Ahmed Hassan', dept: 'Engineering', status: 'Present', time: '08:52' },
    { name: 'Mona Adel', dept: 'Finance', status: 'Present', time: '09:04' },
    { name: 'Nour Khaled', dept: 'HR', status: 'Late', time: '09:41' },
    { name: 'Karim Samir', dept: 'Sales', status: 'On leave', time: '-' },
];
const statusStyle = {
    Present: 'bg-emerald-50 text-emerald-700',
    Late: 'bg-amber-50 text-amber-700',
    'On leave': 'bg-slate-100 text-slate-600',
};
const leaveState = ref('pending');

const scrolled = ref(false);
function onScroll() { scrolled.value = window.scrollY > 8; }
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const vReveal = {
    mounted(el, binding) {
        el.classList.add('reveal');
        if (binding.value) el.style.setProperty('--d', `${binding.value}ms`);
        if (reduceMotion() || !('IntersectionObserver' in window)) { el.classList.add('is-visible'); return; }
        const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { el.classList.add('is-visible'); io.disconnect(); } }, { threshold: 0.12 });
        io.observe(el);
    },
};
onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }); });
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));

const ring = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2BD9A9]';
const btnMint = `inline-flex items-center justify-center rounded-xl bg-[#2BD9A9] px-6 py-3.5 text-sm font-bold text-[#0A2038] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#4BE5BB] hover:shadow-lg hover:shadow-[#2BD9A9]/25 ${ring}`;
const btnGhost = `inline-flex items-center justify-center rounded-xl border border-white/25 px-6 py-3.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-white/10 ${ring}`;
</script>

<template>
    <Head>
        <title>{{ $t('Afrad | HR software for growing companies') }}</title>
        <meta name="description" content="Manage employees, attendance, leave, recruitment and reviews in one workspace with Afrad." />
        <meta name="theme-color" content="#0A2038" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Noto+Kufi+Arabic:wght@400;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="afrad-font relative min-h-screen bg-[#F5F8FA] text-[#10304F] antialiased">
        <!-- Header -->
        <header :class="['sticky top-0 z-30 border-b border-white/10 bg-[#0A2038]/90 backdrop-blur-md transition-shadow', scrolled ? 'shadow-lg shadow-black/20' : '']">
            <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-5 lg:px-8" :aria-label="$t('Main')">
                <a href="#" :class="['flex items-center gap-2.5 rounded-md', ring]">
                    <AfradIcon class="h-9 w-9" aria-hidden="true" />
                    <span class="text-lg font-extrabold text-white">{{ $t('Afrad') }}</span>
                </a>
                <div class="hidden items-center gap-7 text-sm font-medium text-slate-300 lg:flex">
                    <a href="#modules" class="hover:text-white">{{ $t('Features') }}</a>
                    <a href="#how-it-works" class="hover:text-white">{{ $t('How it works') }}</a>
                    <a href="#payroll" class="hover:text-white">{{ $t('Payroll') }}</a>
                    <a href="#security" class="hover:text-white">{{ $t('Security') }}</a>
                    <a href="#plans" class="hover:text-white">{{ $t('Plans') }}</a>
                    <a href="#faq" class="hover:text-white">{{ $t('FAQ') }}</a>
                </div>
                <div class="flex items-center gap-2">
                    <LanguageSwitch dark />
                    <Link v-if="canRegister" :href="route('register')" :class="['inline-flex rounded-xl bg-[#2BD9A9] px-4 py-2.5 text-sm font-bold text-[#0A2038] hover:bg-[#4BE5BB]', ring]">{{ $t('Get started') }}</Link>
                </div>
            </nav>
        </header>

        <main>
            <!-- Hero -->
            <section class="relative overflow-hidden bg-gradient-to-b from-[#0A2038] to-[#13395F] text-white">
                <div class="pointer-events-none absolute -top-24 end-0 h-80 w-80 rounded-full bg-[#2BD9A9]/15 blur-[100px] drift" aria-hidden="true"></div>
                <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 pb-20 pt-14 lg:grid-cols-[1fr_1.05fr] lg:gap-14 lg:px-8 lg:pb-28 lg:pt-20">
                    <div class="min-w-0">
                        <p class="hero-in mb-5 inline-flex items-center gap-2 rounded-full border border-[#2BD9A9]/30 bg-[#2BD9A9]/10 px-4 py-1.5 text-xs font-semibold text-[#2BD9A9]" style="--d: 0ms">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#2BD9A9]"></span>{{ $t('HR software for Arabic and English teams') }}
                        </p>
                        <h1 class="max-w-xl text-4xl font-extrabold leading-[1.2] sm:text-5xl lg:text-[52px]">
                            <span class="hero-in block" style="--d: 100ms">{{ $t('Every person in your company,') }}</span>
                            <span class="hero-in block text-[#2BD9A9]" style="--d: 200ms">{{ $t('in one place.') }}</span>
                        </h1>
                        <p class="hero-in mt-6 max-w-lg text-lg leading-8 text-slate-300" style="--d: 300ms">{{ $t('Employee records, attendance, leave, recruitment and reviews in a single workspace, so your HR team spends less time chasing files and more time on people.') }}</p>
                        <div class="hero-in mt-9 flex flex-col gap-3 sm:flex-row" style="--d: 400ms">
                            <Link v-if="canRegister" :href="route('register')" :class="btnMint">{{ $t('Create your workspace') }}</Link>
                            <a href="#modules" :class="btnGhost">{{ $t('See what\'s included') }}</a>
                        </div>
                        <p class="hero-in mt-6 flex items-center gap-2 text-sm text-slate-400" style="--d: 500ms">
                            <svg class="h-4 w-4 shrink-0 text-[#2BD9A9]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.check" /></svg>
                            {{ $t('Each company gets its own isolated workspace.') }}
                        </p>
                    </div>

                    <!-- Product preview -->
                    <div class="hero-in relative mb-36 min-w-0" style="--d: 300ms">
                        <div class="overflow-hidden rounded-2xl border border-white/10 bg-white text-[#10304F] shadow-2xl shadow-black/30">
                            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                                <span class="flex items-center gap-2 text-sm font-bold"><span class="h-2 w-2 rounded-full bg-[#2BD9A9]"></span>{{ $t('Attendance today') }}</span>
                                <span class="text-xs text-slate-400">{{ $t('Sample data') }}</span>
                            </div>
                            <ul class="divide-y divide-slate-100">
                                <li v-for="e in employees" :key="e.name" class="flex items-center justify-between gap-3 px-5 py-3.5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold">{{ $t(e.name) }}</p>
                                        <p class="text-xs text-slate-500">{{ $t(e.dept) }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <span class="text-xs tabular-nums text-slate-500" dir="ltr">{{ e.time }}</span>
                                        <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusStyle[e.status]]">{{ $t(e.status) }}</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="float-slow absolute -bottom-[8.5rem] start-4 w-64 rounded-xl border border-slate-100 bg-white p-4 text-[#10304F] shadow-xl sm:start-[-20px]">
                            <p class="text-xs font-semibold text-slate-500">{{ $t('Leave request') }}</p>
                            <p class="mt-1 text-sm font-bold">{{ $t('Mona Adel, 3 days') }}</p>
                            <div class="mt-3 flex items-center justify-between">
                                <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', leaveState === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700']">{{ leaveState === 'pending' ? $t('Pending') : $t('Approved') }}</span>
                                <button v-if="leaveState === 'pending'" type="button" :class="['rounded-lg bg-[#0E2A47] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#13395F]', ring]" @click="leaveState = 'approved'">{{ $t('Approve') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Facts -->
            <section class="border-b border-slate-200 bg-white">
                <div class="mx-auto grid max-w-6xl grid-cols-2 gap-6 px-5 py-10 lg:grid-cols-4 lg:px-8">
                    <div v-for="f in facts" :key="f.l" v-reveal>
                        <p class="text-3xl font-extrabold text-[#0E2A47]">{{ f.v === "Roles" ? $t("Roles") : f.v }}</p>
                        <p class="mt-1 text-sm leading-6 text-[#5A6B7D]">{{ $t(f.l) }}</p>
                    </div>
                </div>
            </section>

            <!-- Before / after -->
            <section class="mx-auto max-w-6xl px-5 py-20 lg:px-8 lg:py-28">
                <div class="max-w-2xl" v-reveal>
                    <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('Why Afrad') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('From scattered sheets to one clear workspace') }}</h2>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-7" v-reveal>
                        <p class="text-sm font-bold text-[#5A6B7D]">{{ $t('Without a system') }}</p>
                        <ul class="mt-5 space-y-4">
                            <li v-for="p in pains" :key="p" class="flex gap-3 text-[15px] leading-6 text-[#5A6B7D]">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-slate-300"></span>{{ $t(p) }}
                            </li>
                        </ul>
                    </div>
                    <div class="rounded-2xl bg-[#0E2A47] p-7 text-white" v-reveal="120">
                        <p class="text-sm font-bold text-[#2BD9A9]">{{ $t('With Afrad') }}</p>
                        <ul class="mt-5 space-y-4">
                            <li v-for="g in gains" :key="g" class="flex gap-3 text-[15px] leading-6 text-slate-200">
                                <svg class="mt-1 h-4 w-4 shrink-0 text-[#2BD9A9]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.check" /></svg>{{ $t(g) }}
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Modules -->
            <section id="modules" class="scroll-mt-20 bg-white py-20 lg:py-28">
                <div class="mx-auto max-w-6xl px-5 lg:px-8">
                    <div class="max-w-2xl" v-reveal>
                        <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('What is included') }}</p>
                        <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Everything HR needs day to day') }}</h2>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Start with one area and add the others as your team grows.') }}</p>
                    </div>
                    <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <article v-for="(m, i) in modules" :key="m.title" v-reveal="(i % 3) * 80" class="group rounded-2xl border border-slate-200 bg-[#F5F8FA] p-6 transition-all hover:-translate-y-1 hover:border-[#2BD9A9]/60 hover:bg-white hover:shadow-lg hover:shadow-[#0E2A47]/5">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#0E2A47] text-[#2BD9A9]">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons[m.icon]" /></svg>
                            </span>
                            <h3 class="mt-5 text-lg font-bold">{{ $t(m.title) }}</h3>
                            <p class="mt-2 text-[15px] leading-7 text-[#5A6B7D]">{{ $t(m.desc) }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Deep dive: attendance & leave -->
            <section class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-28">
                <div v-reveal>
                    <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('Attendance and leave') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Know who is in, and who is out') }}</h2>
                    <p class="mt-5 text-lg leading-8 text-[#5A6B7D]">{{ $t('Daily attendance records show late arrivals at a glance. Leave requests go to the right manager, and balances follow the approved requests.') }}</p>
                    <ul class="mt-7 space-y-3 text-[15px]">
                        <li v-for="t in ['Daily attendance records and late arrivals', 'Attendance requests for managers to review', 'Leave requests with approval and balances']" :key="t" class="flex gap-3">
                            <svg class="mt-1 h-4 w-4 shrink-0 text-[#0E9F7E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.check" /></svg>{{ $t(t) }}
                        </li>
                    </ul>
                </div>
                <div class="rounded-2xl bg-[#0E2A47] p-6 text-white shadow-xl" v-reveal="120">
                    <p class="text-sm font-semibold text-slate-300">{{ $t('Leave balance') }} <span class="text-xs text-slate-500">({{ $t('Sample data') }})</span></p>
                    <div class="mt-5 space-y-5">
                        <div v-for="b in [{ n: 'Annual leave', u: 9, t: 21 }, { n: 'Casual leave', u: 2, t: 6 }, { n: 'Sick leave', u: 1, t: 10 }]" :key="b.n">
                            <div class="flex justify-between text-sm"><span>{{ $t(b.n) }}</span><span class="tabular-nums text-slate-300" dir="ltr">{{ b.u }} / {{ b.t }}</span></div>
                            <div class="mt-2 h-2 rounded-full bg-white/10"><div class="h-2 rounded-full bg-[#2BD9A9]" :style="{ width: (b.u / b.t) * 100 + '%' }"></div></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Deep dive: hiring & reviews -->
            <section class="bg-white py-20 lg:py-28">
                <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 lg:grid-cols-2 lg:gap-16 lg:px-8">
                    <div class="grid gap-4 sm:grid-cols-2 lg:order-first" v-reveal>
                        <div class="rounded-2xl border border-slate-200 bg-[#F5F8FA] p-6">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0E2A47] text-[#2BD9A9]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.briefcase" /></svg></span>
                            <h3 class="mt-4 font-bold">{{ $t('Recruitment') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[#5A6B7D]">{{ $t('Create an opening, add candidates, move them through the stages, and hire in one step.') }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-[#F5F8FA] p-6">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0E2A47] text-[#2BD9A9]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.target" /></svg></span>
                            <h3 class="mt-4 font-bold">{{ $t('Performance') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[#5A6B7D]">{{ $t('Open a review cycle, write reviews, finalize them, and reopen when a correction is needed.') }}</p>
                        </div>
                    </div>
                    <div v-reveal="120">
                        <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('Hiring and growth') }}</p>
                        <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('From the first candidate to the yearly review') }}</h2>
                        <p class="mt-5 text-lg leading-8 text-[#5A6B7D]">{{ $t('The people you hire and the reviews you write live next to their employee records, not in separate files.') }}</p>
                    </div>
                </div>
            </section>

            <!-- Roles -->
            <section class="mx-auto max-w-6xl px-5 py-20 lg:px-8 lg:py-28">
                <div class="max-w-2xl" v-reveal>
                    <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('Roles') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('The right access for every role') }}</h2>
                </div>
                <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="(r, i) in roles" :key="r.t" v-reveal="i * 80" class="rounded-2xl border border-slate-200 bg-white p-6">
                        <p class="text-lg font-bold">{{ $t(r.t) }}</p>
                        <p class="mt-2 text-[15px] leading-7 text-[#5A6B7D]">{{ $t(r.d) }}</p>
                    </div>
                </div>
            </section>

            <!-- Payroll honesty -->
            <section id="payroll" class="scroll-mt-20 bg-[#0E2A47] py-20 text-white lg:py-28">
                <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 lg:grid-cols-2 lg:gap-16 lg:px-8">
                    <div v-reveal>
                        <p class="inline-flex rounded-full border border-amber-300/40 bg-amber-300/10 px-3 py-1 text-xs font-bold text-amber-200">{{ $t('Experimental') }}</p>
                        <h2 class="mt-4 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Payroll estimator, with honest limits') }}</h2>
                        <p class="mt-5 text-lg leading-8 text-slate-300">{{ $t('Afrad can estimate a monthly pay run to help you plan. It is an estimator, not a certified payroll system.') }}</p>
                    </div>
                    <ul class="space-y-4" v-reveal="120">
                        <li v-for="p in ['Results are estimates and must be reviewed by an accountant', 'Its output is not an official payslip', 'Rates and rules may change, so check them before you rely on a number']" :key="p" class="flex gap-3 rounded-xl border border-white/10 bg-white/5 p-4 text-[15px] leading-6 text-slate-200">
                            <svg class="mt-1 h-4 w-4 shrink-0 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.shield" /></svg>{{ $t(p) }}
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Arabic first -->
            <section class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-28">
                <div v-reveal>
                    <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('Language') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Built for Arabic and English from the start') }}</h2>
                    <p class="mt-5 text-lg leading-8 text-[#5A6B7D]">{{ $t('Switch the whole interface between Arabic and English at any time. Layout flips to right-to-left, and the names and notes you enter are never translated or changed.') }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4" v-reveal="120">
                    <div class="rounded-2xl bg-white p-6 text-center shadow-sm" dir="rtl">
                        <p class="text-3xl font-extrabold text-[#0E2A47]">أفراد</p>
                        <p class="mt-2 text-sm text-[#5A6B7D]">العربية</p>
                    </div>
                    <div class="rounded-2xl bg-white p-6 text-center shadow-sm" dir="ltr">
                        <p class="text-3xl font-extrabold text-[#0E2A47]">Afrad</p>
                        <p class="mt-2 text-sm text-[#5A6B7D]">English</p>
                    </div>
                </div>
            </section>

            <!-- Steps -->
            <section id="how-it-works" class="scroll-mt-20 bg-white py-20 lg:py-28">
                <div class="mx-auto max-w-6xl px-5 lg:px-8">
                    <div class="max-w-2xl" v-reveal>
                        <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('How it works') }}</p>
                        <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Up and running in four steps') }}</h2>
                    </div>
                    <ol class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <li v-for="(s, i) in steps" :key="s.title" v-reveal="i * 80" class="rounded-2xl border border-slate-200 bg-[#F5F8FA] p-6">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#2BD9A9] text-sm font-extrabold text-[#0A2038]">{{ i + 1 }}</span>
                            <h3 class="mt-4 text-lg font-bold">{{ $t(s.title) }}</h3>
                            <p class="mt-2 text-[15px] leading-7 text-[#5A6B7D]">{{ $t(s.desc) }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- Security -->
            <section id="security" class="mx-auto max-w-6xl scroll-mt-20 px-5 py-20 lg:px-8 lg:py-28">
                <div class="max-w-2xl" v-reveal>
                    <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('Security') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Your people data stays yours') }}</h2>
                </div>
                <div class="mt-12 grid gap-5 md:grid-cols-3">
                    <div v-for="(s, i) in securityPoints" :key="s.t" v-reveal="i * 80" class="rounded-2xl border border-slate-200 bg-white p-6">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#0E2A47] text-[#2BD9A9]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons[s.icon]" /></svg></span>
                        <h3 class="mt-5 text-lg font-bold">{{ $t(s.t) }}</h3>
                        <p class="mt-2 text-[15px] leading-7 text-[#5A6B7D]">{{ $t(s.d) }}</p>
                    </div>
                </div>
            </section>

            <!-- Plans -->
            <section id="plans" class="scroll-mt-20 bg-white py-20 lg:py-28">
                <div class="mx-auto max-w-6xl px-5 lg:px-8">
                    <div class="max-w-2xl" v-reveal>
                        <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('Plans') }}</p>
                        <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Plans by company size') }}</h2>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Plans set limits on employees and user accounts. They are assigned by the Afrad team. There is no online payment yet.') }}</p>
                    </div>
                    <div class="mt-12 grid gap-5 md:grid-cols-3">
                        <div v-for="(p, i) in plans" :key="p.name" v-reveal="i * 80" :class="['rounded-2xl border p-7', p.hot ? 'border-[#2BD9A9] bg-[#0E2A47] text-white' : 'border-slate-200 bg-[#F5F8FA]']">
                            <p class="text-lg font-extrabold">{{ $t(p.name) }}</p>
                            <ul class="mt-5 space-y-3 text-[15px]">
                                <li class="flex gap-3"><svg class="mt-1 h-4 w-4 shrink-0 text-[#2BD9A9]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.check" /></svg>{{ $t(p.emp) }}</li>
                                <li class="flex gap-3"><svg class="mt-1 h-4 w-4 shrink-0 text-[#2BD9A9]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.check" /></svg>{{ $t(p.acc) }}</li>
                            </ul>
                        </div>
                    </div>
                    <p class="mt-6 text-sm text-[#5A6B7D]">{{ $t('At a limit, adding new employees or accounts is blocked. Existing records are never hidden or deleted.') }}</p>
                </div>
            </section>

            <!-- AI planned -->
            <section class="mx-auto max-w-6xl px-5 py-20 lg:px-8 lg:py-24">
                <div class="flex flex-col items-start gap-6 rounded-2xl border border-dashed border-[#2BD9A9]/60 bg-white p-8 sm:flex-row sm:items-center" v-reveal>
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#0E2A47] text-[#2BD9A9]"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.sparkle" /></svg></span>
                    <div>
                        <p class="text-lg font-bold">{{ $t('AI assistant') }} <span class="ms-2 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $t('Planned') }}</span></p>
                        <p class="mt-2 text-[15px] leading-7 text-[#5A6B7D]">{{ $t('Planned, not available yet: ask questions about your HR data in plain language. No AI connection exists today.') }}</p>
                    </div>
                </div>
            </section>

            <!-- FAQ -->
            <section id="faq" class="scroll-mt-20 bg-white py-20 lg:py-28">
                <div class="mx-auto max-w-3xl px-5 lg:px-8">
                    <div v-reveal>
                        <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('FAQ') }}</p>
                        <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Questions, answered') }}</h2>
                    </div>
                    <div class="mt-10 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-[#F5F8FA]">
                        <details v-for="f in faqs" :key="f.q" class="group px-6 py-5">
                            <summary :class="['flex cursor-pointer list-none items-center justify-between gap-4 rounded font-bold', ring]">
                                {{ $t(f.q) }}
                                <span class="text-xl text-[#0E9F7E] transition-transform group-open:rotate-45" aria-hidden="true">+</span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-7 text-[#5A6B7D]">{{ $t(f.a) }}</p>
                        </details>
                    </div>
                </div>
            </section>

            <!-- Final CTA -->
            <section class="bg-gradient-to-b from-[#0E2A47] to-[#0A2038] py-20 text-center text-white lg:py-24">
                <div class="mx-auto max-w-3xl px-5" v-reveal>
                    <AfradIcon class="mx-auto h-14 w-14" aria-hidden="true" />
                    <h2 class="mt-6 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Give your people one home.') }}</h2>
                    <p class="mx-auto mt-4 max-w-xl text-lg leading-8 text-slate-300">{{ $t('Create your workspace and start with your first employees today.') }}</p>
                    <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                        <Link v-if="canRegister" :href="route('register')" :class="btnMint">{{ $t('Create your workspace') }}</Link>
                        <a href="#modules" :class="btnGhost">{{ $t('See what\'s included') }}</a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-[#0A2038] text-slate-400">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-10 text-sm sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div class="flex items-center gap-2.5 text-white">
                    <AfradIcon class="h-8 w-8" aria-hidden="true" />
                    <span class="font-extrabold">{{ $t('Afrad') }}</span>
                </div>
                <nav class="flex flex-wrap gap-x-6 gap-y-2" :aria-label="$t('Footer')">
                    <a href="#modules" class="hover:text-white">{{ $t('Features') }}</a>
                    <a href="#security" class="hover:text-white">{{ $t('Security') }}</a>
                    <a href="#plans" class="hover:text-white">{{ $t('Plans') }}</a>
                    <a href="#faq" class="hover:text-white">{{ $t('FAQ') }}</a>
                </nav>
                <p>© {{ new Date().getFullYear() }} {{ $t('Afrad') }}. {{ $t('All rights reserved.') }}</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.reveal {
    opacity: 0;
    transform: translateY(18px);
    transition:
        opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
    transition-delay: var(--d, 0ms);
}
.reveal.is-visible {
    opacity: 1;
    transform: none;
}

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
.hero-in {
    opacity: 0;
    animation: rise 0.9s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: var(--d, 0ms);
}

@keyframes float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}
.float-slow {
    animation: float 6s ease-in-out infinite;
}

@keyframes drift {
    0%,
    100% {
        transform: translate(0, 0);
    }
    50% {
        transform: translate(40px, 30px);
    }
}
.drift {
    animation: drift 18s ease-in-out infinite;
}
.drift-b {
    animation: drift 22s ease-in-out infinite reverse;
}

.typing {
    overflow: hidden;
    opacity: 0;
    animation:
        rise 0.4s ease 700ms forwards,
        typing-out 0.3s ease 1500ms forwards;
}
@keyframes typing-out {
    from {
        opacity: 1;
        max-height: 24px;
    }
    to {
        opacity: 0;
        max-height: 0;
        margin-top: 0;
    }
}
.dot {
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background: #9aa7bf;
    animation: blink 1s ease-in-out infinite;
}
.dot:nth-child(2) {
    animation-delay: 0.15s;
}
.dot:nth-child(3) {
    animation-delay: 0.3s;
}
@keyframes blink {
    0%,
    80%,
    100% {
        opacity: 0.3;
    }
    40% {
        opacity: 1;
    }
}

details[open] > p {
    animation: fade-down 0.35s ease;
}
@keyframes fade-down {
    from {
        opacity: 0;
        transform: translateY(-6px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .reveal {
        opacity: 1;
        transform: none;
        transition: none;
    }
    .hero-in {
        opacity: 1;
        animation: none;
    }
    .float-slow,
    .drift,
    .drift-b,
    .dot {
        animation: none;
    }
    .typing {
        display: none;
    }
    }

.afrad-font {
    font-family: 'Sora', 'Noto Kufi Arabic', ui-sans-serif, system-ui, sans-serif;
}
:global([dir='rtl']) .afrad-font {
    font-family: 'Noto Kufi Arabic', 'Sora', ui-sans-serif, system-ui, sans-serif;
}
</style>