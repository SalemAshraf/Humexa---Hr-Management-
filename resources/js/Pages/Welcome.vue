<script setup>
import '@fontsource/ibm-plex-sans-arabic/arabic-400.css';
import '@fontsource/ibm-plex-sans-arabic/arabic-500.css';
import '@fontsource/ibm-plex-sans-arabic/arabic-600.css';
import '@fontsource/ibm-plex-sans-arabic/arabic-700.css';
import '@fontsource/ibm-plex-sans-arabic/latin-400.css';
import '@fontsource/ibm-plex-sans-arabic/latin-600.css';
import '@fontsource/ibm-plex-sans-arabic/latin-700.css';
import AfradIcon from '@/Components/AfradIcon.vue';
import LanguageSwitch from '@/Components/LanguageSwitch.vue';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canRegister: { type: Boolean, default: true },
});

const chips = ['Employee records', 'Attendance', 'Leave', 'Hiring', 'Performance reviews', 'Document expiry', 'Reports', 'Payroll'];

const moments = [
    { n: '01', pain: 'A passport or contract expires and nobody notices until it is a problem.', fix: 'Expiring and expired documents sit in one list, so renewals happen on time.' },
    { n: '02', pain: 'A leave request is buried in a chat thread, and the balance lives in a spreadsheet.', fix: 'Requests go to the manager, get approved in one click, and balances follow.' },
    { n: '03', pain: 'Month-end means a day of copying numbers to build one report.', fix: 'Pick a period, read the summary, export it as CSV.' },
];

const roles = [
    { id: 'owner', t: 'Owner', lead: 'See the whole company without asking anyone.', pts: ['Company dashboard with headcount and today\'s attendance', 'Invite the team and choose each person\'s role', 'Company settings in one place'], rows: [['Employees', '48'], ['Present today', '41'], ['Pending leave requests', '4'], ['Departments', '6'], ['Open positions', '3'], ['Documents expiring soon', '7']] },
    { id: 'hr', t: 'HR', lead: 'Keep every record, request and hire moving.', pts: ['Employee records with archive and restore', 'Attendance, leave and document expiry in one workspace', 'Open positions and candidates through to hiring'], rows: [['Documents expiring soon', '7'], ['Open positions', '3'], ['Attendance requests', '5'], ['Candidates in pipeline', '29'], ['New hires this month', '4'], ['Employees on leave', '3']] },
    { id: 'manager', t: 'Manager', lead: 'Decide on requests without leaving the page.', pts: ['Review leave and attendance requests from your team', 'Reports limited to your direct reports', 'Write and finalize performance reviews'], rows: [['Team members', '8'], ['Waiting for you', '3'], ['Reviews to finish', '2'], ['Present today', '6'], ['On leave this week', '1'], ['Late arrivals this week', '2']] },
    { id: 'employee', t: 'Employee', lead: 'A simple space for the things that are yours.', pts: ['Your own attendance record', 'Request leave and see your balance', 'Your profile and account'], rows: [['Annual leave left', '12 days'], ['Sick leave left', '9 days'], ['Last check-in', '08:52'], ['Open requests', '1'], ['Days worked this month', '15'], ['Late arrivals this month', '1']] },
];
const activeRole = ref('hr');

const docs = [
    { who: 'Ahmed Hassan', what: 'Passport', when: 'Expired', tone: 'bg-red-50 text-red-700' },
    { who: 'Mona Adel', what: 'Work contract', when: '12 days left', tone: 'bg-amber-50 text-amber-700' },
    { who: 'Karim Samir', what: 'Health card', when: '27 days left', tone: 'bg-amber-50 text-amber-700' },
    { who: 'Sara Mostafa', what: 'ID card', when: 'Expired', tone: 'bg-red-50 text-red-700' },
    { who: 'Tarek Salem', what: 'Driving licence', when: '5 days left', tone: 'bg-amber-50 text-amber-700' },
    { who: 'Nour Ibrahim', what: 'Work contract', when: '19 days left', tone: 'bg-amber-50 text-amber-700' },
    { who: 'Hossam Ali', what: 'Passport', when: '34 days left', tone: 'bg-slate-100 text-slate-600' },
];

const stages = [
    { s: 'Applied', n: 42 },
    { s: 'Screening', n: 18 },
    { s: 'Interview', n: 9 },
    { s: 'Offer', n: 3 },
    { s: 'Hired', n: 2 },
];

const bars = [52, 58, 64, 72, 82, 94];
const hc = [128, 131, 135, 139, 144, 148];
const months = ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'];
const kpis = [{ l: 'Employees', v: '148' }, { l: 'Present today', v: '126' }, { l: 'On leave', v: '9' }, { l: 'Late today', v: '7' }, { l: 'Open positions', v: '5' }, { l: 'Pending requests', v: '14' }];
const depts = [{ n: 'Operations', c: 38 }, { n: 'Sales', c: 31 }, { n: 'Customer Service', c: 24 }, { n: 'IT', c: 19 }, { n: 'Finance', c: 14 }, { n: 'Human Resources', c: 9 }];
const attToday = [
    { w: 'Ahmed Hassan', d: 'Operations', t: '08:47', s: 'On time', tone: 'bg-emerald-50 text-emerald-700' },
    { w: 'Sara Mostafa', d: 'Sales', t: '08:55', s: 'On time', tone: 'bg-emerald-50 text-emerald-700' },
    { w: 'Omar Khaled', d: 'IT', t: '09:22', s: 'Late', tone: 'bg-amber-50 text-amber-700' },
    { w: 'Hana Fathy', d: 'Finance', t: '08:41', s: 'On time', tone: 'bg-emerald-50 text-emerald-700' },
    { w: 'Tarek Salem', d: 'Customer Service', t: '09:35', s: 'Late', tone: 'bg-amber-50 text-amber-700' },
];
const leaveList = [
    { w: 'Mona Adel', k: 'Annual leave', n: '3 days' },
    { w: 'Youssef Nabil', k: 'Sick leave', n: '2 days' },
    { w: 'Dina Ramy', k: 'Annual leave', n: '5 days' },
];
const leaveDone = [{ w: 'Laila Fathy', k: 'Casual leave', n: '1 day' }];

const faqs = [
    { q: 'Is my company data separated from other companies?', a: 'Yes. Afrad is built as a multi-tenant platform where each organization has its own workspace, and its data is kept isolated from every other organization.' },
    { q: 'Does Afrad pay salaries or file taxes?', a: 'No. Afrad calculates pay from the rules your company enters and keeps the result. It does not move money or file anything, and your accountant should review the numbers before anyone is paid.' },
    { q: 'Can I use it in Arabic?', a: 'Yes. The interface works in Arabic and English with a language switch and right-to-left layout. Names and other data you enter stay exactly as you typed them.' },
    { q: 'Does it have an AI assistant?', a: 'There is no assistant inside Afrad. You can connect your own Claude account to Afrad, read-only, to ask about company totals. It never receives names, contact details, salaries, ID numbers or documents, and it cannot change anything.' },
    { q: 'What happens at a plan limit?', a: 'Adding a new employee or user account is blocked once you reach the limit. Existing records are never hidden or deleted.' },
    { q: 'How do I get started?', a: 'Create your workspace, add your departments and employees, and invite your team. You can start with one area and add the others as you need them.' },
];

const aiAsks = [
    'How many people work here, by department?',
    'How many people are late today?',
    'How many leave requests are waiting for approval?',
    'Which positions are open, and how many candidates are at each stage?',
];
const aiTrust = [
    ['Read-only', 'The connection can read. It cannot add, edit, approve or delete anything.'],
    ['Totals, not people', 'Answers are counts. No names, contact details, salaries, ID numbers or documents are sent.'],
    ['Follows your role', 'Each person can only ask about what their Afrad role already lets them see.'],
    ['You stay in control', 'You approve each connection, and you can disconnect it at any time in Settings.'],
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
const btnGhost = `inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-[#0E2A47] transition-all duration-200 hover:bg-slate-100 ${ring}`;
const btnGhostDark = `inline-flex items-center justify-center rounded-xl border border-white/25 px-6 py-3.5 text-sm font-semibold text-white transition-all duration-200 hover:bg-white/10 ${ring}`;
const btnDark = `inline-flex items-center justify-center rounded-xl border border-[#0E2A47]/20 px-6 py-3.5 text-sm font-semibold text-[#0E2A47] transition-all hover:bg-[#0E2A47]/5 ${ring}`;
</script>

<template>
    <Head>
        <title>{{ $t('Afrad | HR software for growing companies') }}</title>
        <meta name="description" content="Employee records, attendance, leave, hiring and reviews in one workspace, in Arabic and English." />
        <meta name="theme-color" content="#F3F6F8" />
    </Head>

    <div class="afrad-font relative min-h-screen overflow-x-clip bg-[#F3F6F8] text-[#0E2A47] antialiased">
        <header :class="['sticky top-0 z-30 border-b border-slate-200/70 bg-[#F3F6F8]/90 backdrop-blur transition-shadow', scrolled ? 'shadow-sm' : '']">
            <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-5 lg:px-8" :aria-label="$t('Main')">
                <a href="#" :class="['flex items-center gap-2.5 rounded-md', ring]">
                    <AfradIcon class="h-9 w-9" aria-hidden="true" />
                    <span class="text-lg font-bold text-[#0E2A47]">{{ $t('Afrad') }}</span>
                </a>
                <div class="hidden items-center gap-7 text-sm font-medium text-slate-600 lg:flex">
                    <a href="#days" class="hover:text-[#0E2A47]">{{ $t('Why Afrad') }}</a>
                    <a href="#roles" class="hover:text-[#0E2A47]">{{ $t('Roles') }}</a>
                    <a href="#inside" class="hover:text-[#0E2A47]">{{ $t('Inside') }}</a>
                    <a href="#ai" class="hover:text-[#0E2A47]">{{ $t('AI') }}</a>
                    <a href="#plans" class="hover:text-[#0E2A47]">{{ $t('Plans') }}</a>
                    <a href="#faq" class="hover:text-[#0E2A47]">{{ $t('FAQ') }}</a>
                </div>
                <div class="flex items-center gap-2">
                    <LanguageSwitch />
                    <Link :href="route('login')" :class="['hidden rounded-xl px-3 py-2.5 text-sm font-semibold text-[#0E2A47] hover:bg-slate-200/70 sm:inline-flex', ring]">{{ $t('Sign in') }}</Link>
                    <Link v-if="canRegister" :href="route('register')" :class="['inline-flex rounded-xl bg-[#2BD9A9] px-4 py-2.5 text-sm font-bold text-[#0A2038] hover:bg-[#4BE5BB]', ring]">{{ $t('Get started') }}</Link>
                </div>
            </nav>
        </header>

        <main>
            <!-- 1. Hero -->
            <section class="relative overflow-hidden">
                <div class="pointer-events-none absolute -top-24 end-0 h-80 w-80 rounded-full bg-[#2BD9A9]/25 blur-[100px] drift" aria-hidden="true"></div>
                <div class="relative mx-auto max-w-6xl px-5 pb-12 pt-14 text-center lg:px-8 lg:pt-20">
                    <p class="hero-in mx-auto mb-5 inline-block rounded-full border border-slate-200 bg-white px-4 py-1 text-xs font-semibold text-[#0B7A60]" style="--d: 0ms">{{ $t('HR software for growing companies') }}</p>
                    <h1 class="mx-auto max-w-3xl text-4xl font-bold leading-[1.25] tracking-tight sm:text-5xl lg:text-[54px]">
                        <span class="hero-in block" style="--d: 0ms">{{ $t('Your people deserve more') }}</span>
                        <span class="hero-in block text-[#0B7A60]" style="--d: 120ms">{{ $t('than a spreadsheet.') }}</span>
                    </h1>
                    <p class="hero-in mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600" style="--d: 240ms">{{ $t('Afrad brings employee records, attendance, leave, hiring and reviews into one calm workspace, in Arabic and English.') }}</p>
                    <div class="hero-in mt-8 flex flex-col justify-center gap-3 sm:flex-row" style="--d: 360ms">
                        <Link v-if="canRegister" :href="route('register')" :class="btnMint">{{ $t('Create your workspace') }}</Link>
                        <a href="#days" :class="btnGhost">{{ $t('See how it helps') }}</a>
                    </div>

                    <!-- Product composition -->
                    <div class="hero-in relative mx-auto mt-12 max-w-5xl text-start" style="--d: 480ms">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-[#F3F6F8] text-[#0E2A47] shadow-2xl shadow-slate-900/10">
                            <div class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                                <span class="flex items-center gap-2 text-sm font-bold"><AfradIcon class="h-6 w-6" aria-hidden="true" />{{ $t('Company overview') }}</span>
                                <span class="text-xs text-slate-400">{{ $t('Sample data') }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3 p-5 pb-3 md:grid-cols-6">
                                <div v-for="s in kpis" :key="s.l" class="rounded-xl border border-slate-200 bg-white p-3.5">
                                    <p class="text-xs text-slate-500">{{ $t(s.l) }}</p>
                                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ s.v }}</p>
                                </div>
                            </div>
                            <div class="grid gap-3 px-5 pb-3 md:grid-cols-3">
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-sm font-bold">{{ $t('Headcount by department') }}</p>
                                    <div class="mt-3 space-y-2.5">
                                        <div v-for="d in depts" :key="d.n">
                                            <div class="flex justify-between text-xs"><span class="text-slate-600">{{ $t(d.n) }}</span><span class="font-bold tabular-nums">{{ d.c }}</span></div>
                                            <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-[#2BD9A9]" :style="{ width: (d.c / 38) * 100 + '%' }"></div></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-sm font-bold">{{ $t("Today's attendance") }}</p>
                                    <div class="mt-2">
                                        <div v-for="r in attToday" :key="r.w" class="flex items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-0">
                                            <div class="min-w-0"><p class="truncate text-xs font-bold">{{ $t(r.w) }}</p><p class="truncate text-[11px] text-slate-500">{{ $t(r.d) }} · <span dir="ltr">{{ r.t }}</span></p></div>
                                            <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold', r.tone]">{{ $t(r.s) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-sm font-bold">{{ $t('Leave requests') }}</p>
                                    <div class="mt-2">
                                        <div v-for="(r, i) in leaveList" :key="r.w" class="flex items-center justify-between gap-2 border-b border-slate-100 py-2">
                                            <div class="min-w-0"><p class="truncate text-xs font-bold">{{ $t(r.w) }}</p><p class="truncate text-[11px] text-slate-500">{{ $t(r.k) }} · {{ $t(r.n) }}</p></div>
                                            <template v-if="i === 0">
                                                <span v-if="leaveState === 'pending'" class="flex shrink-0 items-center gap-2"><span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">{{ $t('Pending') }}</span><button type="button" :class="['rounded-lg bg-[#0E2A47] px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-[#13395F]', ring]" @click="leaveState = 'approved'">{{ $t('Approve') }}</button></span>
                                                <span v-else class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ $t('Approved') }}</span>
                                            </template>
                                            <span v-else class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">{{ $t('Pending') }}</span>
                                        </div>
                                        <div v-for="r in leaveDone" :key="r.w" class="flex items-center justify-between gap-2 py-2">
                                            <div class="min-w-0"><p class="truncate text-xs font-bold">{{ $t(r.w) }}</p><p class="truncate text-[11px] text-slate-500">{{ $t(r.k) }} · {{ $t(r.n) }}</p></div>
                                            <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ $t('Approved') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid gap-3 px-5 pb-6 md:grid-cols-[1.4fr_1fr]">
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-sm font-bold">{{ $t('Headcount, last 6 months') }}</p>
                                    <div class="mt-3 flex h-24 items-end gap-3" dir="ltr">
                                        <div v-for="(b, i) in bars" :key="i" class="flex flex-1 flex-col items-center justify-end" :style="{ height: '100%' }">
                                            <span class="mb-1 text-[11px] font-bold tabular-nums text-slate-600">{{ hc[i] }}</span>
                                            <div class="w-full rounded-t-md bg-[#2BD9A9]" :style="{ height: b * 0.6 + '%' }"></div>
                                        </div>
                                    </div>
                                    <div class="mt-1 flex gap-3 text-[11px] text-slate-400" dir="ltr"><span v-for="m in months" :key="m" class="flex-1 text-center">{{ $t(m) }}</span></div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-sm font-bold">{{ $t('Documents expiring soon') }}</p>
                                    <div class="mt-2">
                                        <div v-for="d in docs.slice(0, 4)" :key="d.who" class="flex items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-0">
                                            <div class="min-w-0"><p class="truncate text-xs font-bold">{{ $t(d.who) }}</p><p class="truncate text-[11px] text-slate-500">{{ $t(d.what) }}</p></div>
                                            <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold', d.tone]">{{ $t(d.when) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. One-line scope -->
            <section>
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-2 px-5 pb-10 lg:px-8">
                    <span v-for="c in chips" :key="c" class="rounded-full bg-white px-4 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-200">{{ $t(c) }}</span>
                </div>
            </section>

            <!-- 3. Why: moments -->
            <section id="days" class="scroll-mt-20 border-y border-slate-200 bg-white">
                <div class="mx-auto max-w-6xl px-5 py-16 lg:px-8 lg:py-20">
                    <div class="mx-auto max-w-2xl text-center" v-reveal>
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $t('HR is mostly small things that pile up.') }}</h2>
                        <p class="mt-3 text-slate-600">{{ $t('Afrad is built to catch them before they do.') }}</p>
                    </div>
                    <ol class="mt-10 grid gap-4 md:grid-cols-3">
                        <li v-for="(m, i) in moments" :key="m.n" v-reveal="i * 60" class="rounded-2xl border border-slate-200 bg-[#F3F6F8] p-6">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0E2A47] text-sm font-bold text-[#2BD9A9]">{{ i + 1 }}</span>
                            <p class="mt-4 text-sm leading-7 text-slate-500">{{ $t(m.pain) }}</p>
                            <p class="mt-3 font-semibold leading-7">{{ $t(m.fix) }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- 4. Roles -->
            <section id="roles" class="scroll-mt-20 py-16 lg:py-24">
                <div class="mx-auto max-w-5xl px-5 lg:px-8">
                    <div class="max-w-2xl" v-reveal>
                        <p class="text-sm font-bold text-[#0B7A60]">{{ $t('Roles') }}</p>
                        <h2 class="mt-3 text-3xl font-bold leading-[1.3] sm:text-4xl">{{ $t('One workspace, four different views') }}</h2>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Everyone sees what their job needs, and nothing more.') }}</p>
                    </div>
                    <div class="mt-10 flex flex-wrap gap-2" role="tablist">
                        <button v-for="r in roles" :key="r.id" type="button" role="tab" :aria-selected="activeRole === r.id" :class="['rounded-full px-5 py-2.5 text-sm font-bold transition-colors', activeRole === r.id ? 'bg-[#0E2A47] text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:text-[#0E2A47]', ring]" @click="activeRole = r.id">{{ $t(r.t) }}</button>
                    </div>
                    <div v-for="r in roles.filter((x) => x.id === activeRole)" :key="r.id" class="mt-8 grid items-center gap-8 rounded-2xl border border-slate-200 bg-white p-7 md:grid-cols-2 md:p-10">
                        <div>
                            <h3 class="text-2xl font-bold leading-[1.4]">{{ $t(r.lead) }}</h3>
                            <ul class="mt-6 space-y-3 text-[15px] leading-7">
                                <li v-for="p in r.pts" :key="p" class="flex gap-3"><span class="mt-0.5 text-[#0B7A60]" aria-hidden="true">✓</span>{{ $t(p) }}</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-[#F3F6F8] p-5">
                            <p class="text-xs font-semibold text-slate-400">{{ $t('Sample data') }}</p>
                            <div v-for="row in r.rows" :key="row[0]" class="flex items-center justify-between border-b border-slate-100 py-3.5 last:border-0">
                                <span class="text-sm text-[#5A6B7D]">{{ $t(row[0]) }}</span>
                                <span class="text-lg font-bold">{{ /days/.test(row[1]) ? $t(row[1]) : row[1] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. Inside: alternating stories -->
            <section id="inside" class="mx-auto max-w-6xl scroll-mt-20 px-5 py-24 lg:px-8 lg:py-32">
                <div class="mx-auto max-w-2xl text-center" v-reveal>
                    <p class="text-sm font-bold text-[#0B7A60]">{{ $t('Inside') }}</p>
                    <h2 class="mt-3 text-3xl font-bold leading-[1.3] sm:text-4xl">{{ $t('A closer look at the work Afrad takes off your plate') }}</h2>
                </div>

                <!-- Documents -->
                <div class="mt-20 grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div v-reveal>
                        <h3 class="text-2xl font-bold leading-[1.4] sm:text-3xl">{{ $t('Never miss a renewal') }}</h3>
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
                            <div v-for="b in [{ n: 'Annual leave', u: 9, t: 21 }, { n: 'Casual leave', u: 2, t: 6 }, { n: 'Sick leave', u: 1, t: 10 }, { n: 'Unpaid leave', u: 3, t: 15 }]" :key="b.n">
                                <div class="flex justify-between text-sm"><span>{{ $t(b.n) }}</span><span class="tabular-nums text-slate-300" dir="ltr">{{ b.u }} / {{ b.t }}</span></div>
                                <div class="mt-2 h-2 rounded-full bg-white/10"><div class="h-2 rounded-full bg-[#2BD9A9]" :style="{ width: (b.u / b.t) * 100 + '%' }"></div></div>
                            </div>
                        </div>
                    </div>
                    <div v-reveal="100">
                        <h3 class="text-2xl font-bold leading-[1.4] sm:text-3xl">{{ $t('Leave and attendance without the chasing') }}</h3>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Employees request, managers approve, and balances follow the approved requests. Late arrivals show up in the daily attendance record.') }}</p>
                    </div>
                </div>

                <!-- Hiring -->
                <div class="mt-24 grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div v-reveal>
                        <h3 class="text-2xl font-bold leading-[1.4] sm:text-3xl">{{ $t('From first application to first day') }}</h3>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Move candidates through the stages, and turn the one you choose into an employee in a single step.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" v-reveal="100">
                        <p class="text-xs font-semibold text-slate-400">{{ $t('Sample data') }}</p>
                        <div class="mt-4 space-y-3">
                            <div v-for="s in stages" :key="s.s" class="flex items-center gap-3">
                                <span class="w-24 shrink-0 text-sm text-[#5A6B7D]">{{ $t(s.s) }}</span>
                                <div class="h-7 flex-1 rounded-md bg-slate-100"><div class="flex h-7 items-center rounded-md bg-[#0E2A47] px-2 text-xs font-bold text-white" :style="{ width: Math.max((s.n / 42) * 100, 9) + '%' }">{{ s.n }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports -->
                <div class="mt-24 grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:order-first" v-reveal>
                        <div class="flex items-center justify-between"><p class="text-sm font-bold">{{ $t('Reports') }}</p><span class="rounded-lg bg-[#F3F6F8] px-3 py-1.5 text-xs font-semibold text-[#0E2A47] ring-1 ring-slate-200">{{ $t('Export CSV') }}</span></div>
                        <p class="mt-1 text-xs text-slate-400">{{ $t('Sample data') }}</p>
                        <div class="mt-5 flex h-32 items-end gap-3" dir="ltr">
                            <div v-for="(b, i) in bars" :key="i" class="flex flex-1 flex-col items-center justify-end" style="height: 100%">
                                <span class="mb-1 text-xs font-bold tabular-nums text-slate-600">{{ hc[i] }}</span>
                                <div class="w-full rounded-t-md bg-[#13395F]" :style="{ height: b * 0.75 + '%' }"></div>
                            </div>
                        </div>
                        <div class="mt-1 flex gap-3 text-xs text-slate-400" dir="ltr"><span v-for="m in months" :key="m" class="flex-1 text-center">{{ $t(m) }}</span></div>
                    </div>
                    <div v-reveal="100">
                        <h3 class="text-2xl font-bold leading-[1.4] sm:text-3xl">{{ $t('Numbers ready when management asks') }}</h3>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Pick a period, read the people numbers for the whole company or for your own team, and export them as CSV.') }}</p>
                    </div>
                </div>
            </section>

            <!-- 5b. AI connection -->
            <section id="ai" class="scroll-mt-20 border-y border-slate-200 bg-white py-16 lg:py-24">
                <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 lg:grid-cols-2 lg:px-8">
                    <div v-reveal>
                        <p class="text-sm font-bold text-[#0B7A60]">{{ $t('AI') }}</p>
                        <h2 class="mt-3 text-3xl font-bold leading-[1.3] sm:text-4xl">{{ $t('Ask Claude about your people numbers.') }}</h2>
                        <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Connect your own Claude account to Afrad, read-only. Ask in plain language and get company totals back, without opening each page.') }}</p>
                        <ul class="mt-6 space-y-3">
                            <li v-for="a in aiAsks" :key="a" class="flex gap-3 text-[15px] leading-7 text-slate-700"><span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[#2BD9A9]" aria-hidden="true"></span>{{ $t(a) }}</li>
                        </ul>
                        <p class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-950/80">{{ $t('This is a private connector you add by hand in Claude, not an assistant inside Afrad. It is new, and we are still trying it with real accounts. Answers are shown to Claude and handled under its own terms.') }}</p>
                    </div>
                    <div v-reveal="80">
                        <div class="rounded-2xl border border-slate-200 bg-[#F3F6F8] p-5">
                            <p class="text-xs font-semibold text-slate-400">{{ $t('Illustration, sample data') }}</p>
                            <div class="mt-3 space-y-3 text-sm">
                                <p class="ms-auto max-w-[85%] rounded-2xl rounded-ee-sm bg-[#0E2A47] px-4 py-3 text-white">{{ $t('How many people are late today?') }}</p>
                                <p class="max-w-[90%] rounded-2xl rounded-es-sm border border-slate-200 bg-white px-4 py-3 text-slate-700">{{ $t('4 of 38 expected people checked in late today, and 2 have no attendance record yet.') }}</p>
                            </div>
                        </div>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <div v-for="(t, i) in aiTrust" :key="t[0]" v-reveal="i * 60" class="border-t-2 border-[#2BD9A9] pt-4">
                                <p class="font-bold">{{ $t(t[0]) }}</p>
                                <p class="mt-1 text-sm leading-7 text-slate-600">{{ $t(t[1]) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 6. Language band -->
            <section class="bg-[#0E2A47] py-16 text-white lg:py-20">
                <div class="mx-auto grid max-w-5xl items-center gap-10 px-5 md:grid-cols-2 lg:px-8">
                    <div v-reveal>
                        <h2 class="text-3xl font-bold leading-[1.35] sm:text-4xl">{{ $t('Arabic and English, switched in one tap.') }}</h2>
                        <p class="mt-4 text-lg leading-8 text-slate-300">{{ $t('The whole interface flips, including right-to-left layout. Names and notes you type are never translated or changed.') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4" v-reveal="100">
                        <div class="rounded-2xl bg-[#2BD9A9] p-7 text-center text-[#0A2038]" dir="rtl"><p class="text-4xl font-bold">أفراد</p><p class="mt-2 text-sm text-[#0A2038]/80">العربية</p></div>
                        <div class="rounded-2xl bg-white p-7 text-center text-[#0E2A47]" dir="ltr"><p class="text-4xl font-bold">Afrad</p><p class="mt-2 text-sm text-[#5A6B7D]">English</p></div>
                    </div>
                </div>
            </section>

            <!-- 7. Also included -->
            <section class="mx-auto max-w-5xl px-5 py-24 lg:px-8">
                <h2 class="text-2xl font-bold leading-[1.4] sm:text-3xl" v-reveal>{{ $t('Also in the workspace') }}</h2>
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
                    <span class="mb-4 inline-flex shrink-0 rounded-full bg-amber-200 px-3 py-1 text-xs font-bold text-amber-900 md:mb-0">{{ $t('Payroll') }}</span>
                    <div>
                        <h2 class="text-xl font-bold leading-[1.4]">{{ $t('Payroll runs on your company rules.') }}</h2>
                        <p class="mt-2 text-[15px] leading-7 text-amber-950/80">{{ $t('You enter your rates and rules in company settings, and Afrad calculates the monthly run from them. Afrad does not pay salaries or file anything, and your accountant should review a run before anyone relies on it.') }}</p>
                    </div>
                </div>
            </section>

            <!-- 9. Security -->
            <section class="border-y border-slate-200 bg-white py-16 lg:py-20">
                <div class="mx-auto max-w-5xl px-5 lg:px-8">
                    <h2 class="max-w-2xl text-3xl font-bold leading-[1.35] sm:text-4xl" v-reveal>{{ $t('Your people data stays yours.') }}</h2>
                    <div class="mt-10 grid gap-8 md:grid-cols-3">
                        <div v-for="(s, i) in [
                            ['Isolated workspaces', 'Each company works in its own workspace, kept separate from every other company.'],
                            ['Role-based access', 'Permissions follow the role, and sensitive employee data is limited to authorized users.'],
                            ['Limits that protect', 'Plan limits only stop new additions. Existing records are never hidden or deleted.'],
                        ]" :key="s[0]" v-reveal="i * 80" class="border-t-2 border-[#2BD9A9] pt-5">
                            <p class="text-lg font-bold">{{ $t(s[0]) }}</p>
                            <p class="mt-2 text-[15px] leading-7 text-slate-600">{{ $t(s[1]) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 10. Plans -->
            <section id="plans" class="mx-auto max-w-5xl scroll-mt-20 px-5 py-24 lg:px-8 lg:py-28">
                <div class="max-w-2xl" v-reveal>
                    <h2 class="text-3xl font-bold leading-[1.3] sm:text-4xl">{{ $t('Plans that follow your size') }}</h2>
                    <p class="mt-4 text-lg leading-8 text-[#5A6B7D]">{{ $t('Plans set limits on employees and user accounts. The Afrad team assigns them, and there is no online payment yet.') }}</p>
                </div>
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <div v-for="(p, i) in plans" :key="p.name" v-reveal="i * 80" :class="['rounded-2xl border p-7', p.hot ? 'border-[#2BD9A9] bg-white ring-2 ring-[#2BD9A9]' : 'border-slate-200 bg-white']">
                        <p class="text-lg font-bold">{{ $t(p.name) }}</p>
                        <p class="mt-4 text-[15px]">{{ $t(p.emp) }}</p>
                        <p class="mt-1 text-[15px]">{{ $t(p.acc) }}</p>
                    </div>
                </div>
            </section>

            <!-- 11. FAQ -->
            <section id="faq" class="scroll-mt-20 border-t border-slate-200 bg-white py-16 lg:py-24">
                <div class="mx-auto max-w-3xl px-5 lg:px-8">
                    <h2 class="text-3xl font-bold leading-[1.3] sm:text-4xl" v-reveal>{{ $t('Questions, answered') }}</h2>
                    <div class="mt-10 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-[#F3F6F8]">
                        <details v-for="f in faqs" :key="f.q" class="group px-6 py-5">
                            <summary :class="['flex cursor-pointer list-none items-center justify-between gap-4 rounded font-bold', ring]">
                                {{ $t(f.q) }}
                                <span class="text-xl text-[#0B7A60] transition-transform group-open:rotate-45" aria-hidden="true">+</span>
                            </summary>
                            <p class="mt-3 text-[15px] leading-7 text-[#5A6B7D]">{{ $t(f.a) }}</p>
                        </details>
                    </div>
                </div>
            </section>

            <!-- 12. CTA -->
            <section class="px-5 py-16 lg:px-8 lg:py-20">
                <div class="mx-auto max-w-4xl rounded-3xl bg-[#0E2A47] px-6 py-14 text-center text-white" v-reveal>
                    <AfradIcon class="mx-auto h-12 w-12" aria-hidden="true" />
                    <h2 class="mt-5 text-3xl font-bold tracking-tight sm:text-4xl">{{ $t('Give your people one home.') }}</h2>
                    <p class="mx-auto mt-3 max-w-xl text-lg leading-8 text-slate-300">{{ $t('Create your workspace and start with your first employees today.') }}</p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <Link v-if="canRegister" :href="route('register')" :class="btnMint">{{ $t('Create your workspace') }}</Link>
                        <Link :href="route('login')" :class="btnGhostDark">{{ $t('Sign in') }}</Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white text-slate-500">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-10 text-sm sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div class="flex items-center gap-2.5 text-[#0E2A47]"><AfradIcon class="h-8 w-8" aria-hidden="true" /><span class="font-bold">{{ $t('Afrad') }}</span></div>
                <nav class="flex flex-wrap gap-x-6 gap-y-2" :aria-label="$t('Footer')">
                    <a href="#days" class="hover:text-[#0E2A47]">{{ $t('Why Afrad') }}</a>
                    <a href="#plans" class="hover:text-[#0E2A47]">{{ $t('Plans') }}</a>
                    <a href="#faq" class="hover:text-[#0E2A47]">{{ $t('FAQ') }}</a>
                    <Link :href="route('login')" class="hover:text-[#0E2A47]">{{ $t('Sign in') }}</Link>
                </nav>
                <p>© {{ new Date().getFullYear() }} {{ $t('Afrad') }}. {{ $t('All rights reserved.') }}</p>
            </div>
            <p class="border-t border-slate-100 px-5 py-4 text-center text-xs text-slate-500">{{ $t('Built by') }} <a href="https://wa.me/201098971853" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#0E2A47] hover:underline">Reviv Solutions</a></p>
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
    font-family: 'IBM Plex Sans Arabic', 'IBM Plex Sans', 'Segoe UI', Tahoma, system-ui, sans-serif;
    font-feature-settings: 'tnum' 1;
}
</style>
