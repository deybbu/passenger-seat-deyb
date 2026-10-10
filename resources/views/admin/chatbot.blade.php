@extends('layouts.admin')

@section('title', 'AI chatbot & knowledge base')
@section('page-title', 'AI chatbot & knowledge base')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Operations assistant</span></div>
        <div class="admin-chat-log" aria-live="polite">
            @forelse($messages as $message)
                <div class="admin-chat-bubble {{ $message['role'] }}">{{ $message['text'] }}</div>
            @empty
                <p class="admin-muted">Ask about opening hours, inventory, or order flow.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('admin.chatbot.message') }}" class="admin-form-grid">
            @csrf
            <label class="admin-field" style="grid-column:1/-1;">Message
                <input type="text" name="message" maxlength="500" required placeholder="Ask a question about restaurant operations">
            </label>
            <div><button type="submit" class="btn-red">Send message</button></div>
        </form>
    </div>

    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Add knowledge article</span></div>
        <form method="POST" action="{{ route('admin.chatbot.knowledge') }}" class="admin-form-grid">
            @csrf
            <label class="admin-field">Title
                <input type="text" name="title" maxlength="120" required>
            </label>
            <label class="admin-field" style="grid-column:span 2;">Answer
                <textarea name="body" maxlength="1000" rows="3" required></textarea>
            </label>
            <div><button type="submit" class="btn-outline">Add article</button></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Knowledge base</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Topic</th><th>Answer</th></tr></thead>
                <tbody>
                    @foreach($articles as $article)
                        <tr><td class="admin-strong">{{ $article['title'] }}</td><td>{{ $article['body'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
