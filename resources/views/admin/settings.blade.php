@extends('layouts.admin')

@section('title', 'System settings')
@section('page-title', 'System settings')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Restaurant settings</span></div>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <div class="admin-form-grid">
                <label class="admin-field">Restaurant name
                    <input type="text" name="restaurant_name" value="{{ old('restaurant_name', $settings['restaurant_name']) }}" maxlength="80" required>
                </label>
                <label class="admin-field">Tagline
                    <input type="text" name="tagline" value="{{ old('tagline', $settings['tagline']) }}" maxlength="80" required>
                </label>
                <label class="admin-field">Opening time
                    <input type="time" name="open_time" value="{{ old('open_time', $settings['open_time']) }}" required>
                </label>
                <label class="admin-field">Closing time
                    <input type="time" name="close_time" value="{{ old('close_time', $settings['close_time']) }}" required>
                </label>
                <label class="admin-field">VAT %
                    <input type="number" name="vat_percent" min="0" max="100" step="0.01" value="{{ old('vat_percent', $databaseSettings['vat_percent'] ?? 12) }}" required>
                </label>
                <label class="admin-field">Service charge %
                    <input type="number" name="service_charge_percent" min="0" max="100" step="0.01" value="{{ old('service_charge_percent', $databaseSettings['service_charge_percent'] ?? 0) }}" required>
                </label>
                <label class="admin-field">Currency
                    <input name="currency" maxlength="10" value="{{ old('currency', $databaseSettings['currency'] ?? '₱') }}" required>
                </label>
            </div>
            <label class="admin-field" style="display:block;margin:16px 0;">Blackout dates
                <input name="blackout_dates" value="{{ old('blackout_dates', $databaseSettings['blackout_dates'] ?? '') }}" placeholder="YYYY-MM-DD, YYYY-MM-DD">
            </label>
            <fieldset class="admin-settings-options">
                <legend>Alerts</legend>
                <label><input type="checkbox" name="low_stock_alerts" value="1" @checked(old('low_stock_alerts', $settings['low_stock_alerts']))> Low-stock alerts</label>
                <label><input type="checkbox" name="order_alerts" value="1" @checked(old('order_alerts', $settings['order_alerts']))> New and in-progress order alerts</label>
                <label><input type="checkbox" name="qr_enabled" value="1" @checked(old('qr_enabled', ($databaseSettings['qr_enabled'] ?? '1') === '1'))> Enable customer QR code menus</label>
            </fieldset>
            <label class="admin-field" style="display:block;margin-bottom:16px;">Payment gateway key
                <input type="password" name="payment_gateway_key" maxlength="500" autocomplete="new-password" placeholder="Leave blank to keep the saved key">
            </label>
            <button type="submit" class="btn-red">Save settings</button>
        </form>
    </div>
@endsection
