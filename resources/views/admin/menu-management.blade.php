@extends('layouts.admin')

@section('title', 'Menu management')
@section('page-title', 'Menu management')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header">
            <span class="admin-card-title">Add menu item</span>
        </div>
        <form method="POST" action="{{ route('admin.menu-management.store') }}" class="admin-form-grid">
            @csrf
            <label class="admin-field">Name
                <input type="text" name="name" required maxlength="150">
            </label>
            <label class="admin-field">Price
                <input type="number" name="price" min="0" step="0.01" required>
            </label>
            <label class="admin-field">Category
                <select name="menu_category_id">
                    <option value="">Default</option>
                    @foreach($categories ?? [] as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field" style="grid-column:1/-1;">Description
                <textarea name="description" rows="2"></textarea>
            </label>
            <div><button type="submit" class="btn-red">Add new</button></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Menu items</span>
        </div>

        @if($items->isEmpty())
            <div class="admin-empty-state">
                <div class="admin-empty-icon-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2v6a3 3 0 0 1-3 3 3 3 0 0 1-3-3V2"/><path d="M15 11v11"/><path d="M5 2v20"/></svg>
                </div>
                <h3 class="admin-empty-title">Menu management is empty</h3>
                <p class="admin-empty-desc">This module has no records yet. Add the first one to get started.</p>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Availability</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td class="admin-strong">{{ $item->name }}</td>
                                <td>{{ $item->category->name ?? '—' }}</td>
                                <td>₱{{ number_format((float) $item->price, 0) }}</td>
                                <td>
                                    <span class="badge-status {{ $item->is_available ? 'badge-in-stock' : 'badge-out-of-stock' }}">
                                        {{ $item->is_available ? 'Available' : 'Unavailable' }}
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.menu-management.toggle', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-outline" style="padding:5px 10px;font-size:11px;">
                                            {{ $item->is_available ? 'Mark unavailable' : 'Mark available' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.menu-management.destroy', $item) }}" style="margin-top:6px;" onsubmit="return confirm('Permanently delete this menu item? Previous order records will remain intact.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-outline" style="padding:5px 10px;font-size:11px;color:#b91c1c;border-color:#fecaca;">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
