@extends('layouts.pos-register')

@section('title', 'Passenger Seat POS · Register')
@section('pos-tab', 'register')

@section('content')
    <form id="register-form" method="POST" action="{{ route('staff.orders.store') }}"
          data-menu-items="{{ json_encode($menuItems) }}" data-categories="{{ json_encode($categories) }}"
          data-old-items="{{ json_encode(old('items', [])) }}"
          data-old-type="{{ old('type', 'dine_in') }}" data-old-payment-method="{{ old('payment_method', 'cash') }}">
        @csrf
        <div id="item-inputs"></div>
        <section id="v-pos" class="pos">
            <div>
                <div class="bar"><input id="q" placeholder="Search menu items" aria-label="Search menu items"><button class="btn" id="phBtn" type="button">Edit items</button><button class="btn" id="cuBtn" type="button">+ Custom Item</button><input type="file" id="phFile" accept="image/*" class="hide" aria-label="Upload photo"></div>
                <div class="cats" id="cats"></div>
                <div class="menu" id="menu"></div>
            </div>
            <div class="card cart">
                <h2>Current order</h2><div id="heldBox" class="hide"></div>
                <div class="seg" id="otype"><button class="btn on" type="button" data-o="Dine-in">Dine-in</button><button class="btn" type="button" data-o="Take-out">Take-out</button></div>
                <input type="hidden" id="order-type" name="type" value="dine_in">
                <div class="bar" style="margin:0 0 6px"><input id="tbl" name="table_number" value="{{ old('table_number') }}" placeholder="Table no." aria-label="Table number" style="max-width:110px"><input id="cust" name="customer_name" value="{{ old('customer_name') }}" placeholder="Customer name (optional)" aria-label="Customer"></div>
                <div id="lines"><p class="mute">Tap a menu item to add it.</p></div>
                <div style="margin-top:10px"><label class="mute" style="display:block">Discount type<select id="dtype" name="discount_type" style="width:100%;margin-top:4px"><option value="" @selected(old('discount_type') === null)>No discount</option><option value="SC" @selected(old('discount_type') === 'SC')>Senior Citizen (20%)</option><option value="PWD" @selected(old('discount_type') === 'PWD')>PWD (20%)</option><option value="CUSTOM" @selected(old('discount_type') === 'CUSTOM')>Promo / custom %</option></select></label>
                    <div id="dcust" class="hide" style="margin-top:6px"><input id="disc" name="discount_rate" type="number" min="0" max="100" step="0.01" value="{{ old('discount_rate', 0) }}" placeholder="Discount %" aria-label="Discount percent" style="width:100%"></div>
                    <div id="did" class="hide" style="display:grid;gap:6px;margin-top:6px"><input id="idName" name="discount_cardholder_name" value="{{ old('discount_cardholder_name') }}" placeholder="Full name of cardholder" aria-label="Full name of cardholder" autocomplete="off"><input id="idNo" name="discount_id_number" value="{{ old('discount_id_number') }}" placeholder="ID number (OSCA, PWD card or passport)" aria-label="ID number" autocomplete="off"><input id="tin" name="discount_tin" value="{{ old('discount_tin') }}" placeholder="TIN (optional)" aria-label="Tax Identification Number" autocomplete="off"><div id="dhint" class="mute" style="font-size:12px"></div></div></div>
                <div class="tot"><div><span>Subtotal</span><span id="tSub"></span></div><div><span>Discount</span><span id="tDisc"></span></div><div id="rEx" class="hide"><span>VAT exemption</span><span id="tEx"></span></div><div class="mute"><span>VAT (12%, included)</span><span id="tVat"></span></div><div class="g"><span>Total</span><span id="tTot"></span></div></div>
                <div class="seg" id="pay"><button class="btn on" type="button" data-p="Cash">Cash</button><button class="btn" type="button" data-p="GCash">GCash</button><button class="btn" type="button" data-p="Card">Card</button></div>
                <input type="hidden" id="payment-method" name="payment_method" value="cash">
                <div id="cashBox"><input id="tend" name="amount_tendered" value="{{ old('amount_tendered') }}" type="number" min="0" step="0.01" placeholder="Cash tendered" aria-label="Cash tendered" style="width:100%">
                    <div class="seg" id="quick"></div><div class="tot"><div><span>Change</span><b id="chg">₱0.00</b></div></div></div>
                <div id="referenceBox" class="hide"><input id="reference" name="reference_number" value="{{ old('reference_number') }}" type="text" maxlength="100" placeholder="GCash / card reference" aria-label="GCash or card reference"></div>
                <div class="seg"><button class="btn" id="clear" type="button">Clear</button><button class="btn" id="hold" type="button" disabled>Hold</button><button class="btn red" id="place" type="submit" disabled>Place order</button></div>
            </div>
        </section>
    </form>
@endsection

@push('scripts')
    @vite('resources/js/pos-register.js')
@endpush
