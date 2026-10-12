@extends('layouts.pos-register')

@section('title', 'Passenger Seat POS · Table QR')
@section('pos-tab', 'table-qr')

@section('content')
    @php
        $tableOptions = $tableSessions->map(fn ($session) => [
            'number' => $session->table_number,
            'token' => $session->public_token,
        ])->values();
    @endphp

    <div
        id="qr-order-root"
        data-items="{{ json_encode($menuItems) }}"
        data-tables="{{ json_encode($tableOptions) }}"
        data-submission-token="{{ $submissionToken }}"
        data-place-url="{{ route('customer.qr.place') }}"
    >
        <div class="pos">
            <div>
                <h2>Table ordering (customer view)</h2>
                <p class="mute">
                    Each table's QR code opens this screen with its table number filled in.
                    Orders go straight to the Orders list and the table's bill.
                </p>
                <div class="menu" id="qMenu"></div>
            </div>
            <div class="card cart">
                <h2>Your order</h2>
                <label class="mute" for="qTbl">Table
                    <select id="qTbl" style="width:100%;margin:4px 0">
                        <option value="">Choose an open table</option>
                    </select>
                </label>
                <div id="qLines"></div>
                <div class="tot"><div class="g"><span>Total</span><span id="qTot">₱0.00</span></div></div>
                @if ($tableSessions->isEmpty())
                    <p class="mute">No open table sessions. Open a table before sending an order.</p>
                    <a class="btn" href="{{ route('staff.tables.index') }}">Manage table sessions</a>
                @endif
                <button class="btn red" id="qSend" type="button" style="width:100%;margin-top:8px" disabled>Send order to kitchen</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/pos-table-qr.js')
@endpush
