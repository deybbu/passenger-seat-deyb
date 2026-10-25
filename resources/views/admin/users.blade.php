@extends('layouts.admin')

@section('title', 'User & role management')
@section('page-title', 'User & role management')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Create staff account</span></div>
        <form method="POST" action="{{ route('admin.users.store') }}" class="admin-form-grid">
            @csrf
            <label class="admin-field">Name
                <input type="text" name="name" maxlength="120" required>
            </label>
            <label class="admin-field">Email
                <input type="email" name="email" maxlength="150" required>
            </label>
            <label class="admin-field">Role
                <select name="role" required>
                    @foreach(\App\Models\User::ROLES as $role)
                        <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">Temporary password
                <input type="password" name="password" minlength="8" maxlength="80" autocomplete="new-password" required>
            </label>
            <div><button type="submit" class="btn-red">Create account</button></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Staff accounts</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
                <tbody>
                    @foreach($users as $staff)
                        <tr><td class="admin-strong">{{ $staff->name }}</td><td>{{ $staff->email }}</td><td>{{ $staff->assignedRole->name ?? ucfirst($staff->role) }}</td><td><span class="badge-status {{ $staff->is_active ? 'badge-in-stock' : 'badge-out-of-stock' }}">{{ $staff->is_active ? 'Active' : 'Deactivated' }}</span></td><td>{{ $staff->created_at?->format('M j, Y') ?? '—' }}</td><td><form method="POST" action="{{ route('admin.users.toggle', $staff) }}">@csrf @method('PATCH')<button class="btn-outline" type="submit">{{ $staff->is_active ? 'Deactivate' : 'Reactivate' }}</button></form></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card" style="margin-top:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Role permissions (view access)</span></div>
        <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Module</th>@foreach($roles as $role)<th>{{ $role->name }}</th>@endforeach</tr></thead><tbody>
            @foreach(['orders_pos' => 'Orders & POS', 'menu_recipes' => 'Menu & recipes', 'inventory_receiving' => 'Inventory & receiving', 'waste' => 'Waste', 'reservations' => 'Reservations', 'reports' => 'Reports', 'settings' => 'Settings'] as $module => $label)
                <tr><td>{{ $label }}</td>@foreach($roles as $role)@php($permission = $role->permissions->firstWhere('module_key', $module))<td><form method="POST" action="{{ route('admin.roles.permission', $role) }}">@csrf @method('PATCH')<input type="hidden" name="module_key" value="{{ $module }}"><input type="hidden" name="can_view" value="{{ $permission?->can_view ? 0 : 1 }}"><button class="btn-outline" type="submit" {{ $role->key === 'super_admin' ? 'disabled' : '' }}>{{ $permission?->can_view ? 'Allowed' : 'Blocked' }}</button></form></td>@endforeach</tr>
            @endforeach
        </tbody></table></div>
    </div>
@endsection
