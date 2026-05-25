<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Sign In — Apex</title>

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
            animation: {
                'fade-in':  'fadeIn 0.4s ease-out',
                'slide-up': 'slideUp 0.45s cubic-bezier(0.2,1,0.3,1)',
                'shake':    'shake 0.4s cubic-bezier(.36,.07,.19,.97) both',
            },
            keyframes: {
                fadeIn:  { '0%': { opacity:'0' }, '100%': { opacity:'1' } },
                slideUp: { '0%': { transform:'translateY(20px)', opacity:'0' }, '100%': { transform:'translateY(0)', opacity:'1' } },
                shake:   { '10%,90%': { transform:'translate3d(-1px,0,0)' }, '20%,80%': { transform:'translate3d(2px,0,0)' }, '30%,50%,70%': { transform:'translate3d(-4px,0,0)' }, '40%,60%': { transform:'translate3d(4px,0,0)' } },
            },
        }
    }
}
</script>

<style>
/* Scanlines */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.08) 50%);
    background-size: 100% 4px;
    pointer-events: none;
    z-index: 50;
}
/* Dot grid */
body::after {
    content: '';
    position: fixed;
    inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,0.035) 1px, transparent 1px);
    background-size: 24px 24px;
    pointer-events: none;
    z-index: 0;
}
.glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); }
</style>
</head>

<body class="bg-[#080808] text-white font-sans min-h-screen flex items-center justify-center p-4 selection:bg-white selection:text-black overflow-hidden">

<div class="relative z-10 w-full max-w-sm animate-slide-up">

    {{-- Brand --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center gap-3 mb-5 group cursor-default">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center transition-transform group-hover:scale-110">
                <i class="fa-solid fa-chart-line text-black"></i>
            </div>
            <h1 class="font-serif text-2xl font-bold tracking-tight">Apex</h1>
        </div>
        <p class="text-[9px] font-mono text-white/25 tracking-[0.3em] uppercase">Volatility 25 · CRT Strategy</p>
    </div>

    {{-- Card --}}
    <div class="glass border border-white/[0.08] rounded-2xl overflow-hidden">

        {{-- Card header --}}
        <div class="px-6 pt-6 pb-5 border-b border-white/[0.06]">
            <h2 class="font-serif text-xl font-bold">Welcome back</h2>
            <p class="text-xs font-mono text-white/30 mt-1">Sign in to your trading account</p>
        </div>

        <div class="p-6">

            {{-- Error --}}
            @if($errors->any())
            <div class="flex items-start gap-3 px-4 py-3 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-400 text-xs font-mono mb-5 animate-fade-in">
                <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0"></i>
                <span>{{ $errors->first() }}</span>
            </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" id="login-form" class="space-y-4">
                @csrf

                {{-- Email --}}
                <div>
                    <label class="block text-[9px] font-mono text-white/30 tracking-widest uppercase mb-2">
                        Email address
                    </label>
                    <input type="email" name="email"
                        value="{{ old('email') }}"
                        placeholder="you@example.com"
                        autocomplete="email"
                        required
                        class="w-full glass border rounded-xl px-4 py-3 text-sm text-white/80 placeholder-white/20 outline-none transition-all
                               {{ $errors->has('email') ? 'border-rose-500/50 focus:border-rose-400' : 'border-white/[0.08] focus:border-white/30 focus:bg-white/[0.06]' }}">
                    @error('email')
                    <p class="text-[10px] font-mono text-rose-400 mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                {{-- API Key --}}
                <div>
                    <label class="block text-[9px] font-mono text-white/30 tracking-widest uppercase mb-2">
                        Deriv API key
                    </label>
                    <div class="relative">
                        <input type="password" name="deriv_api_key"
                            id="api-key-input"
                            placeholder="Your Personal Access Token"
                            autocomplete="current-password"
                            required
                            class="w-full glass border rounded-xl px-4 py-3 pr-11 text-sm text-white/80 placeholder-white/20 outline-none transition-all
                                   {{ $errors->has('deriv_api_key') ? 'border-rose-500/50 focus:border-rose-400' : 'border-white/[0.08] focus:border-white/30 focus:bg-white/[0.06]' }}">
                        <button type="button" onclick="toggleVisibility()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-white/25 hover:text-white/60 transition-colors text-sm">
                            <i class="fa-solid fa-eye" id="eye-icon"></i>
                        </button>
                    </div>
                    <p class="text-[10px] font-mono text-white/20 mt-1.5 leading-relaxed">
                        Generate at: app.deriv.com → Account Settings → API Token
                    </p>
                    @error('deriv_api_key')
                    <p class="text-[10px] font-mono text-rose-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <button type="submit" id="submit-btn"
                    class="w-full mt-2 py-3 bg-white text-black text-sm font-semibold rounded-xl
                           hover:bg-white/90 active:scale-[0.98] transition-all tracking-wide
                           flex items-center justify-center gap-2">
                    <span id="btn-text">Sign In</span>
                    <i class="fa-solid fa-arrow-right text-xs" id="btn-icon"></i>
                    <i class="fa-solid fa-spinner animate-spin hidden" id="btn-spinner"></i>
                </button>

            </form>
        </div>

        {{-- Footer --}}
        <div class="px-6 pb-5 text-center">
            <div class="flex items-center gap-3 mb-4">
                <div class="h-px flex-1 bg-white/[0.06]"></div>
                <span class="text-[10px] font-mono text-white/20">or</span>
                <div class="h-px flex-1 bg-white/[0.06]"></div>
            </div>
            <p class="text-xs text-white/30">
                New here?
                <a href="{{ route('register') }}" class="text-white/70 hover:text-white transition-colors font-medium ml-1">
                    Create an account →
                </a>
            </p>
        </div>
    </div>

    {{-- Fine print --}}
    <p class="text-center text-[9px] font-mono text-white/15 tracking-widest uppercase mt-6">
        Apex · CRT Bot · V25 · 4H
    </p>
</div>

<script>
function toggleVisibility() {
    const input = document.getElementById('api-key-input');
    const icon  = document.getElementById('eye-icon');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
}

document.getElementById('login-form').addEventListener('submit', function() {
    const btn     = document.getElementById('submit-btn');
    const text    = document.getElementById('btn-text');
    const icon    = document.getElementById('btn-icon');
    const spinner = document.getElementById('btn-spinner');
    btn.disabled  = true;
    text.textContent = 'Signing in...';
    icon.classList.add('hidden');
    spinner.classList.remove('hidden');
    btn.classList.add('opacity-70');
});
</script>

</body>
</html>