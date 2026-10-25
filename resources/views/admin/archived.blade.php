@extends('layouts.admin')

@section('title', 'Archived products')
@section('page-title', 'Archived products')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Archived products <span class="badge-status badge-low-stock">{{ $items->count() }}</span></span>
            <span class="admin-muted">Restore archived products to make them available in menu management and POS.</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Archived</th><th></th></tr></thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td class="admin-strong">{{ $item->name }}<small class="admin-muted admin-block">{{ $item->description ?: 'No description added' }}</small></td>
                            <td>{{ $item->category->name ?? '—' }}</td>
                            <td>₱{{ number_format((float) $item->price, 2) }}</td>
                            <td>{{ $item->archived_at?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.menu-management.restore', $item) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn-outline" type="submit">Restore</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="admin-muted">No archived products match your search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
