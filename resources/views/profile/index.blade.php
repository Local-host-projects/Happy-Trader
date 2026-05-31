@extends('layouts.app')

@section('title', 'Settings')
@section('subtitle', 'Account & API key management')

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 max-w-3xl">

    {{-- Update API Key --}}
    <div class="glass border border-white/[0.07] rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-white/[0.06]">
            <p class="text-xs font-mono text-white/40 tracking-widest uppercase">API Key</p>
            <p class="text-[10px] font-mono text-white/20 mt-1">Update if your Deriv PAT has expired</p>
        </div>
        <div class="p-5">
            @if($errors->any())
            <div class="flex items-start gap-3 px-4 py-3 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-400 text-xs font-mono mb-4 animate-fade-in">
                <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0"></i>
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('settings.api-key') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[9px] font-mono text-white/30 tracking-widest uppercase mb-2">
                        New Deriv API Key
                    </label>
                    <div class="relative">
                        <input type="password" name="deriv_api_key" id="new-api-key"
                            placeholder="sk-..."
                            required
                            class="w-full glass border border-white/[0.08] rounded-xl px-4 py-3 pr-11 text-sm font-mono text-white/80 placeholder-white/20 outline-none transition-all focus:border-white/30 focus:bg-white/[0.06] bg-transparent">
                        <button type="button" onclick="toggleKey()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-white/25 hover:text-white/60 transition-colors text-sm">
                            <i class="fa-solid fa-eye" id="key-eye"></i>
                        </button>
                    </div>
                    <p class="text-[10px] font-mono text-white/20 mt-1.5 leading-relaxed">
                        app.deriv.com → Account Settings → API Token<br>
                        Required scopes: <span class="text-white/35">trade + account_manage</span>
                    </p>
                </div>

                <div class="p-3 rounded-xl border border-amber-500/20 bg-amber-500/8 text-amber-400/80 text-[10px] font-mono leading-relaxed">
                    <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
                    Bot will be paused automatically on key update. Reactivate from the dashboard.
                </div>

                <button type="submit"
                    class="w-full py-3 bg-white text-black text-sm font-semibold rounded-xl hover:bg-white/90 active:scale-[0.99] transition-all">
                    Update API Key
                </button>
            </form>
        </div>
    </div>

    {{-- Account info --}}
    <div class="glass border border-white/[0.07] rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-white/[0.06]">
            <p class="text-xs font-mono text-white/40 tracking-widest uppercase">Linked Account</p>
            <p class="text-[10px] font-mono text-white/20 mt-1">Currently active Deriv account</p>
        </div>
        <div class="p-5 space-y-4">
            <div class="flex items-center justify-between py-2 border-b border-white/[0.04]">
                <span class="text-xs text-white/30">Account ID</span>
                <span class="text-xs font-mono text-white/60">{{ $user->deriv_account_id ?? 'Not set' }}</span>
            </div>
            <div class="flex items-center justify-between py-2 border-b border-white/[0.04]">
                <span class="text-xs text-white/30">Bot status</span>
                <span class="text-[10px] font-mono px-2 py-0.5 rounded-full border
                    {{ $user->is_active ? 'border-emerald-500/30 text-emerald-400 bg-emerald-500/10' : 'border-white/10 text-white/30' }}">
                    {{ $user->is_active ? 'LIVE' : 'PAUSED' }}
                </span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-xs text-white/30">Name</span>
                <span class="text-xs text-white/60">{{ $user->name }}</span>
            </div>

            <div class="pt-2">
                <p class="text-[9px] font-mono text-white/20 tracking-widest uppercase mb-3">Switch Account</p>
                <div id="accounts-list" class="space-y-2">
                    <div class="text-[10px] font-mono text-white/20 text-center py-4">
                        <i class="fa-solid fa-spinner animate-spin mr-2"></i>Fetching accounts...
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
async function loadAccounts() {
    try {
        const accounts = await fetch('{{ route("data.accounts") }}').then(r => r.json());

        if (accounts.error) {
            document.getElementById('accounts-list').innerHTML =
                `<p class="text-[10px] font-mono text-rose-400/60 text-center py-4">${accounts.error}</p>`;
            return;
        }

        document.getElementById('accounts-list').innerHTML = accounts.map(a => `
            <button onclick="switchAccount('${a.account_id}')"
                class="w-full flex items-center justify-between px-4 py-3 rounded-xl border transition-all text-left
                       ${a.active
                           ? 'border-white/20 bg-white/[0.06] cursor-default'
                           : 'border-white/[0.06] hover:border-white/20 hover:bg-white/[0.04]'}">
                <div>
                    <p class="text-xs font-mono ${a.active ? 'text-white' : 'text-white/50'}">${a.account_id}</p>
                    <p class="text-[9px] font-mono text-white/25 mt-0.5 uppercase tracking-widest">${a.account_type} · ${a.currency}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-mono ${a.active ? 'text-white' : 'text-white/40'}">$${a.balance}</p>
                    ${a.active
                        ? '<span class="text-[9px] font-mono text-emerald-400/70 tracking-widest">ACTIVE</span>'
                        : '<span class="text-[9px] font-mono text-white/20 tracking-widest">SWITCH</span>'}
                </div>
            </button>
        `).join('');

    } catch(e) {
        document.getElementById('accounts-list').innerHTML =
            `<p class="text-[10px] font-mono text-rose-400/60 text-center py-4">Failed to load accounts.</p>`;
    }
}

async function switchAccount(accountId) {
    if (!confirm(`Switch bot to account ${accountId}? The bot will be paused.`)) return;

    try {
        const res  = await fetch('{{ route("settings.switch-account") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json' },
            body: JSON.stringify({ deriv_account_id: accountId }),
        });
        const data = await res.json();

        if (data.error) { alert(data.error); return; }

        // Reload to reflect changes
        window.location.reload();

    } catch(e) { alert('Switch failed. Try again.'); }
}

function toggleKey() {
    const input = document.getElementById('new-api-key');
    const icon  = document.getElementById('key-eye');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
}

loadAccounts();
</script>
@endpush