<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') — Happy</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

<script>
tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                sans:  ['Inter', 'sans-serif'],
                mono:  ['JetBrains Mono', 'monospace'],
                serif: ['Playfair Display', 'serif'],
            },
            colors: {
                noir: '#080808',
            },
            animation: {
                'fade-in':  'fadeIn 0.3s ease-out',
                'slide-up': 'slideUp 0.35s cubic-bezier(0.2,1,0.3,1)',
            },
            keyframes: {
                fadeIn:  { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                slideUp: { '0%': { transform: 'translateY(16px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
            },
        }
    }
}
</script>

<style>
/* Scanline texture — the noir signature */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.08) 50%);
    background-size: 100% 4px;
    pointer-events: none;
    z-index: 999;
}

/* Dot grid */
body::after {
    content: '';
    position: fixed;
    inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 24px 24px;
    pointer-events: none;
    z-index: 0;
}

::-webkit-scrollbar { width: 3px; height: 3px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

.glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); }
.glass-deep { background: rgba(255,255,255,0.02); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
</style>
</head>

<body class="bg-noir text-white font-sans min-h-screen overflow-x-hidden selection:bg-white selection:text-black">

{{-- ── Desktop Sidebar ── --}}
<aside class="hidden md:flex fixed left-0 top-0 bottom-0 w-60 flex-col border-r border-white/[0.06] glass-deep z-50">

    <div class="p-6 border-b border-white/[0.06]">
        <div class="flex items-center gap-3 group cursor-pointer">
            <div class="w-9 h-9 bg-white rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-110">
                <i class="fa-solid fa-chart-line text-black text-sm"></i>
            </div>
            <div>
                <h1 class="font-serif font-bold text-lg tracking-tight leading-none">Happy</h1>
                <p class="text-[10px] font-mono text-white/30 tracking-widest uppercase mt-0.5">CRT · V25</p>
            </div>
        </div>
    </div>

    <nav class="flex flex-col gap-1 p-4 flex-1">
        <p class="text-[9px] font-mono text-white/20 tracking-widest uppercase px-2 py-2">Navigation</p>
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all
                  {{ request()->routeIs('dashboard') ? 'bg-white text-black' : 'text-white/50 hover:text-white hover:bg-white/[0.06]' }}">
            <i class="fa-solid fa-gauge-high w-4 text-center"></i> Dashboard
        </a>
        <a href="{{ route('trades') }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all
                  {{ request()->routeIs('trades') ? 'bg-white text-black' : 'text-white/50 hover:text-white hover:bg-white/[0.06]' }}">
            <i class="fa-solid fa-wave-square w-4 text-center"></i> Trade Logs
        </a>
        <a href="{{ route('parameters') }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all
                  {{ request()->routeIs('parameters') ? 'bg-white text-black' : 'text-white/50 hover:text-white hover:bg-white/[0.06]' }}">
            <i class="fa-solid fa-sliders w-4 text-center"></i> Parameters
        </a>
        <a href="{{ route('settings') }}"
        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all
                {{ request()->routeIs('settings') ? 'bg-white text-black' : 'text-white/50 hover:text-white hover:bg-white/[0.06]' }}">
            <i class="fa-solid fa-gear w-4 text-center"></i> Settings
        </a>
    </nav>

    <div class="p-4 border-t border-white/[0.06]">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-white/10 border border-white/20 flex items-center justify-center text-xs font-bold shrink-0">
                {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium truncate">{{ Auth::user()->name }}</p>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ Auth::user()->is_active ? 'bg-emerald-400 animate-pulse' : 'bg-white/20' }}" id="status-dot"></span>
                    <span class="text-[10px] font-mono text-white/30 tracking-widest uppercase" id="status-label">{{ Auth::user()->is_active ? 'LIVE' : 'PAUSED' }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                @csrf
                <button type="submit" class="text-white/20 hover:text-white/60 transition-colors text-xs">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- ── Main Content ── --}}
<div class="relative z-10 md:pl-60">

    {{-- Topbar --}}
    <header class="sticky top-0 z-40 flex items-center justify-between px-5 md:px-8 py-4 border-b border-white/[0.06] glass">
        <div>
            <h2 class="font-serif text-lg font-bold leading-none">@yield('title', 'Dashboard')</h2>
            <p class="text-[10px] font-mono text-white/30 tracking-widest uppercase mt-1">@yield('subtitle', 'Volatility 25 · 4H CRT')</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-[11px] font-mono text-white/25 hidden sm:block" id="clock"></span>
            <form method="POST" action="{{ route('logout') }}" class="hidden md:block">
                @csrf
                <button type="submit" class="flex items-center gap-2 text-xs text-white/40 hover:text-white/80 transition-colors glass border border-white/[0.08] px-3 py-1.5 rounded-lg">
                    <i class="fa-solid fa-right-from-bracket"></i> Sign out
                </button>
            </form>
        </div>
    </header>

    {{-- Flash --}}
    @if(session('success'))
    <div class="mx-5 md:mx-8 mt-4 px-4 py-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-sm font-mono animate-fade-in flex items-center gap-2">
        <i class="fa-solid fa-check"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Page Content --}}
    <main class="p-5 md:p-8 pb-28 md:pb-8">
        @yield('content')
    </main>
</div>

{{-- ── Mobile Bottom Nav ── --}}
<nav class="md:hidden fixed bottom-0 left-0 right-0 h-16 glass border-t border-white/[0.08] z-50 flex justify-around items-center" style="padding-bottom: env(safe-area-inset-bottom)">

    <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-white/30' }} transition-colors">
        <i class="fa-solid fa-gauge-high text-lg"></i>
        <span class="text-[9px] font-mono tracking-wider uppercase">Home</span>
    </a>

    <a href="{{ route('trades') }}" class="flex flex-col items-center gap-1 {{ request()->routeIs('trades') ? 'text-white' : 'text-white/30' }} transition-colors">
        <i class="fa-solid fa-wave-square text-lg"></i>
        <span class="text-[9px] font-mono tracking-wider uppercase">Trades</span>
    </a>

    {{-- Bot toggle — centre raised button like Theresa --}}
    <button id="bot-toggle-mobile" onclick="toggleBot()"
        class="w-14 h-14 rounded-full -mt-8 flex items-center justify-center shadow-2xl border-4 border-noir transition-all active:scale-90
               {{ Auth::user()->is_active ? 'bg-emerald-500' : 'bg-white/10 border-white/20' }}">
        <i class="fa-solid fa-robot text-lg {{ Auth::user()->is_active ? 'text-white' : 'text-white/40' }}" id="bot-icon-mobile"></i>
    </button>

    <a href="{{ route('parameters') }}" class="flex flex-col items-center gap-1 {{ request()->routeIs('parameters') ? 'text-white' : 'text-white/30' }} transition-colors">
        <i class="fa-solid fa-sliders text-lg"></i>
        <span class="text-[9px] font-mono tracking-wider uppercase">Params</span>
    </a>

    <a href="{{ route('settings') }}" class="flex flex-col items-center gap-1 {{ request()->routeIs('settings') ? 'text-white' : 'text-white/30' }} transition-colors">
        <i class="fa-solid fa-gear text-lg"></i>
        <span class="text-[9px] font-mono tracking-wider uppercase">Settings</span>
    </a>

</nav>

<script>
// Global routes and CSRF
window.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
window.routes = {
    toggle:    '{{ route("trading.toggle") }}',
    stats:     '{{ route("data.stats") }}',
    snapshots: '{{ route("data.snapshots") }}',
    trades:    '{{ route("data.trades") }}',
};
window.botActive = {{ Auth::user()->is_active ? 'true' : 'false' }};

// Clock
function updateClock() {
    const now = new Date();
    const el  = document.getElementById('clock');
    if (el) el.textContent = now.toUTCString().slice(17,25) + ' UTC';
}
updateClock();
setInterval(updateClock, 1000);

// Bot toggle — available globally for the mobile nav
async function toggleBot() {
    try {
        const res  = await fetch(window.routes.toggle, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        window.botActive = data.is_active;
        updateBotUI(data.is_active);
    } catch(e) { console.error('Toggle failed', e); }
}

function updateBotUI(active) {
    // Mobile centre button
    const btn  = document.getElementById('bot-toggle-mobile');
    const icon = document.getElementById('bot-icon-mobile');
    if (btn) {
        btn.className = btn.className
            .replace(/bg-emerald-500|bg-white\/10/, active ? 'bg-emerald-500' : 'bg-white/10')
            .replace(/border-white\/20/, active ? 'border-noir' : 'border-white/20');
    }
    if (icon) icon.className = icon.className.replace(/text-white|text-white\/40/, active ? 'text-white' : 'text-white/40');

    // Dashboard toggle (if present)
    const dash = document.getElementById('dash-bot-toggle');
    if (dash) {
        dash.classList.toggle('bg-emerald-500', active);
        dash.classList.toggle('bg-white/10',    !active);
    }
    const dashLabel = document.getElementById('dash-toggle-label');
    if (dashLabel) dashLabel.textContent = active ? 'Bot Active' : 'Bot Paused';

    // Sidebar dot
    const dot   = document.getElementById('status-dot');
    const label = document.getElementById('status-label');
    if (dot)   { dot.className   = `w-1.5 h-1.5 rounded-full ${active ? 'bg-emerald-400 animate-pulse' : 'bg-white/20'}`; }
    if (label) { label.textContent = active ? 'LIVE' : 'PAUSED'; }
}
</script>

@stack('scripts')
</body>
</html>