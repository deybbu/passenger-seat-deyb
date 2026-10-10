@extends('layouts.admin')

@section('title', 'Recipe & food cost')
@section('page-title', 'Recipe & food cost')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header">
            <span class="admin-card-title">Add recipe ingredient</span>
        </div>
        <form method="POST" action="{{ route('admin.recipes.store') }}" class="admin-form-grid admin-recipe-form">
            @csrf
            <label class="admin-field">Menu item
                <select name="menu_item_id" required>
                    <option value="">Choose a menu item</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">Ingredient
                <select name="ingredient_id" required>
                    <option value="">Choose an ingredient</option>
                    @foreach($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit }})</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">Quantity per serving
                <input type="number" name="quantity" min="0.001" step="0.001" required>
            </label>
            <div class="admin-recipe-form-action"><button type="submit" class="btn-red">Save recipe line</button></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Recipes & ingredient costs</span></div>
        @if($items->isEmpty())
            <div class="admin-empty-state">
                <h3 class="admin-empty-title">No menu items yet</h3>
                <p class="admin-empty-desc">Add menu items before defining their ingredient recipes.</p>
                <a href="{{ route('admin.menu-management') }}" class="btn-red">Open menu management</a>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-data-table">
                    <thead><tr><th>Menu item</th><th>Category</th><th>Ingredient</th><th>Quantity per serving</th></tr></thead>
                    <tbody>
                        @php($hasLines = false)
                        @foreach($items as $item)
                            @forelse($item->ingredients as $ingredient)
                                @php($hasLines = true)
                                <tr>
                                    <td class="admin-strong">{{ $item->name }}</td>
                                    <td>{{ $item->category->name ?? '—' }}</td>
                                    <td>{{ $ingredient->name }}</td>
                                    <td>{{ rtrim(rtrim(number_format((float) $ingredient->pivot->quantity, 3), '0'), '.') }} {{ $ingredient->unit }}</td>
                                </tr>
                            @empty
                            @endforelse
                        @endforeach
                        @unless($hasLines)
                            <tr><td colspan="4" class="admin-muted">No recipe lines have been added yet.</td></tr>
                        @endunless
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
