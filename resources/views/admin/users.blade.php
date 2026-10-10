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
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
                <tbody>
                    @foreach($users as $staff)
                        <tr><td class="admin-strong">{{ $staff->name }}</td><td>{{ $staff->email }}</td><td>{{ ucfirst($staff->role) }}</td><td>{{ $staff->created_at?->format('M j, Y') ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
