@props(['class' => ''])

<button
    type="button"
    {{ $attributes->merge(['class' => 'app-theme-toggle '.$class]) }}
    data-app-theme
    aria-label="Switch color theme"
    title="Switch color theme"
>
    <svg class="app-theme-icon app-theme-moon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
    </svg>
    <svg class="app-theme-icon app-theme-sun" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>
    </svg>
    <span class="app-theme-label">Theme</span>
</button>
