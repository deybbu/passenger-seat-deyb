@props(['order'])

@php
    $subtotal = round((float) $order->items->sum(fn ($item) => (float) $item->price * (int) $item->quantity), 2);
    $formId = 'payment-'.$order->id;
@endphp

<details class="pos-payment-details">
    <summary>Take payment</summary>
    <form method="POST" action="{{ route('staff.pos.paid', $order) }}" class="pos-payment-form"
          data-pos-payment-form data-subtotal="{{ number_format($subtotal, 2, '.', '') }}">
        @csrf
        <label for="{{ $formId }}-discount-type">Discount type</label>
        <select id="{{ $formId }}-discount-type" name="discount_type" data-discount-type>
            <option value="">No discount</option>
            <option value="SC">Senior Citizen (20%)</option>
            <option value="PWD">PWD (20%)</option>
            <option value="CUSTOM">Promo / custom %</option>
        </select>

        <div data-custom-discount hidden>
            <label for="{{ $formId }}-discount-rate">Discount percent</label>
            <input id="{{ $formId }}-discount-rate" type="number" name="discount_rate"
                   min="0" max="100" step="0.01" value="0" inputmode="decimal" data-discount-rate>
        </div>

        <div class="pos-discount-id-fields" data-discount-id-fields hidden>
            <label for="{{ $formId }}-cardholder">Full name of cardholder</label>
            <input id="{{ $formId }}-cardholder" name="discount_cardholder_name" type="text" maxlength="120" autocomplete="off">
            <label for="{{ $formId }}-id-number">ID number (OSCA, PWD card or passport)</label>
            <input id="{{ $formId }}-id-number" name="discount_id_number" type="text" maxlength="80" autocomplete="off">
            <label for="{{ $formId }}-tin">TIN (optional)</label>
            <input id="{{ $formId }}-tin" name="discount_tin" type="text" maxlength="30" autocomplete="off">
            <p class="pos-hint">20% off the VAT-exclusive price. Cardholder name and ID number are required.</p>
        </div>

        <div class="pos-payment-summary" aria-live="polite">
            <div><span>Subtotal</span><strong data-summary-subtotal>₱{{ number_format($subtotal, 2) }}</strong></div>
            <div data-vat-exemption-row hidden><span>VAT exemption</span><strong data-vat-exemption></strong></div>
            <div><span>Discount</span><strong data-summary-discount>−₱0.00</strong></div>
            <div class="pos-muted"><span>VAT (12%, included)</span><strong data-summary-vat>₱{{ number_format($subtotal * 12 / 112, 2) }}</strong></div>
            <div class="pos-payment-total"><span>Total</span><strong data-summary-total>₱{{ number_format($subtotal, 2) }}</strong></div>
        </div>

        <label for="{{ $formId }}-method">Payment method</label>
        <select id="{{ $formId }}-method" name="payment_method" data-payment-method required>
            <option value="cash">Cash</option>
            <option value="gcash">GCash</option>
            <option value="card">Card</option>
        </select>

        <div data-cash-payment>
            <label for="{{ $formId }}-tendered">Cash tendered</label>
            <input id="{{ $formId }}-tendered" type="number" name="amount_tendered"
                   min="0" step="0.01" inputmode="decimal" data-cash-tendered required>
            <div class="pos-quick-tender">
                <button type="button" data-quick-tender="exact">Exact</button>
                <button type="button" data-quick-tender="100">₱100</button>
                <button type="button" data-quick-tender="500">₱500</button>
                <button type="button" data-quick-tender="1000">₱1,000</button>
            </div>
            <p class="pos-change">Change <strong data-cash-change>₱0.00</strong></p>
        </div>

        <p class="pos-payment-error" data-payment-error role="alert" hidden></p>
        <button type="submit" class="pos-button pos-button-primary">Confirm payment</button>
    </form>
</details>
