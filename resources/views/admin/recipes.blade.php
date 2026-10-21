@extends('layouts.admin')

@section('title', 'Recipe & food cost')
@section('page-title', 'Recipe & food cost')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header">
            <span class="admin-card-title">Recipe &amp; food cost</span>
            <form method="POST" action="{{ route('admin.recipes.target') }}" class="admin-inline-actions">
                @csrf
                <label class="admin-muted">Target %
                    <input type="number" name="recipe_target_percent" value="{{ $targetPercent }}" min="1" max="100" step="0.1" style="width:78px;">
                </label>
                <button type="submit" class="btn-outline">Save target</button>
            </form>
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
        <div class="admin-card-header"><span class="admin-card-title">Menu item cost summary</span></div>
        @if($items->isEmpty())
            <div class="admin-empty-state">
                <h3 class="admin-empty-title">No menu items yet</h3>
                <p class="admin-empty-desc">Add menu items before defining their ingredient recipes.</p>
                <a href="{{ route('admin.menu-management') }}" class="btn-red">Open menu management</a>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-data-table">
                    <thead><tr><th>Dish</th><th>Price</th><th>Cost / portion</th><th>Cost %</th><th>Margin</th><th>Suggested price</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($items as $item)
                            @php
                                $complete = $item->ingredients->isNotEmpty() && $item->ingredients->every(fn ($ingredient) => $ingredient->unit_cost !== null);
                                $cost = $complete ? $item->ingredients->sum(fn ($ingredient) => $ingredient->rawQuantityForPortion((float) $ingredient->pivot->quantity) * (float) $ingredient->unit_cost) : null;
                                $percent = $cost !== null && (float) $item->price > 0 ? ($cost / (float) $item->price) * 100 : null;
                            @endphp
                            <tr>
                                <td class="admin-strong">
                                    {{ $item->name }}
                                    <small class="admin-muted admin-block">{{ $item->category->name ?? '—' }}</small>
                                    @if($item->ingredients->isNotEmpty())
                                        <small class="admin-muted admin-block">
                                            @foreach($item->ingredients as $ingredient)
                                                <span>{{ $ingredient->name }} ({{ rtrim(rtrim(number_format((float) $ingredient->pivot->quantity, 3), '0'), '.') }} {{ $ingredient->unit }})</span>
                                                <form method="POST" action="{{ route('admin.recipes.line.destroy', [$item, $ingredient]) }}" style="display:inline;" onsubmit="return confirm('Remove this ingredient from the recipe?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-outline" style="padding:1px 5px;font-size:10px;">Remove</button>
                                                </form>{{ $loop->last ? '' : ', ' }}
                                            @endforeach
                                        </small>
                                    @endif
                                </td>
                                <td>₱{{ number_format((float) $item->price, 2) }}</td>
                                <td>{{ $cost === null ? '—' : '₱'.number_format($cost, 2) }}</td>
                                <td>{{ $percent === null ? '—' : number_format($percent, 1).'%' }}</td>
                                <td>{{ $cost === null ? '—' : '₱'.number_format((float) $item->price - $cost, 2) }}</td>
                                <td>{{ $cost === null ? '—' : '₱'.number_format($cost / max($targetPercent / 100, 0.001), 2) }}</td>
                                <td>
                                    @if($percent === null)
                                        <span class="badge-status badge-out-of-stock">Incomplete</span>
                                    @elseif($percent > 35)
                                        <span class="badge-status badge-out-of-stock">Over ceiling</span>
                                    @elseif($percent > $targetPercent)
                                        <span class="badge-status badge-low-stock">Watch</span>
                                    @else
                                        <span class="badge-status badge-in-stock">On target</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if($items->isEmpty())
                            <tr><td colspan="7" class="admin-muted">No menu items are available yet.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
