<script setup>
import AfradIcon from '@/Components/AfradIcon.vue';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canRegister: {
        type: Boolean,
        default: true,
    },
});

const icons = {
    users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
    clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20 M12 6v6l4 2',
    calendar: 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
    wallet: 'M2 6h20v12H2z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6 M6 12h.01 M18 12h.01',
    chart: 'M3 3v18h18 M7 15l4-4 3 3 5-6',
    sparkle: 'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z',
    check: 'M20 6 9 17l-5-5',
    shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
};

const features = [
    {
        icon: 'users',
        title: 'Employee management',
        description: 'Profiles, documents, and org structure in one secure place, instead of scattered folders and sheets.',
    },
    {
        icon: 'clock',
        title: 'Attendance',
        description: 'Track working hours, late arrivals, and overtime with company attendance policies.',
    },
    {
        icon: 'calendar',
        title: 'Leave management',
        description: 'Employees request, managers approve, and balances update automatically.',
    },
    {
        icon: 'wallet',
        title: 'Payroll',
        description: 'Keep payroll workflows organized, with compensation data visible only to authorized people.',
    },
    {
        icon: 'chart',
        title: 'Reports',
        description: 'Headcount, attendance, and leave summaries your management team can read at a glance.',
    },
    {
        icon: 'sparkle',
        title: 'AI assistant',
        description: 'Planned, not available yet: ask questions about your HR data in plain language.',
    },
];

const steps = [
    {
        title: 'Create your workspace',
        description: 'Sign up and get a dedicated space for your company in minutes.',
    },
    {
        title: 'Add your people and policies',
        description: 'Set up departments and leave types, then invite your team.',
    },
    {
        title: 'Run HR from one place',
        description: 'Handle attendance, leave, and payroll without switching between tools.',
    },
];

const securityPoints = [
    'Every company works in its own isolated workspace',
    'Role-based permissions for owners, HR, and managers',
    'Sensitive employee data limited to authorized users',
];

const faqs = [
    {
        q: 'Is my company data separated from other companies?',
        a: 'Yes. Afrad is built as a multi-tenant platform where each organization has its own workspace, and its data is kept isolated from every other organization.',
    },
    {
        q: 'Which HR tasks can I manage in Afrad?',
        a: 'Employee records, attendance, leave requests and balances, payroll workflows, and reporting, all inside one workspace.',
    },
    {
        q: 'How do I get started?',
        a: 'Create your workspace, add your departments and employees, and invite your team. You can start with one module and add the others as you need them.',
    },
];

const employees = [
    { name: 'Ahmed Hassan', dept: 'Engineering', status: 'Present', time: '08:52' },
    { name: 'Mona Adel', dept: 'Finance', status: 'Present', time: '09:04' },
    { name: 'Nour Khaled', dept: 'HR', status: 'Late', time: '09:41' },
    { name: 'Karim Samir', dept: 'Sales', status: 'On leave', time: '—' },
];

const statusStyle = {
    Present: 'bg-emerald-50 text-emerald-700',
    Late: 'bg-amber-50 text-amber-700',
    'On leave': 'bg-slate-100 text-slate-600',
};

const leaveState = ref('pending');

const assistants = [
    { id: 'claude', name: 'Claude' },
    { id: 'chatgpt', name: 'ChatGPT' },
];
const activeAssistant = ref('claude');
const aiPoints = [
    'Planned: not available yet. No Claude or ChatGPT connection exists today',
    'Planned: answers would come only from your own workspace data',
    'Planned: would follow the same roles and permissions as Afrad',
];
const aiPanel = ref(null);
const aiVisible = ref(false);

const scrolled = ref(false);
const progress = ref(0);
function onScroll() {
    const y = window.scrollY;
    scrolled.value = y > 8;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    progress.value = max > 0 ? Math.min(y / max, 1) : 0;
}

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Reveal elements as they scroll into view. Usage: v-reveal or v-reveal="delayMs"
const vReveal = {
    mounted(el, binding) {
        el.classList.add('reveal');
        if (binding.value) el.style.setProperty('--d', `${binding.value}ms`);
        if (reduceMotion() || !('IntersectionObserver' in window)) {
            el.classList.add('is-visible');
            return;
        }
        const io = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    el.classList.add('is-visible');
                    io.disconnect();
                }
            },
            { threshold: 0.15 },
        );
        io.observe(el);
    },
};

let aiObserver;
onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    if (reduceMotion() || !('IntersectionObserver' in window)) {
        aiVisible.value = true;
    } else {
        aiObserver = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    aiVisible.value = true;
                    aiObserver.disconnect();
                }
            },
            { threshold: 0.35 },
        );
        if (aiPanel.value) aiObserver.observe(aiPanel.value);
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    if (aiObserver) aiObserver.disconnect();
});

const focusRing =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F4FD8]';
const btnPrimary = `inline-flex items-center justify-center rounded-lg bg-[#1F4FD8] px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1A43B8] hover:shadow-lg hover:shadow-[#1F4FD8]/30 active:translate-y-0 ${focusRing}`;
const btnSecondary = `inline-flex items-center justify-center rounded-lg border border-[#C9D2E0] bg-white px-6 py-3.5 text-sm font-semibold text-[#0F1B33] transition-all duration-200 hover:-translate-y-0.5 hover:border-[#1F4FD8]/50 hover:bg-[#EEF2F8] active:translate-y-0 ${focusRing}`;
</script>

<template>
    <Head>
        <title>{{ $t('Afrad | HR software for growing companies') }}</title>
        <meta
            name="description"
            content="Manage employees, attendance, leave, and payroll in one workspace with Afrad."
        />
        <meta name="theme-color" content="#F6F8FB" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="relative isolate min-h-screen bg-[#F6F8FB] font-['Manrope',ui-sans-serif,system-ui,sans-serif] text-[#0F1B33] antialiased selection:bg-[#1F4FD8]/15">
        <!-- Ambient background -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
            <div class="drift absolute -left-32 -top-24 h-[420px] w-[420px] rounded-full bg-[#1F4FD8]/10 blur-[110px]"></div>
            <div class="drift-b absolute -right-32 top-[30%] h-[380px] w-[380px] rounded-full bg-[#0E9F8E]/10 blur-[110px]"></div>
        </div>

        <!-- Header -->
        <header :class="['sticky top-0 z-30 border-b border-[#DDE3EC] bg-[#F6F8FB]/80 backdrop-blur-md transition-shadow duration-300', scrolled ? 'shadow-[0_8px_24px_-16px_rgba(15,27,51,0.35)]' : '']">
            <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6 lg:px-8" :aria-label="$t('Main')">
                <a href="#" :class="['flex items-center gap-2.5 rounded-md', focusRing]">
                    <AfradIcon class="h-8 w-8" aria-hidden="true" />
                    <span class="text-lg font-extrabold tracking-tight">{{ $t('Afrad') }}</span>
                </a>

                <div class="hidden items-center gap-8 text-sm font-medium text-[#4A5875] md:flex">
                    <a href="#features" :class="['rounded transition-colors hover:text-[#0F1B33]', focusRing]">{{ $t('Features') }}</a>
                    <a href="#ai" :class="['rounded transition-colors hover:text-[#0F1B33]', focusRing]">{{ $t('AI') }}</a>
                    <a href="#how-it-works" :class="['rounded transition-colors hover:text-[#0F1B33]', focusRing]">{{ $t('How it works') }}</a>
                    <a href="#security" :class="['rounded transition-colors hover:text-[#0F1B33]', focusRing]">{{ $t('Security') }}</a>
                    <a href="#faq" :class="['rounded transition-colors hover:text-[#0F1B33]', focusRing]">{{ $t('FAQ') }}</a>
                </div>

                <div class="flex items-center gap-2">
                    <a
                        href="/platform/login"
                        :class="['hidden rounded-lg px-4 py-2 text-sm font-semibold text-[#0F1B33] transition-colors hover:bg-[#E8EDF5] sm:block', focusRing]"
                    >{{ $t('Sign in') }}</a>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        :class="['inline-flex rounded-lg bg-[#1F4FD8] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#1A43B8]', focusRing]"
                    >{{ $t('Get started') }}</Link>
                </div>
            </nav>
            <div class="h-0.5 origin-left bg-[#1F4FD8]" :style="{ transform: `scaleX(${progress})` }" aria-hidden="true"></div>
        </header>

        <main>
            <!-- Hero -->
            <section class="mx-auto grid max-w-6xl items-center gap-14 px-6 pb-24 pt-16 lg:grid-cols-[1fr_1.05fr] lg:gap-16 lg:px-8 lg:pb-32 lg:pt-24">
                <div class="min-w-0">
                    <h1 class="max-w-xl text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl lg:text-[56px]">
                        <span class="hero-in block" style="--d: 0ms">{{ $t('HR for your whole company,') }}</span>
                        <span class="hero-in block" style="--d: 120ms">{{ $t('in one place.') }}</span>
                    </h1>
                    <p class="hero-in mt-6 max-w-lg text-lg leading-8 text-[#4A5875]" style="--d: 240ms">{{ $t('Employee records, attendance, leave, and payroll in a single workspace, so your HR team spends less time chasing spreadsheets.') }}</p>

                    <div class="hero-in mt-9 flex flex-col gap-3 sm:flex-row" style="--d: 360ms">
                        <Link v-if="canRegister" :href="route('register')" :class="btnPrimary">{{ $t('Create your workspace') }}</Link>
                        <a href="#features" :class="btnSecondary">{{ $t('See what\'s included') }}</a>
                    </div>

                    <p class="hero-in mt-6 flex items-center gap-2 text-sm text-[#4A5875]" style="--d: 480ms">
                        <svg class="h-4 w-4 text-[#0E9F8E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.check" /></svg>{{ $t('Each company gets its own isolated workspace.') }}</p>
                </div>

                <!-- Product preview -->
                <div class="hero-in relative min-w-0" style="--d: 300ms">
                    <div class="overflow-hidden rounded-xl border border-[#DDE3EC] bg-white shadow-[0_20px_50px_-20px_rgba(15,27,51,0.25)]">
                        <div class="flex items-center justify-between border-b border-[#E6EBF2] px-5 py-3.5">
                            <span class="flex items-center gap-2 text-sm font-bold">
                                <span class="relative flex h-2 w-2" aria-hidden="true">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75 motion-reduce:animate-none"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                </span>{{ $t('Today\'s attendance') }}</span>
                            <span class="text-xs text-[#6B7894]">{{ $t('Sample data') }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[420px] text-start text-sm">
                                <caption class="sr-only">{{ $t('Sample attendance list') }}</caption>
                                <thead>
                                    <tr class="text-xs text-[#6B7894]">
                                        <th scope="col" class="px-5 py-3 font-semibold">{{ $t('Employee') }}</th>
                                        <th scope="col" class="px-3 py-3 font-semibold">{{ $t('Department') }}</th>
                                        <th scope="col" class="px-3 py-3 font-semibold">{{ $t('Status') }}</th>
                                        <th scope="col" class="px-5 py-3 text-end font-semibold">{{ $t('Check-in') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#EEF1F6]">
                                    <tr v-for="(e, i) in employees" :key="e.name" class="hero-in transition-colors hover:bg-[#F6F8FB]" :style="{ '--d': `${700 + i * 120}ms` }">
                                        <td class="px-5 py-3 font-semibold">{{ $t(e.name) }}</td>
                                        <td class="px-3 py-3 text-[#4A5875]">{{ $t(e.dept) }}</td>
                                        <td class="px-3 py-3">
                                            <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusStyle[e.status]]">{{ $t(e.status) }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-right tabular-nums text-[#4A5875]">{{ e.time }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Interactive leave request -->
                    <div class="float-slow mt-4 rounded-xl border border-[#DDE3EC] bg-white p-5 shadow-[0_12px_30px_-18px_rgba(15,27,51,0.25)] sm:absolute sm:-bottom-10 sm:-left-8 sm:mt-0 sm:w-[300px]">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold">{{ $t('Leave request') }}</p>
                                <p class="mt-1 text-sm text-[#4A5875]">{{ $t('Mona Adel · Annual leave') }}</p>
                                <p class="text-sm text-[#4A5875]">{{ $t('3 days, Nov 24 to 26') }}</p>
                            </div>
                        </div>

                        <div v-if="leaveState === 'pending'" class="mt-4 flex gap-2">
                            <button
                                type="button"
                                :class="['flex-1 rounded-lg bg-[#1F4FD8] px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#1A43B8]', focusRing]"
                                @click="leaveState = 'approved'"
                            >{{ $t('Approve') }}</button>
                            <button
                                type="button"
                                :class="['flex-1 rounded-lg border border-[#C9D2E0] px-3 py-2 text-sm font-semibold transition-colors hover:bg-[#EEF2F8]', focusRing]"
                                @click="leaveState = 'declined'"
                            >{{ $t('Decline') }}</button>
                        </div>
                        <div v-else class="mt-4 flex items-center justify-between" role="status">
                            <span
                                :class="[
                                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                                    leaveState === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600',
                                ]"
                            >
                                {{ leaveState === 'approved' ? $t('Approved') : $t('Declined') }}
                            </span>
                            <button
                                type="button"
                                :class="['rounded text-sm font-semibold text-[#1F4FD8] hover:underline', focusRing]"
                                @click="leaveState = 'pending'"
                            >{{ $t('Undo') }}</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section id="features" class="border-y border-[#DDE3EC] bg-white">
                <div class="mx-auto grid max-w-6xl gap-12 px-6 py-24 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16 lg:px-8">
                    <div v-reveal class="lg:sticky lg:top-28 lg:self-start">
                        <h2 class="max-w-sm text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">{{ $t('Everything your HR team does every week.') }}</h2>
                        <p class="mt-5 max-w-sm leading-7 text-[#4A5875]">{{ $t('Start with the module you need first and bring the rest in as your company grows.') }}</p>
                    </div>

                    <ul class="divide-y divide-[#E6EBF2] border-y border-[#E6EBF2]">
                        <li v-for="(f, i) in features" :key="f.title" v-reveal="i * 70"><div class="group flex gap-5 py-6 transition-transform duration-300 hover:translate-x-1.5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[#EAF0FD] text-[#1F4FD8] transition-all duration-300 group-hover:scale-110 group-hover:bg-[#1F4FD8] group-hover:text-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons[f.icon]" /></svg>
                            </span>
                            <div>
                                <h3 class="text-base font-bold">{{ $t(f.title) }}</h3>
                                <p class="mt-1.5 max-w-lg text-[15px] leading-7 text-[#4A5875]">{{ $t(f.description) }}</p>
                            </div>
                        </div></li>
                    </ul>
                </div>
            </section>

            <!-- AI integrations -->
            <section id="ai" class="mx-auto max-w-6xl px-6 py-24 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
                    <div v-reveal>
                        <h2 class="max-w-md text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">{{ $t('An AI assistant for your HR data is planned.') }}</h2>
                        <p class="mt-5 max-w-md leading-7 text-[#4A5875]">{{ $t('This is a planned feature and is not available today. The conversation shown is only an illustration of what it could look like.') }}</p>
                        <ul class="mt-8 space-y-4">
                            <li v-for="point in aiPoints" :key="point" class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#EAF0FD] text-[#1F4FD8]">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.check" /></svg>
                                </span>
                                <span class="leading-7 text-[#4A5875]">{{ $t(point) }}</span>
                            </li>
                        </ul>
                    </div>

                    <div ref="aiPanel" v-reveal="150" class="rounded-2xl bg-[#EAF0FD] p-4 sm:p-8">
                        <div class="overflow-hidden rounded-xl border border-[#DDE3EC] bg-white shadow-[0_20px_50px_-20px_rgba(15,27,51,0.25)]">
                            <div class="flex items-center justify-between gap-3 border-b border-[#E6EBF2] px-4 py-3">
                                <div role="tablist" :aria-label="$t('AI assistant')" class="inline-flex rounded-lg bg-[#F1F4F9] p-1">
                                    <button
                                        v-for="a in assistants"
                                        :key="a.id"
                                        type="button"
                                        role="tab"
                                        :aria-selected="activeAssistant === a.id"
                                        :class="[
                                            'rounded-md px-3.5 py-1.5 text-sm font-semibold transition-all duration-200',
                                            focusRing,
                                            activeAssistant === a.id ? 'bg-white text-[#0F1B33] shadow-sm' : 'text-[#6B7894] hover:text-[#0F1B33]',
                                        ]"
                                        @click="activeAssistant = a.id"
                                    >
                                        {{ a.name }}
                                    </button>
                                </div>
                                <span class="text-xs text-[#6B7894]">{{ $t('Illustration of the planned feature, not a working product') }}</span>
                            </div>

                            <div class="min-h-[340px] p-5 sm:p-6" aria-live="polite">
                                <div v-if="aiVisible" :key="activeAssistant" class="space-y-4">
                                    <div class="flex justify-end">
                                        <div class="hero-in max-w-[85%] rounded-2xl rounded-br-md bg-[#0F1B33] px-4 py-3 text-sm leading-6 text-white" style="--d: 0ms">{{ $t('Who is on leave next week, and who has more than 15 days of annual leave left?') }}</div>
                                    </div>

                                    <div class="hero-in inline-flex items-center gap-2 rounded-full border border-[#DDE3EC] bg-[#F6F8FB] px-3 py-1.5 text-xs font-semibold text-[#4A5875]" style="--d: 600ms">
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#0E9F8E]"></span>{{ $t('Afrad: Leave balances') }}</div>

                                    <div class="typing flex gap-1.5 px-1" aria-hidden="true">
                                        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                                    </div>

                                    <div class="hero-in max-w-[92%] rounded-2xl rounded-bl-md bg-[#F1F4F9] px-4 py-3 text-sm leading-6" style="--d: 1700ms">
                                        <p class="text-xs font-bold text-[#6B7894]">{{ assistants.find((a) => a.id === activeAssistant).name }}</p>
                                        <p class="mt-1.5">{{ $t('Two people are on leave next week:') }}</p>
                                        <ul class="mt-1.5 list-disc space-y-0.5 pl-5">
                                            <li>{{ $t('Karim Samir (Sales), all week') }}</li>
                                            <li>{{ $t('Mona Adel (Finance), Nov 24 to 26') }}</li>
                                        </ul>
                                        <p class="mt-2.5">{{ $t('Three employees have more than 15 days of annual leave left: Ahmed Hassan, Nour Khaled, and Youssef Ali.') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- How it works -->
            <section id="how-it-works" class="mx-auto max-w-6xl px-6 py-24 lg:px-8">
                <h2 v-reveal class="max-w-xl text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">{{ $t('Up and running in three steps.') }}</h2>

                <ol class="mt-14 grid gap-10 md:grid-cols-3 md:gap-8">
                    <li v-for="(s, i) in steps" :key="s.title" v-reveal="i * 120"><div class="group border-t-2 border-[#0F1B33] pt-6 transition-colors duration-300 hover:border-[#1F4FD8]">
                        <span class="text-sm font-bold text-[#1F4FD8]">{{ $t('Step {n}', { n: i + 1 }) }}</span>
                        <h3 class="mt-3 text-lg font-bold">{{ $t(s.title) }}</h3>
                        <p class="mt-2 text-[15px] leading-7 text-[#4A5875]">{{ $t(s.description) }}</p>
                    </div></li>
                </ol>
            </section>

            <!-- Security -->
            <section id="security" class="mx-auto max-w-6xl px-6 pb-24 lg:px-8">
                <div v-reveal class="grid items-center gap-10 rounded-2xl bg-[#0F1B33] p-8 text-white sm:p-12 lg:grid-cols-2 lg:gap-16 lg:p-14">
                    <div>
                        <h2 class="text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">{{ $t('Your employee data stays yours.') }}</h2>
                        <p class="mt-5 max-w-md leading-7 text-[#B8C3D9]">{{ $t('Afrad is built around a multi-tenant architecture that keeps every company\'s workspace and data separate from everyone else\'s.') }}</p>
                    </div>

                    <ul class="space-y-4">
                        <li v-for="(p, i) in securityPoints" :key="p" v-reveal="250 + i * 120" class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#1F4FD8]">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="icons.check" /></svg>
                            </span>
                            <span class="leading-7 text-[#E3E9F5]">{{ $t(p) }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- FAQ -->
            <section id="faq" class="mx-auto max-w-3xl px-6 pb-24 lg:px-8">
                <h2 v-reveal class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $t('Common questions') }}</h2>

                <div class="mt-10 divide-y divide-[#DDE3EC] border-y border-[#DDE3EC]">
                    <details v-for="(item, i) in faqs" :key="item.q" v-reveal="i * 80" class="group py-5">
                        <summary :class="['flex cursor-pointer list-none items-center justify-between gap-6 rounded text-base font-bold [&::-webkit-details-marker]:hidden', focusRing]">
                            {{ $t(item.q) }}
                            <span class="text-xl leading-none text-[#6B7894] transition-transform group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <p class="mt-3 max-w-xl leading-7 text-[#4A5875]">{{ $t(item.a) }}</p>
                    </details>
                </div>
            </section>

            <!-- Final CTA -->
            <section class="border-t border-[#DDE3EC] bg-white">
                <div v-reveal class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-8 px-6 py-16 sm:flex-row sm:items-center lg:px-8">
                    <div>
                        <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $t('Ready to move HR out of spreadsheets?') }}</h2>
                        <p class="mt-2 text-[#4A5875]">{{ $t('Create a workspace for your company and invite your team.') }}</p>
                    </div>
                    <Link v-if="canRegister" :href="route('register')" :class="[btnPrimary, 'shrink-0']">{{ $t('Create your workspace') }}</Link>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-[#DDE3EC]">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 text-sm text-[#6B7894] sm:flex-row lg:px-8">
                <p>© {{ new Date().getFullYear() }} {{ $t('Afrad') }}. {{ $t('All rights reserved.') }}</p>
                <a href="/platform/login" :class="['rounded transition-colors hover:text-[#0F1B33]', focusRing]">{{ $t('Platform administration') }}</a>
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
    details[open] > p {
        animation: none;
    }
}
</style>