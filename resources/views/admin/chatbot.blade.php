@extends('layouts.admin')

@section('title', 'FAQ and chatbot management')
@section('page-title', 'FAQ and chatbot management')

@section('content')
    <div class="admin-two-column-grid">
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">FAQ</span></div>
            @forelse($faqs as $faq)<p><strong>{{ $faq->question }}</strong><br>{{ $faq->answer }}</p>@empty<p class="admin-muted">No FAQs yet.</p>@endforelse
            <form method="POST" action="{{ route('admin.chatbot.faq.store') }}" class="admin-form-grid">
                @csrf
                <label class="admin-field">Question<input name="question" required maxlength="500"></label>
                <label class="admin-field">Answer<textarea name="answer" rows="2" required maxlength="5000"></textarea></label>
                <button class="btn-red" type="submit">Add Q&amp;A</button>
            </form>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Unanswered questions</span></div>
            @forelse($unanswered as $question)
                <form method="POST" action="{{ route('admin.chatbot.unanswered.answer', $question) }}" style="margin-bottom:12px;">
                    @csrf
                    <strong>{{ $question->question }}</strong>
                    <div class="admin-inline-actions"><input name="answer" placeholder="Type the answer" required style="flex:1;"><button class="btn-outline" type="submit">Teach</button></div>
                </form>
            @empty
                <p class="admin-muted">All caught up.</p>
            @endforelse
        </div>
    </div>
    <div class="admin-two-column-grid" style="margin-top:16px;">
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Persona &amp; guardrails</span></div>
            <form method="POST" action="{{ route('admin.chatbot.settings') }}">
                @csrf
                <label class="admin-field">Tone<select name="persona"><option @selected($settings['persona'] === 'Warm & professional')>Warm &amp; professional</option><option @selected($settings['persona'] === 'Trendy & casual')>Trendy &amp; casual</option></select></label>
                <label><input type="checkbox" name="guardrail_calendar" value="1" @checked($settings['guardrail_calendar'])> Never promise open dates without checking the calendar</label>
                <button class="btn-outline" type="submit">Save chatbot settings</button>
            </form>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Knowledge sources</span></div>
            @forelse($sources as $source)<p><strong>{{ $source->title }}</strong><br><span class="admin-muted">{{ \Illuminate\Support\Str::limit($source->content, 160) }}</span></p>@empty<p class="admin-muted">No knowledge sources yet.</p>@endforelse
            <form method="POST" action="{{ route('admin.chatbot.knowledge') }}" class="admin-form-grid">
                @csrf
                <label class="admin-field">Source title<input name="title" required maxlength="150"></label>
                <label class="admin-field">Content<textarea name="body" required maxlength="5000" rows="2"></textarea></label>
                <button class="btn-outline" type="submit">Add source</button>
            </form>
        </div>
    </div>
@endsection
