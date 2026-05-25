@extends('layouts.app')

@section('title', 'Trade Logs')
@section('subtitle', 'Full history · all sessions')

@section('content')

{{-- Filters --}}
<div class="glass border border-white/[0.07] rounded-2xl p-4 mb-4 flex flex-wrap items-center gap-3">
    <span class="text-[9px] font-mono text-white/25 tracking-widest uppercase">Filter</span>

    <select id="f-status" onchange="loadTrades()"
        class="glass border border-white/[0.08] text-white/70 text-xs font-mono rounded-xl px-3 py-2 outline-none cursor-pointer bg-transparent">
        <option value="all" class="bg-noir">All statuses</option>
        <option value="open" class="bg-noir">Open</option>
        <option value="tp1" class="bg-noir">TP1</option>
        <option value="tp2" class="bg-noir">TP2</option>
        <option value="sl" class="bg-noir">Stopped</option>
        <option value="be" class="bg-noir">Break-even</option>
    </select>

    <select id="f-dir" onchange="loadTrades()"
        class="glass border border-white/[0.08] text-white/70 text-xs font-mono rounded-xl px-3 py-2 outline-none cursor-pointer bg-transparent">
        <option value="all" class="bg-noir">All directions</option>
        <option value="buy" class="bg-noir">Buy</option>
        <option value="sell" class="bg-noir">Sell</option>
    </select>

    <span class="ml-auto text-[10px] font-mono text-white/25" id="trade-count"></span>
</div>

{{-- Table --}}
<div class="glass border border-white/[0.07] rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
            <thead>
                <tr class="border-b border-white/[0.06]">
                    @foreach(['Date / Time','Dir','Entry','SL','TP1','Close','ATR','SL Dist','R','P&L','Status'] as $h)
                    <th class="px-4 py-3 text-left text-[9px] font-mono text-white/25 tracking-widest uppercase whitespace-nowrap">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody id="trades-body">
                <tr><td colspan="11" class="px-4 py-10 text-center text-[10px] font-mono text-white/20 tracking-widest">LOADING...</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="px-5 py-4 border-t border-white/[0.06] flex items-center justify-between">
        <button onclick="changePage(-1)" id="btn-prev"
            class="text-xs font-mono text-white/30 hover:text-white border border-white/[0.08] hover:border-white/20 px-4 py-2 rounded-xl transition-all disabled:opacity-20">
            ← Prev
        </button>
        <span class="text-[10px] font-mono text-white/25" id="page-info"></span>
        <button onclick="changePage(1)" id="btn-next"
            class="text-xs font-mono text-white/30 hover:text-white border border-white/[0.08] hover:border-white/20 px-4 py-2 rounded-xl transition-all disabled:opacity-20">
            Next →
        </button>
    </div>
</div>

@endsection

@push('scripts')
<script>
let currentPage = 1;

const statusStyles = {
    open:      'border-sky-500/30 text-sky-400 bg-sky-500/10',
    tp1:       'border-emerald-500/30 text-emerald-400 bg-emerald-500/10',
    tp2:       'border-emerald-400/40 text-emerald-300 bg-emerald-400/15',
    sl:        'border-rose-500/30 text-rose-400 bg-rose-500/10',
    be:        'border-white/10 text-white/40 bg-white/5',
    cancelled: 'border-white/10 text-white/25 bg-white/5',
};

function badge(status) {
    const s = statusStyles[status] || statusStyles.be;
    return `<span class="text-[9px] font-mono px-2 py-0.5 rounded-full border ${s}">${status.toUpperCase()}</span>`;
}

function dirBadge(d) {
    return d === 'buy'
        ? `<span class="text-[9px] font-mono px-2 py-0.5 rounded-full border border-emerald-500/30 text-emerald-400 bg-emerald-500/10">BUY</span>`
        : `<span class="text-[9px] font-mono px-2 py-0.5 rounded-full border border-rose-500/30 text-rose-400 bg-rose-500/10">SELL</span>`;
}

function pnl(v) {
    if (v === null || v === undefined) return '<span class="font-mono text-xs text-white/25">—</span>';
    const cls = v >= 0 ? 'text-emerald-400' : 'text-rose-400';
    return `<span class="font-mono text-xs ${cls}">${v >= 0 ? '+' : ''}$${Math.abs(v).toFixed(2)}</span>`;
}

function rMult(v) {
    if (!v) return '<span class="font-mono text-xs text-white/25">—</span>';
    const cls = v >= 0 ? 'text-emerald-400' : 'text-rose-400';
    return `<span class="font-mono text-xs ${cls}">${parseFloat(v).toFixed(2)}R</span>`;
}

async function loadTrades() {
    const status = document.getElementById('f-status').value;
    const dir    = document.getElementById('f-dir').value;
    const url    = `${window.routes.trades}?page=${currentPage}&status=${status}&direction=${dir}`;

    document.getElementById('trades-body').innerHTML =
        `<tr><td colspan="11" class="px-4 py-10 text-center text-[10px] font-mono text-white/20 tracking-widest">LOADING...</td></tr>`;

    try {
        const data = await fetch(url).then(r => r.json());

        if (!data.data || data.data.length === 0) {
            document.getElementById('trades-body').innerHTML =
                `<tr><td colspan="11" class="px-4 py-12 text-center text-[10px] font-mono text-white/20 tracking-widest uppercase">No trades found</td></tr>`;
            document.getElementById('trade-count').textContent = '0 results';
            return;
        }

        document.getElementById('trades-body').innerHTML = data.data.map(t => `
            <tr class="border-b border-white/[0.04] hover:bg-white/[0.03] transition-colors">
                <td class="px-4 py-3 font-mono text-[11px] text-white/25 whitespace-nowrap">${(t.created_at||'').slice(0,16).replace('T',' ')}</td>
                <td class="px-4 py-3">${dirBadge(t.direction)}</td>
                <td class="px-4 py-3 font-mono text-xs text-white/70">${parseFloat(t.entry_price).toFixed(2)}</td>
                <td class="px-4 py-3 font-mono text-xs text-white/30">${parseFloat(t.sl_price).toFixed(2)}</td>
                <td class="px-4 py-3 font-mono text-xs text-white/30">${parseFloat(t.tp1_price).toFixed(2)}</td>
                <td class="px-4 py-3 font-mono text-xs text-white/30">${t.close_price ? parseFloat(t.close_price).toFixed(2) : '—'}</td>
                <td class="px-4 py-3 font-mono text-xs text-white/30">${t.atr_at_entry ? parseFloat(t.atr_at_entry).toFixed(3) : '—'}</td>
                <td class="px-4 py-3 font-mono text-xs text-white/30">${t.sl_distance_in_atr ? parseFloat(t.sl_distance_in_atr).toFixed(2)+'×' : '—'}</td>
                <td class="px-4 py-3">${rMult(t.r_multiple)}</td>
                <td class="px-4 py-3">${pnl(t.pnl)}</td>
                <td class="px-4 py-3">${badge(t.status)}</td>
            </tr>
        `).join('');

        document.getElementById('trade-count').textContent  = `${data.total} total`;
        document.getElementById('page-info').textContent    = `${data.current_page} / ${data.last_page}`;
        document.getElementById('btn-prev').disabled = data.current_page <= 1;
        document.getElementById('btn-next').disabled = data.current_page >= data.last_page;

    } catch(e) { console.error(e); }
}

function changePage(d) {
    currentPage = Math.max(1, currentPage + d);
    loadTrades();
}

loadTrades();
</script>
@endpush