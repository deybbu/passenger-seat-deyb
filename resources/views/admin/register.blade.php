@extends('layouts.admin')

@section('title', 'Register (POS) · Passenger Seat')
@section('page-title', 'Register (POS)')

@section('content')
    <form id="register-form" class="admin-register"
          method="POST" action="{{ route('admin.pos.store') }}"
          data-admin-register="true"
          data-menu-items="{{ json_encode($menuItems) }}"
          data-categories="{{ json_encode($categories) }}"
          data-held-orders="{{ json_encode($heldOrders) }}"
          data-old-items="{{ json_encode(old('items', [])) }}"
          data-old-type="{{ old('type', 'dine_in') }}"
          data-old-payment-method="{{ old('payment_method', 'cash') }}"
          data-old-held-id="{{ old('held_order_id', '') }}"
          data-hold-url="{{ route('admin.pos.hold') }}"
          data-resume-url="{{ route('admin.pos.held.resume', ['order' => '__ID__']) }}"
          data-discard-url="{{ route('admin.pos.held.discard', ['order' => '__ID__']) }}">
        @csrf
        <input type="hidden" name="held_order_id" value="{{ old('held_order_id', '') }}" data-held-order-id>
        <input type="hidden" name="type" id="order-type" value="{{ old('type', 'dine_in') }}">
        <input type="hidden" name="payment_method" id="payment-method" value="{{ old('payment_method', 'cash') }}">
        <div id="item-inputs"></div>

        <section id="v-pos" class="pos">
            <div class="admin-register-menu">
                <div class="bar">
                    <input id="q" type="search" placeholder="Search menu items" aria-label="Search menu items">
                </div>
                <div class="cats" id="cats"></div>
                <div class="menu" id="menu"></div>
            </div>

            <div class="card cart">
                <h2>Current order</h2>
                <div id="heldBox" class="hide"></div>
                <div class="seg" id="otype">
                    <button class="btn on" type="button" data-o="Dine-in">Dine-in</button>
                    <button class="btn" type="button" data-o="Take-out">Take-out</button>
                </div>
                <div class="bar admin-register-customer">
                    <input id="tbl" name="table_number" value="{{ old('table_number') }}" placeholder="Table no."
                           aria-label="Table number" list="restaurant-tables">
                    <datalist id="restaurant-tables">
                        @foreach ($tables as $table)
                            <option value="{{ $table->table_number }}">{{ $table->name }}</option>
                        @endforeach
                    </datalist>
                    <input id="cust" name="customer_name" value="{{ old('customer_name') }}"
                           placeholder="Customer name (optional)" aria-label="Customer">
                </div>
                <div id="lines"><p class="mute">Tap a menu item to add it.</p></div>

                <div class="admin-register-discount">
                    <label for="dtype">Discount type</label>
                    <select id="dtype" name="discount_type">
                        <option value="" @selected(old('discount_type') === null)>No discount</option>
                        <option value="SC" @selected(old('discount_type') === 'SC')>Senior Citizen (20%)</option>
                        <option value="PWD" @selected(old('discount_type') === 'PWD')>PWD (20%)</option>
                        <option value="CUSTOM" @selected(old('discount_type') === 'CUSTOM')>Promo / custom %</option>
                    </select>
                    <div id="dcust" class="hide">
                        <label for="disc">Discount percent</label>
                        <input id="disc" name="discount_rate" type="number" min="0" max="100" step="0.01"
                               value="{{ old('discount_rate', 0) }}" placeholder="Discount %" aria-label="Discount percent">
                    </div>
                    <div id="did" class="hide">
                        <input id="idName" name="discount_cardholder_name" value="{{ old('discount_cardholder_name') }}"
                               placeholder="Full name of cardholder" aria-label="Full name of cardholder" autocomplete="off">
                        <input id="idNo" name="discount_id_number" value="{{ old('discount_id_number') }}"
                               placeholder="ID number (OSCA, PWD card or passport)" aria-label="ID number" autocomplete="off">
                        <input id="tin" name="discount_tin" value="{{ old('discount_tin') }}"
                               placeholder="TIN (optional)" aria-label="Tax Identification Number" autocomplete="off">
                        <div id="dhint" class="mute"></div>
                    </div>
                </div>

                <div class="tot">
                    <div><span>Subtotal</span><span id="tSub"></span></div>
                    <div><span>Discount</span><span id="tDisc"></span></div>
                    <div id="rEx" class="hide"><span>VAT exemption</span><span id="tEx"></span></div>
                    <div class="mute"><span>VAT (12%, included)</span><span id="tVat"></span></div>
                    <div class="g"><span>Total</span><span id="tTot"></span></div>
                </div>

                <div class="seg" id="pay">
                    <button class="btn on" type="button" data-p="Cash">Cash</button>
                    <button class="btn" type="button" data-p="GCash">GCash</button>
                    <button class="btn" type="button" data-p="Card">Card</button>
                </div>
                <div id="cashBox">
                    <label for="tend">Cash tendered</label>
                    <input id="tend" name="amount_tendered" value="{{ old('amount_tendered') }}" type="number"
                           min="0" step="0.01" placeholder="Cash tendered" aria-label="Cash tendered">
                    <div class="seg" id="quick"></div>
                    <div class="tot"><div><span>Change</span><b id="chg">₱0.00</b></div></div>
                </div>
                <div class="seg admin-register-actions">
                    <button class="btn" id="clear" type="button">Clear</button>
                    <button class="btn" id="hold" type="button" disabled>Hold</button>
                    <button class="btn red" id="place" type="submit" disabled>Place order</button>
                </div>
            </div>
        </section>
    </form>
    <div id="toast" class="admin-register-toast" role="status" aria-live="polite"></div>
@endsection
