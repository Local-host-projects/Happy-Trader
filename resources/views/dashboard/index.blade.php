@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Today · Volatility 25 · 4H')

@section('content')

{{-- Live R_25 Price --}}
<div class="glass border border-white/[0.07] rounded-2xl px-5 py-4 mb-5 flex items-center justify-between flex-wrap gap-4">
    <div class="flex items-center gap-4">
        <div class="relative">
            <div class="w-10 h-10 rounded-xl bg-white/[0.06] border border-white/[0.08] flex items-center justify-center shrink-0">
                <i class="fa-solid fa-chart-line text-white/50"></i>
            </div>
            {{-- Live indicator dot --}}
            <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-emerald-400 border-2 border-noir animate-pulse" id="live-dot"></span>
        </div>
        <div>
            <p class="text-[9px] font-mono text-white/25 tracking-widest uppercase mb-1">
                Volatility 25 Index · R_25 · Live
            </p>
            <div class="flex items-baseline gap-3">
                <span class="font-mono text-3xl font-bold text-white" id="live-price">—</span>
                <span class="font-mono text-sm" id="live-change">
                    <span id="live-arrow"></span>
                    <span id="live-diff" class="text-white/30">—</span>
                </span>
            </div>
            <p class="text-[10px] font-mono text-white/20 mt-0.5" id="live-time">Connecting...</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-right">
            <p class="text-[9px] font-mono text-white/25 tracking-widest uppercase mb-1">Tick / 2s</p>
            <p class="font-mono text-xs text-white/40" id="tick-count">0 ticks</p>
        </div>
        <div class="text-right">
            <p class="text-[9px] font-mono text-white/25 tracking-widest uppercase mb-1">Status</p>
            <span class="text-[9px] font-mono px-2 py-1 rounded-full border border-emerald-500/30 text-emerald-400 bg-emerald-500/10" id="ws-status">
                CONNECTING
            </span>
        </div>
    </div>
</div>
{{-- Stats grid --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-5">

    @php
    $stats = [
        ['id'=>'s-trades',  'label'=>"Today's Trades",  'sub'=>'<span id="s-open" class="font-mono text-white/30 text-xs">— open</span>',   'accent'=>'text-white'],
        ['id'=>'s-winrate', 'label'=>'Win Rate',         'sub'=>'<span class="font-mono text-white/30 text-xs">Closed trades</span>',         'accent'=>'text-violet-400'],
        ['id'=>'s-pnl',     'label'=>"Today's P&L",     'sub'=>'<span class="font-mono text-white/30 text-xs">Realised</span>',               'accent'=>'text-emerald-400'],
        ['id'=>'s-dd',      'label'=>'Drawdown',         'sub'=>'<span class="font-mono text-white/30 text-xs">From peak equity</span>',       'accent'=>'text-white'],
    ];
    @endphp

    @foreach($stats as $s)
    <div class="glass border border-white/[0.07] rounded-2xl p-4 md:p-5 relative overflow-hidden group transition-all hover:border-white/[0.14]">
        <div class="absolute top-0 left-4 right-4 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
        <p class="text-[9px] font-mono text-white/30 tracking-widest uppercase mb-3">{{ $s['label'] }}</p>
        <p class="font-serif text-2xl md:text-3xl font-bold {{ $s['accent'] }}" id="{{ $s['id'] }}">—</p>
        <div class="mt-1.5">{!! $s['sub'] !!}</div>
    </div>
    @endforeach
</div>
{{-- Wallet Balance --}}
<div class="glass border border-white/[0.07] rounded-2xl px-5 py-4 mb-5 flex items-center justify-between flex-wrap gap-4">
    <div class="flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-white/[0.06] border border-white/[0.08] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-wallet text-white/50"></i>
        </div>
        <div>
            <p class="text-[9px] font-mono text-white/25 tracking-widest uppercase mb-1">Deriv Wallet Balance</p>
            <div class="flex items-baseline gap-2">
                <span class="font-serif text-2xl font-bold" id="wallet-balance">—</span>
                <span class="text-xs font-mono text-white/30" id="wallet-currency">USD</span>
            </div>
            <p class="text-[10px] font-mono text-white/20 mt-0.5" id="wallet-account">Account: —</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="text-[9px] font-mono px-2 py-1 rounded-full border border-white/[0.08] text-white/30" id="wallet-source"></span>
        <button onclick="refreshBalance()"
            class="flex items-center gap-2 text-xs font-mono text-white/30 hover:text-white border border-white/[0.08] hover:border-white/20 px-3 py-1.5 rounded-xl transition-all">
            <i class="fa-solid fa-rotate-right" id="refresh-icon"></i> Refresh
        </button>
        <a href="{{ route('settings') }}"
            class="text-[10px] font-mono text-white/25 hover:text-white/60 transition-colors">
            Switch account →
        </a>
    </div>
</div>
{{-- Manual Trade Controls --}}
<div class="glass border border-white/[0.07] rounded-2xl p-5 mb-5">
    <div class="flex items-center justify-between mb-4">
        <p class="text-xs font-mono text-white/40 tracking-widest uppercase">Manual trade · R_25 · 60s</p>
        <span class="text-[10px] font-mono text-white/25" id="manual-status"></span>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <button onclick="placeTrade('buy')" id="btn-buy"
            class="py-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-semibold text-sm
                   hover:bg-emerald-500/20 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-arrow-trend-up"></i> BUY
        </button>
        <button onclick="placeTrade('sell')" id="btn-sell"
            class="py-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 font-semibold text-sm
                   hover:bg-rose-500/20 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-arrow-trend-down"></i> SELL
        </button>
    </div>
</div>

{{-- Live Positions Panel --}}
<div class="glass border border-white/[0.07] rounded-2xl overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
        <p class="text-xs font-mono text-white/40 tracking-widest uppercase">Live positions</p>
        <span class="text-[10px] font-mono text-white/25" id="positions-count">0 open</span>
    </div>
    <div id="positions-list" class="p-5 flex flex-col gap-3">
        <p class="text-[10px] font-mono text-white/20 text-center py-6 tracking-widest uppercase">No open positions</p>
    </div>
</div>

{{-- Bot control + Chart --}}
<div class="grid grid-cols-1 md:grid-cols-[260px_1fr] gap-4 mb-5">

    {{-- Bot control card --}}
    <div class="glass border border-white/[0.07] rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <p class="text-xs font-mono text-white/40 tracking-widest uppercase">Bot Control</p>
            <span class="text-[9px] font-mono px-2 py-1 rounded-full border {{ Auth::user()->is_active ? 'border-emerald-500/40 text-emerald-400 bg-emerald-500/10' : 'border-white/10 text-white/30' }}" id="bot-status-badge">
                {{ Auth::user()->is_active ? 'LIVE' : 'PAUSED' }}
            </span>
        </div>
        <div class="p-5 flex flex-col gap-4">

            {{-- Toggle --}}
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" id="dash-toggle-label">{{ Auth::user()->is_active ? 'Bot Active' : 'Bot Paused' }}</p>
                    <p class="text-[11px] text-white/30 font-mono mt-0.5">{{ Auth::user()->is_active ? 'Trading every 4H' : 'Click to activate' }}</p>
                </div>
                <button onclick="toggleBot()" id="dash-bot-toggle"
                    class="w-12 h-6 rounded-full flex items-center transition-all {{ Auth::user()->is_active ? 'bg-emerald-500 justify-end' : 'bg-white/10 justify-start' }}">
                    <span class="w-5 h-5 bg-white rounded-full shadow mx-0.5 block transition-all"></span>
                </button>
            </div>

            <div class="border-t border-white/[0.06] pt-4 flex flex-col gap-2">
                @foreach([
                    ['Risk / trade',  $user->parameters->risk_percent.'%'],
                    ['Zone thresh.',  $user->parameters->zone_threshold],
                    ['SL multiplier', $user->parameters->sl_atr_multiplier.'×'],
                    ['Max trades',    $user->parameters->max_concurrent_trades],
                ] as [$k, $v])
                <div class="flex justify-between items-center">
                    <span class="text-xs text-white/30">{{ $k }}</span>
                    <span class="text-xs font-mono text-white/70">{{ $v }}</span>
                </div>
                @endforeach
            </div>

            @if($user->parameters->ai_last_adjusted_at)
            <div class="border-t border-white/[0.06] pt-3">
                <p class="text-[9px] font-mono text-white/20 tracking-widest uppercase mb-1">Last AI review</p>
                <p class="text-xs text-white/40">{{ $user->parameters->ai_last_adjusted_at->diffForHumans() }}</p>
            </div>
            @endif

            <a href="{{ route('parameters') }}" class="mt-1 w-full text-center text-xs font-mono text-white/40 border border-white/[0.08] rounded-xl py-2.5 hover:text-white hover:border-white/20 transition-all">
                Edit Parameters
            </a>
        </div>
    </div>

    {{-- Equity chart --}}
    <div class="glass border border-white/[0.07] rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <p class="text-xs font-mono text-white/40 tracking-widest uppercase">Equity Curve</p>
            <span class="text-[10px] font-mono text-white/20">Balance over time</span>
        </div>
        <div class="p-5">
            <canvas id="equityChart" class="w-full" height="160"></canvas>
        </div>
    </div>
</div>

{{-- Recent trades --}}
<div class="glass border border-white/[0.07] rounded-2xl overflow-hidden">
    <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
        <p class="text-xs font-mono text-white/40 tracking-widest uppercase">Recent Trades</p>
        <a href="{{ route('trades') }}" class="text-[11px] font-mono text-white/30 hover:text-white transition-colors">View all →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px]">
            <thead>
                <tr class="border-b border-white/[0.06]">
                    @foreach(['Time','Dir','Entry','SL','TP1','ATR','R','P&L','Status'] as $h)
                    <th class="px-4 py-3 text-left text-[9px] font-mono text-white/25 tracking-widest uppercase">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($todayTrades as $trade)
                <tr class="border-b border-white/[0.04] hover:bg-white/[0.03] transition-colors">
                    <td class="px-4 py-3 font-mono text-[11px] text-white/30">{{ $trade->created_at->format('H:i') }}</td>
                    <td class="px-4 py-3">
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-full border
                            {{ $trade->direction === 'buy' ? 'border-emerald-500/30 text-emerald-400 bg-emerald-500/10' : 'border-rose-500/30 text-rose-400 bg-rose-500/10' }}">
                            {{ strtoupper($trade->direction) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-white/70">{{ number_format($trade->entry_price, 2) }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-white/30">{{ number_format($trade->sl_price, 2) }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-white/30">{{ number_format($trade->tp1_price, 2) }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-white/30">{{ $trade->atr_at_entry ? number_format($trade->atr_at_entry, 3) : '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs {{ ($trade->r_multiple ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $trade->r_multiple ? number_format($trade->r_multiple, 2).'R' : '—' }}
                    </td>
                    <td class="px-4 py-3 font-mono text-xs {{ ($trade->pnl ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $trade->pnl !== null ? (($trade->pnl >= 0 ? '+' : '').'$'.number_format(abs($trade->pnl), 2)) : '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @php
                        $statusMap = ['open'=>'text-sky-400 border-sky-500/30 bg-sky-500/10','tp1'=>'text-emerald-400 border-emerald-500/30 bg-emerald-500/10','tp2'=>'text-emerald-300 border-emerald-400/40 bg-emerald-400/15','sl'=>'text-rose-400 border-rose-500/30 bg-rose-500/10','be'=>'text-white/40 border-white/10 bg-white/5','cancelled'=>'text-white/30 border-white/10 bg-white/5'];
                        @endphp
                        <span class="text-[9px] font-mono px-2 py-0.5 rounded-full border {{ $statusMap[$trade->status] ?? 'text-white/30 border-white/10' }}">
                            {{ strtoupper($trade->status) }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-xs font-mono text-white/20 tracking-widest uppercase">
                        No trades today — bot fires every 4 hours
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Stats
async function loadStats() {
    try {
        const data = await fetch(window.routes.stats).then(r => r.json());

        document.getElementById('s-trades').textContent  = data.today_trades;
        document.getElementById('s-open').textContent    = `${data.open_trades} open`;
        document.getElementById('s-winrate').textContent = `${data.win_rate}%`;

        const pnl    = data.today_pnl;
        const pnlEl  = document.getElementById('s-pnl');
        pnlEl.textContent = (pnl >= 0 ? '+' : '') + '$' + Math.abs(pnl).toFixed(2);
        pnlEl.className   = pnlEl.className.replace(/text-\w+-\d+|text-white/, pnl >= 0 ? 'text-emerald-400' : 'text-rose-400');

        const dd   = data.drawdown_pct;
        const ddEl = document.getElementById('s-dd');
        ddEl.textContent = `${dd}%`;
        if (dd > 5) ddEl.className = ddEl.className.replace('text-white', 'text-rose-400');

        // Update bot badge on dashboard
        const badge = document.getElementById('bot-status-badge');
        if (badge) {
            badge.textContent = data.is_active ? 'LIVE' : 'PAUSED';
            badge.className   = badge.className.replace(/border-\S+ text-\S+ bg-\S+/,
                data.is_active
                    ? 'border-emerald-500/40 text-emerald-400 bg-emerald-500/10'
                    : 'border-white/10 text-white/30 bg-transparent');
        }
    } catch(e) {}
}

// Chart
let equityChart = null;
async function loadChart() {
    try {
        const points = await fetch(window.routes.snapshots).then(r => r.json());
        const ctx    = document.getElementById('equityChart').getContext('2d');

        const grad = ctx.createLinearGradient(0, 0, 0, 200);
        grad.addColorStop(0, 'rgba(255,255,255,0.12)');
        grad.addColorStop(1, 'rgba(255,255,255,0)');

        if (equityChart) equityChart.destroy();
        equityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: points.map(p => p.label),
                datasets: [{
                    data: points.map(p => p.balance),
                    borderColor: 'rgba(255,255,255,0.7)',
                    borderWidth: 1.5,
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBackgroundColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(8,8,8,0.95)',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        titleColor: 'rgba(255,255,255,0.3)',
                        bodyColor: 'rgba(255,255,255,0.8)',
                        bodyFont: { family: 'JetBrains Mono' },
                        titleFont: { family: 'JetBrains Mono', size: 10 },
                        callbacks: { label: c => ` $${c.parsed.y.toFixed(2)}` }
                    }
                },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false }, ticks: { color: 'rgba(255,255,255,0.2)', font: { family: 'JetBrains Mono', size: 9 }, maxTicksLimit: 5 } },
                    y: { grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false }, ticks: { color: 'rgba(255,255,255,0.2)', font: { family: 'JetBrains Mono', size: 9 }, callback: v => '$'+v } }
                }
            }
        });
    } catch(e) {}
}

loadStats();
loadChart();
setInterval(loadStats, 30000);
// Wallet balance
async function refreshBalance() {
    const icon = document.getElementById('refresh-icon');
    icon.classList.add('animate-spin');

    try {
        const data = await fetch('{{ route("data.balance") }}').then(r => r.json());

        document.getElementById('wallet-balance').textContent  = '$' + data.balance;
        document.getElementById('wallet-currency').textContent = data.currency;
        document.getElementById('wallet-account').textContent  = 'Account: ' + data.account_id + ' · ' + (data.account_type || '');
        document.getElementById('wallet-source').textContent   = data.source === 'live' ? '● LIVE' : '○ CACHED';

    } catch(e) {
        document.getElementById('wallet-balance').textContent = 'Error';
    } finally {
        icon.classList.remove('animate-spin');
    }
}

// Load balance on page load
refreshBalance();
</script>
<script>
// ── Live R_25 Price via Deriv public WebSocket ──────────────────────────
(function () {
    const SYMBOL    = 'R_25';
    const WS_URL    = 'wss://api.derivws.com/trading/v1/options/ws/public';
    const APP_ID    = '{{ config("deriv.app_id") }}';

    let ws          = null;
    let lastPrice   = null;
    let tickCount   = 0;
    let reconnectMs = 3000;

    function connect() {
        ws = new WebSocket(WS_URL);

        ws.onopen = function () {
            document.getElementById('ws-status').textContent  = 'LIVE';
            document.getElementById('ws-status').className    = 'text-[9px] font-mono px-2 py-1 rounded-full border border-emerald-500/30 text-emerald-400 bg-emerald-500/10';
            document.getElementById('live-time').textContent  = 'Connected';

            // Subscribe to tick stream
            ws.send(JSON.stringify({ ticks: SYMBOL, subscribe: 1 }));
            reconnectMs = 3000;
        };

        ws.onmessage = function (event) {
            const data = JSON.parse(event.data);

            if (data.tick) {
                const price = parseFloat(data.tick.quote).toFixed(4);
                const epoch = data.tick.epoch;
                const time  = new Date(epoch * 1000).toUTCString().slice(17, 25) + ' UTC';

                // Direction indicator
                let arrow = '';
                let cls   = 'text-white/30';

                if (lastPrice !== null) {
                    const diff = parseFloat(price) - parseFloat(lastPrice);
                    if (diff > 0) {
                        arrow = '▲ ';
                        cls   = 'text-emerald-400';
                    } else if (diff < 0) {
                        arrow = '▼ ';
                        cls   = 'text-rose-400';
                    }
                    document.getElementById('live-diff').textContent  = Math.abs(diff).toFixed(4);
                    document.getElementById('live-diff').className    = cls;
                    document.getElementById('live-arrow').textContent = arrow;
                    document.getElementById('live-arrow').className   = cls;
                }

                document.getElementById('live-price').textContent = price;
                document.getElementById('live-time').textContent  = time;
                document.getElementById('tick-count').textContent = (++tickCount) + ' ticks';

                // Flash effect on price update
                const priceEl = document.getElementById('live-price');
                priceEl.style.opacity = '0.5';
                setTimeout(() => { priceEl.style.opacity = '1'; }, 100);

                lastPrice = price;
            }
        };

        ws.onerror = function () {
            document.getElementById('ws-status').textContent = 'ERROR';
            document.getElementById('ws-status').className   = 'text-[9px] font-mono px-2 py-1 rounded-full border border-rose-500/30 text-rose-400 bg-rose-500/10';
            document.getElementById('live-dot').className    = 'absolute -top-1 -right-1 w-3 h-3 rounded-full bg-rose-400 border-2 border-noir';
        };

        ws.onclose = function () {
            document.getElementById('ws-status').textContent = 'RECONNECTING';
            document.getElementById('ws-status').className   = 'text-[9px] font-mono px-2 py-1 rounded-full border border-amber-500/30 text-amber-400 bg-amber-500/10';
            document.getElementById('live-dot').className    = 'absolute -top-1 -right-1 w-3 h-3 rounded-full bg-amber-400 border-2 border-noir';

            // Auto-reconnect with backoff
            setTimeout(connect, reconnectMs);
            reconnectMs = Math.min(reconnectMs * 1.5, 30000);
        };
    }

    // Pause/resume when tab loses/gains focus to save connections
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && ws) {
            ws.close();
        } else if (!document.hidden) {
            connect();
        }
    });

    connect();
})();
</script>
<script>
// ── Manual Buy/Sell ────────────────────────────────────────────────────
async function placeTrade(direction) {
    const buyBtn  = document.getElementById('btn-buy');
    const sellBtn = document.getElementById('btn-sell');
    const status  = document.getElementById('manual-status');

    buyBtn.disabled  = true;
    sellBtn.disabled = true;
    buyBtn.classList.add('opacity-50');
    sellBtn.classList.add('opacity-50');
    status.textContent = 'Placing trade...';

    try {
        const res  = await fetch(window.routes.buy || '{{ route("trading.buy") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json' },
            body: JSON.stringify({ direction }),
        });
        const data = await res.json();

        if (data.error) {
            status.textContent = data.error;
            status.className   = 'text-[10px] font-mono text-rose-400';
        } else {
            status.textContent = `${direction.toUpperCase()} placed · $${data.stake}`;
            status.className   = 'text-[10px] font-mono text-emerald-400';
            loadPositions();
        }
    } catch (e) {
        status.textContent = 'Trade failed — check connection';
        status.className   = 'text-[10px] font-mono text-rose-400';
    } finally {
        buyBtn.disabled  = false;
        sellBtn.disabled = false;
        buyBtn.classList.remove('opacity-50');
        sellBtn.classList.remove('opacity-50');
        setTimeout(() => { status.textContent = ''; }, 4000);
    }
}

// ── Live Positions Panel ──────────────────────────────────────────────
let currentPositions = {};
let livePriceMap     = {};

async function loadPositions() {
    try {
        const data = await fetch('{{ route("data.positions") }}').then(r => r.json());

        if (data.error) {
            document.getElementById('positions-list').innerHTML =
                `<p class="text-[10px] font-mono text-rose-400/60 text-center py-6">${data.error}</p>`;
            return;
        }

        const previousIds = new Set(Object.keys(currentPositions));
        const currentIds  = new Set(data.open.map(p => String(p.contract_id)));

        // Detect contracts that disappeared — they closed since last poll
        previousIds.forEach(id => {
            if (!currentIds.has(id)) {
                flashClose(currentPositions[id]);
            }
        });

        currentPositions = {};
        data.open.forEach(p => currentPositions[p.contract_id] = p);

        renderPositions(data.open);
        document.getElementById('positions-count').textContent = data.open.length + ' open';

    } catch (e) {}
}

function renderPositions(positions) {
    const container = document.getElementById('positions-list');

    if (positions.length === 0) {
        container.innerHTML = `<p class="text-[10px] font-mono text-white/20 text-center py-6 tracking-widest uppercase">No open positions</p>`;
        return;
    }

    container.innerHTML = positions.map(p => {
        const badgeCls  = p.direction === 'buy'
            ? 'border-emerald-500/30 text-emerald-400 bg-emerald-500/10'
            : 'border-rose-500/30 text-rose-400 bg-rose-500/10';
        const profitCls = p.profit >= 0 ? 'text-emerald-400' : 'text-rose-400';

        const secondsLeft = p.date_expiry
            ? Math.max(0, Math.round(p.date_expiry - (Date.now() / 1000)))
            : '—';

        return `
        <div class="flex items-center justify-between p-4 rounded-xl border border-white/[0.08] bg-white/[0.02]" data-contract-id="${p.contract_id}">
            <div class="flex items-center gap-3">
                <span class="text-[9px] font-mono px-2 py-0.5 rounded-full border ${badgeCls}">${p.direction.toUpperCase()}</span>
                <div>
                    <p class="text-xs font-mono text-white/70">Entry ${p.entry_price.toFixed(3)}</p>
                    <p class="text-[10px] font-mono text-white/30">Now ${p.current_spot.toFixed(3)}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs font-mono ${profitCls}">${p.profit >= 0 ? '+' : ''}$${p.profit.toFixed(2)}</p>
                <p class="text-[10px] font-mono text-white/25">${secondsLeft}s left</p>
            </div>
        </div>`;
    }).join('');
}

function flashClose(position) {
    const el = document.querySelector(`[data-contract-id="${position.contract_id}"]`);
    if (!el) return;

    const won = position.profit > 0;
    el.style.transition  = 'all 0.4s ease';
    el.style.background  = won ? 'rgba(16,185,129,0.15)' : 'rgba(244,63,94,0.15)';
    el.style.borderColor = won ? 'rgba(16,185,129,0.4)' : 'rgba(244,63,94,0.4)';

    setTimeout(() => { if (el) el.style.opacity = '0'; }, 1200);
}

// ── Persistent live connection — public tick stream ──────────────────
// Extends the existing price ticker to also feed the positions panel
(function () {
    const SYMBOL = 'R_25';
    let ws       = null;
    let reconnectMs = 3000;

    function connect() {
        ws = new WebSocket('wss://api.derivws.com/trading/v1/options/ws/public');

        ws.onopen = function () {
            ws.send(JSON.stringify({ ticks: SYMBOL, subscribe: 1 }));
            reconnectMs = 3000;
        };

        ws.onmessage = function (event) {
            const data = JSON.parse(event.data);
            if (data.tick) {
                const price = parseFloat(data.tick.quote);
                livePriceMap.buy  = price;
                livePriceMap.sell = price;
                if (Object.keys(currentPositions).length > 0) {
                    renderPositions(Object.values(currentPositions));
                }
            }
        };

        ws.onclose = function () {
            setTimeout(connect, reconnectMs);
            reconnectMs = Math.min(reconnectMs * 1.5, 30000);
        };
    }

    connect();
})();

// Poll positions every 5 seconds — reflects MonitorOpenTradesJob closures
loadPositions();
setInterval(loadPositions, 5000);
</script>
@endpush
