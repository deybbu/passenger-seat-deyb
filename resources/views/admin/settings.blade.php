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
            </div>
            <fieldset class="admin-settings-options">
                <legend>Alerts</legend>
                <label><input type="checkbox" name="low_stock_alerts" value="1" @checked(old('low_stock_alerts', $settings['low_stock_alerts']))> Low-stock alerts</label>
                <label><input type="checkbox" name="order_alerts" value="1" @checked(old('order_alerts', $settings['order_alerts']))> New and in-progress order alerts</label>
            </fieldset>
            <button type="submit" class="btn-red">Save settings</button>
        </form>
    </div>
@endsection
