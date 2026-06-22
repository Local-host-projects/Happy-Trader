@extends('layouts.app')

@section('title', 'Parameters')
@section('subtitle', 'Bot calibration · AI-managed')

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">

    {{-- Parameter form --}}
    <div class="glass border border-white/[0.07] rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <p class="text-xs font-mono text-white/40 tracking-widest uppercase">Parameter Values</p>
            <span class="text-[10px] font-mono text-white/20">
                Updated {{ $params->updated_at}}
            </span>
        </div>

        <form method="POST" action="{{ route('parameters.update') }}" class="p-5 space-y-7">
            @csrf
            @method('PUT')

            {{-- Section: Risk --}}
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-px flex-1 bg-white/[0.06]"></div>
                    <p class="text-[9px] font-mono text-white/30 tracking-widest uppercase">Risk Management</p>
                    <div class="h-px flex-1 bg-white/[0.06]"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach([
                        ['risk_percent',          'Risk % / trade',       $params->risk_percent,          '0.1','5','0.1',   '0.1 – 5.0'],
                        ['daily_loss_limit_pct',  'Daily loss limit %',   $params->daily_loss_limit_pct,  '0.5','20','0.25', 'Pauses bot at this %'],
                        ['max_concurrent_trades', 'Max concurrent',       $params->max_concurrent_trades, '1','5','1',       '1 – 5 trades'],
                    ] as [$name, $label, $val, $min, $max, $step, $hint])
                    <div>
                        <label class="block text-[9px] font-mono text-white/30 tracking-widest uppercase mb-2">{{ $label }}</label>
                        <input type="number" name="{{ $name }}" value="{{ $val }}"
                            min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
                            class="w-full glass border border-white/[0.08] rounded-xl px-3 py-2.5 text-sm font-mono text-white/80
                                   focus:outline-none focus:border-white/30 focus:bg-white/[0.06] transition-all bg-transparent">
                        <p class="text-[10px] font-mono text-white/20 mt-1.5">{{ $hint }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Section: Setup filters --}}
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-px flex-1 bg-white/[0.06]"></div>
                    <p class="text-[9px] font-mono text-white/30 tracking-widest uppercase">CRT Setup Filters</p>
                    <div class="h-px flex-1 bg-white/[0.06]"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach([
                        ['zone_threshold',    'Zone threshold',     $params->zone_threshold,    '0.15','0.45','0.01', '0.30 = top/bottom 30%'],
                        ['sl_atr_multiplier', 'SL ATR multiplier',  $params->sl_atr_multiplier, '1.0','5','0.1',      'Floor: 1.0'],
                        ['min_range_atr_pct', 'Min range % ATR',   $params->min_range_atr_pct, '10','200','5',        'Candle range floor'],
                        ['max_range_atr_pct', 'Max range % ATR',   $params->max_range_atr_pct, '50','500','10',       'Spike candle ceiling'],
                        ['adx_min_threshold', 'ADX min threshold', $params->adx_min_threshold, '10','50','1',         'Empty = disabled'],
                    ] as [$name, $label, $val, $min, $max, $step, $hint])
                    <div>
                        <label class="block text-[9px] font-mono text-white/30 tracking-widest uppercase mb-2">{{ $label }}</label>
                        <input type="number" name="{{ $name }}" value="{{ old($name, $val) }}"
                            min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
                            placeholder="{{ $name === 'adx_min_threshold' ? 'Disabled' : '' }}"
                            class="w-full glass border border-white/[0.08] rounded-xl px-3 py-2.5 text-sm font-mono text-white/80
                                   focus:outline-none focus:border-white/30 focus:bg-white/[0.06] transition-all bg-transparent placeholder-white/20">
                        <p class="text-[10px] font-mono text-white/20 mt-1.5">{{ $hint }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Section: Take profit --}}
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-px flex-1 bg-white/[0.06]"></div>
                    <p class="text-[9px] font-mono text-white/30 tracking-widest uppercase">Take Profit</p>
                    <div class="h-px flex-1 bg-white/[0.06]"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach([
                        ['tp1_close_pct',      'TP1 close %',          $params->tp1_close_pct,      '10','100','5',  '% to close at TP1'],
                        ['tp2_atr_multiplier', 'TP2 ATR multiplier',   $params->tp2_atr_multiplier, '0.5','5','0.1', 'Empty = disabled'],
                        ['trailing_atr_step',  'Trailing ATR step',    $params->trailing_atr_step,  '0.1','3','0.1', 'Empty = no trail'],
                    ] as [$name, $label, $val, $min, $max, $step, $hint])
                    <div>
                        <label class="block text-[9px] font-mono text-white/30 tracking-widest uppercase mb-2">{{ $label }}</label>
                        <input type="number" name="{{ $name }}" value="{{ old($name, $val) }}"
                            min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
                            placeholder="Disabled"
                            class="w-full glass border border-white/[0.08] rounded-xl px-3 py-2.5 text-sm font-mono text-white/80
                                   focus:outline-none focus:border-white/30 focus:bg-white/[0.06] transition-all bg-transparent placeholder-white/20">
                        <p class="text-[10px] font-mono text-white/20 mt-1.5">{{ $hint }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Trend filter toggle --}}
                <div class="mt-4 flex items-center gap-3">
                    <input type="hidden" name="trend_filter_enabled" value="0">
                    <input type="checkbox" id="trend_filter" name="trend_filter_enabled" value="1"
                        {{ $params->trend_filter_enabled ? 'checked' : '' }}
                        class="w-4 h-4 cursor-pointer accent-white rounded">
                    <div>
                        <label for="trend_filter" class="text-sm font-medium cursor-pointer">Enable HTF trend filter</label>
                        <p class="text-[10px] font-mono text-white/25 mt-0.5">Only take setups aligned with daily EMA direction</p>
                    </div>
                </div>
            </div>

            {{-- Save --}}
            <button type="submit"
                class="w-full py-3 bg-white text-black text-sm font-semibold rounded-xl hover:bg-white/90 active:scale-[0.99] transition-all tracking-wide">
                Save Parameters
            </button>
        </form>
    </div>

    {{-- Right column --}}
    <div class="flex flex-col gap-4">

        {{-- AI reviewer --}}
        <div class="glass border border-white/[0.07] rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
                <p class="text-xs font-mono text-white/40 tracking-widest uppercase">AI Reviewer</p>
                <span class="text-[9px] font-mono px-2 py-1 rounded-full border border-white/10 text-white/30">AUTO · 23:30</span>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-xs text-white/40 leading-relaxed font-mono">
                    Reviews today's performance daily at <span class="text-white/60">23:30</span>.
                    Adjusts up to 2 parameters when the same signal appears across 3+ trades.
                </p>

                @if($params->ai_last_adjusted_at)
                <div class="border border-white/[0.08] rounded-xl p-4">
                    <p class="text-[9px] font-mono text-white/25 tracking-widest uppercase mb-2">
                        Last review · {{ $params->ai_last_adjusted_at->format('M d, H:i') }}
                    </p>
                    <p class="text-xs text-white/50 leading-relaxed font-mono">
                        {{ $params->ai_adjustment_note ?? 'No note recorded.' }}
                    </p>
                </div>
                @else
                <div class="border border-white/[0.06] border-dashed rounded-xl p-6 text-center">
                    <p class="text-[10px] font-mono text-white/20 tracking-widest uppercase">No adjustments yet</p>
                    <p class="text-[10px] font-mono text-white/15 mt-1">Needs 3+ completed trades</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Hard limits --}}
        <div class="glass border border-white/[0.07] rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-white/[0.06]">
                <p class="text-xs font-mono text-white/40 tracking-widest uppercase">Hard Limits</p>
            </div>
            <div class="p-5 space-y-2">
                @foreach([
                    ['SL multiplier floor',  '≥ 1.0'],
                    ['Risk % ceiling',       '≤ 3.0'],
                    ['Zone threshold range', '0.15 – 0.45'],
                    ['Daily loss floor',     '≥ 1.0'],
                    ['Drawdown lock',        '> 5% disables risk increase'],
                ] as [$label, $constraint])
                <div class="flex justify-between items-start py-2 border-b border-white/[0.04] last:border-0">
                    <span class="text-xs text-white/30">{{ $label }}</span>
                    <span class="text-[10px] font-mono text-white/50 text-right ml-4">{{ $constraint }}</span>
                </div>
                @endforeach
                <p class="text-[10px] font-mono text-white/20 pt-2 leading-relaxed">AI cannot cross these. Manual edits validate server-side.</p>
            </div>
        </div>
    </div>

</div>

@endsection
