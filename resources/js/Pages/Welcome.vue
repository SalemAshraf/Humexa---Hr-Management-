<script setup>
import AfradIcon from '@/Components/AfradIcon.vue';
import LanguageSwitch from '@/Components/LanguageSwitch.vue';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canRegister: { type: Boolean, default: true },
});

const chips = ['Employee records', 'Attendance', 'Leave', 'Hiring', 'Performance reviews', 'Document expiry', 'Reports', 'Payroll (experimental)'];

const moments = [
    { n: '01', pain: 'A passport or contract expires and nobody notices until it is a problem.', fix: 'Expiring and expired documents sit in one list, so renewals happen on time.' },
    { n: '02', pain: 'A leave request is buried in a chat thread, and the balance lives in a spreadsheet.', fix: 'Requests go to the manager, get approved in one click, and balances follow.' },
    { n: '03', pain: 'Month-end means a day of copying numbers to build one report.', fix: 'Pick a period, read the summary, export it as CSV.' },
];

const roles = [
    { id: 'owner', t: 'Owner', lead: 'See the whole company without asking anyone.', pts: ['Company dashboard with headcount and today\'s attendance', 'Invite the team and choose each person\'s role', 'Company settings in one place'], rows: [['Employees', '12'], ['Present today', '9'], ['Pending leave requests', '4']] },
    { id: 'hr', t: 'HR', lead: 'Keep every record, request and hire moving.', pts: ['Employee records with archive and restore', 'Attendance, leave and document expiry in one workspace', 'Open positions and candidates through to hiring'], rows: [['Documents expiring soon', '3'], ['Open positions', '2'], ['Attendance requests', '5']] },
    { id: 'manager', t: 'Manager', lead: 'Decide on requests without leaving the page.', pts: ['Review leave and attendance requests from your team', 'Reports limited to your direct reports', 'Write and finalize performance reviews'], rows: [['Team members', '6'], ['Waiting for you', '2'], ['Reviews to finish', '1']] },
    { id: 'employee', t: 'Employee', lead: 'A simple space for the things that are yours.', pts: ['Your own attendance record', 'Request leave and see your balance', 'Your profile and account'], rows: [['Annual leave left', '12 days'], ['Last check-in', '08:52'], ['Open requests', '1']] },
];
const activeRole = ref('hr');

const docs = [
    { who: 'Ahmed Hassan', what: 'Passport', when: 'Expired', tone: 'bg-red-50 text-red-700' },
    { who: 'Mona Adel', what: 'Work contract', when: '12 days left', tone: 'bg-amber-50 text-amber-700' },
    { who: 'Karim Samir', what: 'Health card', when: '27 days left', tone: 'bg-amber-50 text-amber-700' },
];

const stages = [
    { s: 'Applied', n: 14 },
    { s: 'Screening', n: 9 },
    { s: 'Interview', n: 5 },
    { s: 'Offer', n: 2 },
    { s: 'Hired', n: 1 },
];

const bars = [38, 52, 44, 61, 57, 70];

const faqs = [
    { q: 'Is my company data separated from other companies?', a: 'Yes. Afrad is built as a multi-tenant platform where each organization has its own workspace, and its data is kept isolated from every other organization.' },
    { q: 'Is payroll ready for production use?', a: 'Not yet. Payroll is an experimental estimator. It is not an official payslip and its results must be reviewed by an accountant before anyone relies on them.' },
    { q: 'Can I use it in Arabic?', a: 'Yes. The interface works in Arabic and English with a language switch and right-to-left layout. Names and other data you enter stay exactly as you typed them.' },
    { q: 'Does it have an AI assistant?', a: 'Not yet. It is planned, and no AI connection exists today.' },
    { q: 'What happens at a plan limit?', a: 'Adding a new employee or user account is blocked once you reach the limit. Existing records are never hidden or deleted.' },
    { q: 'How do I get started?', a: 'Create your workspace, add your departments and employees, and invite your team. You can start with one area and add the others as you need them.' },
];

const plans = [
    { name: 'Starter', emp: 'Up to 25 employees', acc: 'Up to 5 user accounts' },
    { name: 'Growth', emp: 'Up to 100 employees', acc: 'Up to 15 user accounts', hot: true },
    { name: 'Business', emp: 'Up to 500 employees', acc: 'Up to 50 user accounts' },
];

const leaveState = ref('pending');
const scrolled = ref(false);
function onScroll() { scrolled.value = window.scrollY > 8; }
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const vReveal = {
    mounted(el, binding) {
        el.classList.add('reveal');
        if (binding.value) el.style.setProperty('--d', `${binding.value}ms`);
        if (reduceMotion() || !('IntersectionObserver' in window)) { el.classList.add('is-visible'); return; }
        const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { el.classList.add('is-visible'); io.disconnect(); } }, { threshold: 0.1 });
        io.observe(el);
    },
};
onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }); });
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));

const ring = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2BD9A9]';
const btnMint = `inline-flex items-center justify-center rounded-xl bg-[#2BD9A9] px-6 py-3.5 text-sm font-bold text-[#0A2038] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#4BE5BB] hover:shadow-lg hover:shadow-[#2BD9A9]/25 ${ring}`;
const btnGhost = `inline-flex items-center justify-center rounded-xl border border-white/25 px-6 py-3.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-white/10 ${ring}`;
const btnDark = `inline-flex items-center justify-center rounded-xl border border-[#0E2A47]/20 px-6 py-3.5 text-sm font-semibold text-[#0E2A47] transition-all hover:bg-[#0E2A47]/5 ${ring}`;
</script>

<template>
    <Head>
        <title>{{ $t('Afrad | HR software for growing companies') }}</title>
        <meta name="description" content="Employee records, attendance, leave, hiring and reviews in one workspace, in Arabic and English." />
        <meta name="theme-color" content="#0A2038" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Noto+Kufi+Arabic:wght@400;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="afrad-font relative min-h-screen overflow-x-clip bg-[#F5F8FA] text-[#10304F] antialiased">
        <header :class="['sticky top-0 z-30 border-b border-white/10 bg-[#0A2038]/90 backdrop-blur-md transition-shadow', scrolled ? 'shadow-lg shadow-black/20' : '']">
            <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-5 lg:px-8" :aria-label="$t('Main')">
                <a href="#" :class="['flex items-center gap-2.5 rounded-md', ring]">
                    <AfradIcon class="h-9 w-9" aria-hidden="true" />
                    <span class="text-lg font-extrabold text-white">{{ $t('Afrad') }}</span>
                </a>
                <div class="hidden items-center gap-7 text-sm font-medium text-slate-300 lg:flex">
                    <a href="#days" class="hover:text-white">{{ $t('Why Afrad') }}</a>
                    <a href="#roles" class="hover:text-white">{{ $t('Roles') }}</a>
                    <a href="#inside" class="hover:text-white">{{ $t('Inside') }}</a>
                    <a href="#plans" class="hover:text-white">{{ $t('Plans') }}</a>
                    <a href="#faq" class="hover:text-white">{{ $t('FAQ') }}</a>
                </div>
                <div class="flex items-center gap-2">
                    <LanguageSwitch dark />
                    <Link :href="route('login')" :class="['hidden rounded-xl px-3 py-2.5 text-sm font-semibold text-white hover:bg-white/10 sm:inline-flex', ring]">{{ $t('Sign in') }}</Link>
                    <Link v-if="canRegister" :href="route('register')" :class="['inline-flex rounded-xl bg-[#2BD9A9] px-4 py-2.5 text-sm font-bold text-[#0A2038] hover:bg-[#4BE5BB]', ring]">{{ $t('Get started') }}</Link>
                </div>
            </nav>
        </header>

        <main>
            <!-- 1. Hero -->
            <section class="relative bg-gradient-to-b from-[#0A2038] via-[#0E2A47] to-[#13395F] text-white">
                <div class="pointer-events-none absolute -top-24 end-0 h-80 w-80 rounded-full bg-[#2BD9A9]/15 blur-[100px] drift" aria-hidden="true"></div>
                <div class="mx-auto max-w-6xl px-5 pb-0 pt-14 text-center lg:px-8 lg:pt-20">
                    <h1 class="mx-auto max-w-3xl text-4xl font-extrabold leading-[1.25] sm:text-5xl lg:text-[56px]">
                        <span class="hero-in block" style="--d: 0ms">{{ $t('Your people deserve more') }}</span>
                        <span class="hero-in block text-[#2BD9A9]" style="--d: 120ms">{{ $t('than a spreadsheet.') }}</span>
                    </h1>
                    <p class="hero-in mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-300" style="--d: 240ms">{{ $t('Afrad brings employee records, attendance, leave, hiring and reviews into one calm workspace, in Arabic and English.') }}</p>
                    <div class="hero-in mt-9 flex flex-col justify-center gap-3 sm:flex-row" style="--d: 360ms">
                        <Link v-if="canRegister" :href="route('register')" :class="btnMint">{{ $t('Create your workspace') }}</Link>
                        <a href="#days" :class="btnGhost">{{ $t('See how it helps') }}</a>
                    </div>

                    <!-- Product composition -->
                    <div class="hero-in relative mx-auto mt-16 max-w-5xl translate-y-0 text-start" style="--d: 480ms">
                        <div class="overflow-hidden rounded-t-2xl border border-white/10 bg-[#F5F8FA] text-[#10304F] shadow-2xl shadow-black/40">
                            <div class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                                <span class="flex items-center gap-2 text-sm font-bold"><AfradIcon class="h-6 w-6" aria-hidden="true" />{{ $t('Company overview') }}</span>
                                <span class="text-xs text-slate-400">{{ $t('Sample data') }}</span>
                            </div>
                            <div class="grid gap-4 p-5 md:grid-cols-4">
                                <div v-for="s in [{ l: 'Employees', v: '12' }, { l: 'Present today', v: '9' }, { l: 'On leave', v: '2' }, { l: 'Pending requests', v: '4' }]" :key="s.l" class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs text-slate-500">{{ $t(s.l) }}</p>
                                    <p class="mt-1 text-2xl font-extrabold">{{ s.v }}</p>
                                </div>
                            </div>
                            <div class="grid gap-4 px-5 pb-8 md:grid-cols-[1.4fr_1fr]">
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-sm font-bold">{{ $t('Headcount, last 6 months') }}</p>
                                    <div class="mt-4 flex h-28 items-end gap-3" dir="ltr">
                                        <div v-for="(b, i) in bars" :key="i" class="flex-1 rounded-t-md bg-[#2BD9A9]" :style="{ height: b + '%' }"></div>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-semibold text-slate-500">{{ $t('Leave request') }}</p>
                                    <p class="mt-1 text-sm font-bold">{{ $t('Mona Adel, 3 days') }}</p>
                                    <div class="mt-4 flex items-center justify-between">
                                        <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', leaveState === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700']">{{ leaveState === 'pending' ? $t('Pending') : $t('Approved') }}</span>
                                        <button v-if="leaveState === 'pending'" type="button" :class="['rounded-lg bg-[#0E2A47] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#13395F]', ring]" @click="leaveState = 'approved'">{{ $t('Approve') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. One-line scope -->
            <section class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-2.5 px-5 py-8 lg:px-8">
                    <span v-for="c in chips" :key="c" class="rounded-full border border-slate-200 bg-[#F5F8FA] px-4 py-2 text-sm font-semibold text-[#0E2A47]">{{ $t(c) }}</span>
                </div>
            </section>

            <!-- 3. Why: moments -->
            <section id="days" class="mx-auto max-w-5xl scroll-mt-20 px-5 py-24 lg:px-8 lg:py-32">
                <div class="mx-auto max-w-2xl text-center" v-reveal>
                    <h2 class="text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('HR is mostly small things that pile up.') }}</h2>
                    <p class="mt-5 text-lg leading-8 text-[#5A6B7D]">{{ $t('Afrad is built to catch them before they do.') }}</p>
                </div>
                <div class="mt-16 space-y-6">
                    <div v-for="(m, i) in moments" :key="m.n" v-reveal="i * 60" class="grid overflow-hidden rounded-2xl border border-slate-200 bg-white md:grid-cols-2">
                        <div class="p-7 md:p-9">
                            <p class="text-sm font-extrabold text-slate-300">{{ m.n }}</p>
                            <p class="mt-3 text-lg font-semibold leading-8 text-[#5A6B7D]">{{ $t(m.pain) }}</p>
                        </div>
                        <div class="bg-[#0E2A47] p-7 text-white md:p-9">
                            <p class="text-sm font-bold text-[#2BD9A9]">{{ $t('With Afrad') }}</p>
                            <p class="mt-3 text-lg font-semibold leading-8">{{ $t(m.fix) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4. Roles -->
            <section id="roles" class="scroll-mt-20 bg-white py-24 lg:py-32">
                <div class="mx-auto max-w-5xl px-5 lg:px-8">
                    <div class="max-w-2xl" v-reveal>
                        <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('Roles') }}</p>
                        <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('One workspace, four different views') }}</h2>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Everyone sees what their job needs, and nothing more.') }}</p>
                    </div>
                    <div class="mt-10 flex flex-wrap gap-2" role="tablist">
                        <button v-for="r in roles" :key="r.id" type="button" role="tab" :aria-selected="activeRole === r.id" :class="['rounded-full px-5 py-2.5 text-sm font-bold transition-colors', activeRole === r.id ? 'bg-[#0E2A47] text-white' : 'bg-[#F5F8FA] text-[#0E2A47] hover:bg-slate-200', ring]" @click="activeRole = r.id">{{ $t(r.t) }}</button>
                    </div>
                    <div v-for="r in roles.filter((x) => x.id === activeRole)" :key="r.id" class="mt-8 grid items-center gap-8 rounded-2xl bg-[#F5F8FA] p-7 md:grid-cols-2 md:p-10">
                        <div>
                            <h3 class="text-2xl font-extrabold leading-[1.4]">{{ $t(r.lead) }}</h3>
                            <ul class="mt-6 space-y-3 text-[15px] leading-7">
                                <li v-for="p in r.pts" :key="p" class="flex gap-3"><span class="mt-0.5 text-[#0E9F7E]" aria-hidden="true">✓</span>{{ $t(p) }}</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold text-slate-400">{{ $t('Sample data') }}</p>
                            <div v-for="row in r.rows" :key="row[0]" class="flex items-center justify-between border-b border-slate-100 py-3.5 last:border-0">
                                <span class="text-sm text-[#5A6B7D]">{{ $t(row[0]) }}</span>
                                <span class="text-lg font-extrabold">{{ row[1] === '12 days' ? $t('12 days') : row[1] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. Inside: alternating stories -->
            <section id="inside" class="mx-auto max-w-6xl scroll-mt-20 px-5 py-24 lg:px-8 lg:py-32">
                <div class="mx-auto max-w-2xl text-center" v-reveal>
                    <p class="text-sm font-bold text-[#0E9F7E]">{{ $t('Inside') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('A closer look at the work Afrad takes off your plate') }}</h2>
                </div>

                <!-- Documents -->
                <div class="mt-20 grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div v-reveal>
                        <h3 class="text-2xl font-extrabold leading-[1.4] sm:text-3xl">{{ $t('Never miss a renewal') }}</h3>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Expired documents and the ones expiring soon appear together, with the employee next to each one.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" v-reveal="100">
                        <p class="text-xs font-semibold text-slate-400">{{ $t('Sample data') }}</p>
                        <div v-for="d in docs" :key="d.who" class="flex items-center justify-between gap-3 border-b border-slate-100 py-3.5 last:border-0">
                            <div><p class="text-sm font-bold">{{ $t(d.who) }}</p><p class="text-xs text-slate-500">{{ $t(d.what) }}</p></div>
                            <span :class="['shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold', d.tone]">{{ $t(d.when) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Leave + attendance -->
                <div class="mt-24 grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div class="rounded-2xl bg-[#0E2A47] p-6 text-white shadow-xl lg:order-first" v-reveal>
                        <p class="text-sm font-semibold text-slate-300">{{ $t('Leave balance') }} <span class="text-xs text-slate-500">({{ $t('Sample data') }})</span></p>
                        <div class="mt-5 space-y-5">
                            <div v-for="b in [{ n: 'Annual leave', u: 9, t: 21 }, { n: 'Casual leave', u: 2, t: 6 }, { n: 'Sick leave', u: 1, t: 10 }]" :key="b.n">
                                <div class="flex justify-between text-sm"><span>{{ $t(b.n) }}</span><span class="tabular-nums text-slate-300" dir="ltr">{{ b.u }} / {{ b.t }}</span></div>
                                <div class="mt-2 h-2 rounded-full bg-white/10"><div class="h-2 rounded-full bg-[#2BD9A9]" :style="{ width: (b.u / b.t) * 100 + '%' }"></div></div>
                            </div>
                        </div>
                    </div>
                    <div v-reveal="100">
                        <h3 class="text-2xl font-extrabold leading-[1.4] sm:text-3xl">{{ $t('Leave and attendance without the chasing') }}</h3>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Employees request, managers approve, and balances follow the approved requests. Late arrivals show up in the daily attendance record.') }}</p>
                    </div>
                </div>

                <!-- Hiring -->
                <div class="mt-24 grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div v-reveal>
                        <h3 class="text-2xl font-extrabold leading-[1.4] sm:text-3xl">{{ $t('From first application to first day') }}</h3>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Move candidates through the stages, and turn the one you choose into an employee in a single step.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" v-reveal="100">
                        <p class="text-xs font-semibold text-slate-400">{{ $t('Sample data') }}</p>
                        <div class="mt-4 space-y-3">
                            <div v-for="s in stages" :key="s.s" class="flex items-center gap-3">
                                <span class="w-24 shrink-0 text-sm text-[#5A6B7D]">{{ $t(s.s) }}</span>
                                <div class="h-7 flex-1 rounded-md bg-slate-100"><div class="flex h-7 items-center rounded-md bg-[#0E2A47] px-2 text-xs font-bold text-white" :style="{ width: Math.max((s.n / 14) * 100, 9) + '%' }">{{ s.n }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports -->
                <div class="mt-24 grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:order-first" v-reveal>
                        <div class="flex items-center justify-between"><p class="text-sm font-bold">{{ $t('Reports') }}</p><span class="rounded-lg bg-[#F5F8FA] px-3 py-1.5 text-xs font-semibold text-[#0E2A47] ring-1 ring-slate-200">{{ $t('Export CSV') }}</span></div>
                        <p class="mt-1 text-xs text-slate-400">{{ $t('Sample data') }}</p>
                        <div class="mt-5 flex h-32 items-end gap-3" dir="ltr">
                            <div v-for="(b, i) in bars" :key="i" class="flex-1 rounded-t-md bg-[#13395F]" :style="{ height: b + '%' }"></div>
                        </div>
                    </div>
                    <div v-reveal="100">
                        <h3 class="text-2xl font-extrabold leading-[1.4] sm:text-3xl">{{ $t('Numbers ready when management asks') }}</h3>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Pick a period, read the people numbers for the whole company or for your own team, and export them as CSV.') }}</p>
                    </div>
                </div>
            </section>

            <!-- 6. Language band -->
            <section class="bg-[#2BD9A9] py-20 text-[#0A2038] lg:py-24">
                <div class="mx-auto grid max-w-5xl items-center gap-10 px-5 md:grid-cols-2 lg:px-8">
                    <div v-reveal>
                        <h2 class="text-3xl font-extrabold leading-[1.35] sm:text-4xl">{{ $t('Arabic and English, switched in one tap.') }}</h2>
                        <p class="mt-4 text-lg leading-8 text-[#0A2038]/80">{{ $t('The whole interface flips, including right-to-left layout. Names and notes you type are never translated or changed.') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4" v-reveal="100">
                        <div class="rounded-2xl bg-[#0A2038] p-7 text-center text-white" dir="rtl"><p class="text-4xl font-extrabold">أفراد</p><p class="mt-2 text-sm text-slate-300">العربية</p></div>
                        <div class="rounded-2xl bg-white p-7 text-center" dir="ltr"><p class="text-4xl font-extrabold">Afrad</p><p class="mt-2 text-sm text-[#5A6B7D]">English</p></div>
                    </div>
                </div>
            </section>

            <!-- 7. Also included -->
            <section class="mx-auto max-w-5xl px-5 py-24 lg:px-8">
                <h2 class="text-2xl font-extrabold leading-[1.4] sm:text-3xl" v-reveal>{{ $t('Also in the workspace') }}</h2>
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(a, i) in [
                        ['Performance reviews', 'Review cycles that can be finalized, and reopened when something needs to change.'],
                        ['Organization', 'Branches, departments and positions, so the structure is clear.'],
                        ['Notifications', 'In-app notifications for requests and approvals.'],
                        ['Team and roles', 'Give each person the access their job needs.'],
                        ['Search', 'Find people and records from anywhere in the workspace.'],
                        ['Archive and restore', 'Archive employees without losing their records, and bring them back when needed.'],
                    ]" :key="a[0]" v-reveal="(i % 3) * 60" class="rounded-2xl border border-slate-200 bg-white p-6">
                        <p class="font-bold">{{ $t(a[0]) }}</p>
                        <p class="mt-2 text-sm leading-7 text-[#5A6B7D]">{{ $t(a[1]) }}</p>
                    </div>
                </div>
            </section>

            <!-- 8. Payroll honesty -->
            <section class="mx-auto max-w-5xl px-5 pb-24 lg:px-8">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-7 md:flex md:items-start md:gap-6 md:p-9" v-reveal>
                    <span class="mb-4 inline-flex shrink-0 rounded-full bg-amber-200 px-3 py-1 text-xs font-bold text-amber-900 md:mb-0">{{ $t('Experimental') }}</span>
                    <div>
                        <h2 class="text-xl font-extrabold leading-[1.4]">{{ $t('Payroll is an estimator, not a payroll system yet.') }}</h2>
                        <p class="mt-2 text-[15px] leading-7 text-amber-950/80">{{ $t('Afrad can estimate a monthly pay run to help you plan. The results are not official payslips, rates and rules may change, and an accountant must review them before anyone relies on a number.') }}</p>
                    </div>
                </div>
            </section>

            <!-- 9. Security -->
            <section class="bg-[#0E2A47] py-20 text-white lg:py-24">
                <div class="mx-auto max-w-5xl px-5 lg:px-8">
                    <h2 class="max-w-2xl text-3xl font-extrabold leading-[1.35] sm:text-4xl" v-reveal>{{ $t('Your people data stays yours.') }}</h2>
                    <div class="mt-10 grid gap-8 md:grid-cols-3">
                        <div v-for="(s, i) in [
                            ['Isolated workspaces', 'Each company works in its own workspace, kept separate from every other company.'],
                            ['Role-based access', 'Permissions follow the role, and sensitive employee data is limited to authorized users.'],
                            ['Limits that protect', 'Plan limits only stop new additions. Existing records are never hidden or deleted.'],
                        ]" :key="s[0]" v-reveal="i * 80" class="border-t border-[#2BD9A9]/50 pt-5">
                            <p class="text-lg font-bold">{{ $t(s[0]) }}</p>
                            <p class="mt-2 text-[15px] leading-7 text-slate-300">{{ $t(s[1]) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 10. Plans -->
            <section id="plans" class="mx-auto max-w-5xl scroll-mt-20 px-5 py-24 lg:px-8 lg:py-28">
                <div class="max-w-2xl" v-reveal>
                    <h2 class="text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Plans that follow your size') }}</h2>
                    <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Plans set limits on employees and user accounts. The Afrad team assigns them, and there is no online payment yet.') }}</p>
                </div>
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <div v-for="(p, i) in plans" :key="p.name" v-reveal="i * 80" :class="['rounded-2xl border p-7', p.hot ? 'border-[#2BD9A9] bg-[#0E2A47] text-white' : 'border-slate-200 bg-white']">
                        <p class="text-lg font-extrabold">{{ $t(p.name) }}</p>
                        <p class="mt-4 text-[15px]">{{ $t(p.emp) }}</p>
                        <p class="mt-1 text-[15px]">{{ $t(p.acc) }}</p>
                    </div>
                </div>
            </section>

            <!-- 11. FAQ -->
            <section id="faq" class="scroll-mt-20 bg-white py-24 lg:py-28">
                <div class="mx-auto max-w-3xl px-5 lg:px-8">
                    <h2 class="text-3xl font-extrabold leading-[1.3] sm:text-4xl" v-reveal>{{ $t('Questions, answered') }}</h2>
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

            <!-- 12. CTA -->
            <section class="bg-gradient-to-b from-[#0E2A47] to-[#0A2038] py-20 text-center text-white lg:py-24">
                <div class="mx-auto max-w-3xl px-5" v-reveal>
                    <AfradIcon class="mx-auto h-14 w-14" aria-hidden="true" />
                    <h2 class="mt-6 text-3xl font-extrabold leading-[1.3] sm:text-4xl">{{ $t('Give your people one home.') }}</h2>
                    <p class="mx-auto mt-4 max-w-xl text-lg leading-8 text-slate-300">{{ $t('Create your workspace and start with your first employees today.') }}</p>
                    <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                        <Link v-if="canRegister" :href="route('register')" :class="btnMint">{{ $t('Create your workspace') }}</Link>
                        <Link :href="route('login')" :class="btnGhost">{{ $t('Sign in') }}</Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-[#0A2038] text-slate-400">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-10 text-sm sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div class="flex items-center gap-2.5 text-white"><AfradIcon class="h-8 w-8" aria-hidden="true" /><span class="font-extrabold">{{ $t('Afrad') }}</span></div>
                <nav class="flex flex-wrap gap-x-6 gap-y-2" :aria-label="$t('Footer')">
                    <a href="#days" class="hover:text-white">{{ $t('Why Afrad') }}</a>
                    <a href="#plans" class="hover:text-white">{{ $t('Plans') }}</a>
                    <a href="#faq" class="hover:text-white">{{ $t('FAQ') }}</a>
                    <Link :href="route('login')" class="hover:text-white">{{ $t('Sign in') }}</Link>
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