<div class="pos-bill-alerts" data-pos-bill-alerts
     data-url="{{ route('staff.pos.bill-requests') }}"
     data-billing-url="{{ route('staff.pos.index') }}">
    <button type="button" class="pos-bill-alert-button" data-pos-bill-alert-toggle
            aria-label="Bill request notifications" aria-expanded="false">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 18h16l-2-2V10a6 6 0 0 0-12 0v6l-2 2z"/>
            <path d="M10 21h4"/>
        </svg>
        <span class="pos-bill-alert-badge" data-bill-request-count @if(($billRequestCount ?? 0) < 1) hidden @endif>{{ $billRequestCount ?? 0 }}</span>
    </button>
    <section class="pos-bill-alert-panel" data-pos-bill-alert-panel hidden aria-label="Bill request notifications">
        <header>
            <strong>Bill requests</strong>
            <button type="button" class="pos-bill-alert-mute" data-bill-alert-mute aria-pressed="false">Mute sound</button>
        </header>
        <div class="pos-bill-alert-list" data-pos-bill-alert-list>
            <p class="pos-bill-alert-empty">Checking for bill requests…</p>
        </div>
        <a class="pos-bill-alert-footer" href="{{ route('staff.pos.index') }}">Open Billing</a>
    </section>
</div>
