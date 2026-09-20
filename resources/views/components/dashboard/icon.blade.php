@php
    $paths = match ($name) {
        'building' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 21h16M6 21V4h8v17M14 8h4v13M9 8h2M9 12h2M9 16h2M16 12h1M16 16h1"/>',
        'location' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-1.8a4.2 4.2 0 0 0-4.2-4.2H7.2A4.2 4.2 0 0 0 3 19.2V21M9.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM16 4.4a3.5 3.5 0 0 1 0 6.8M21 21v-1.8a4.2 4.2 0 0 0-3.1-4.05"/>',
        'user' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 0 0-16 0M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/>',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="m12 3 7 3v5c0 4.8-3 8-7 10-4-2-7-5.2-7-10V6l7-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>',
        'briefcase' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16v11H4zM9 8V5h6v3M4 12h16M10 12v2h4v-2"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path stroke-linecap="round" d="M8 3v4M16 3v4M4 10h16"/>',
        'queue' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 6h11M8 12h11M8 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" d="M3 10h18"/>',
        'sparkles' => '<path stroke-linecap="round" stroke-linejoin="round" d="m12 3 1.4 4.1L17.5 8.5l-4.1 1.4L12 14l-1.4-4.1-4.1-1.4 4.1-1.4L12 3ZM19 14l.7 2.1 2.1.7-2.1.7L19 20l-.7-2.5-2.1-.7 2.1-.7L19 14Z"/>',
        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 20V10M12 20V5M19 20v-8"/>',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m19.2 13.2 1.2.9-1.8 3.1-1.4-.6a7.8 7.8 0 0 1-1.5.9L15.5 19h-3.6l-.2-1.5a7.8 7.8 0 0 1-1.5-.9l-1.4.6L7 14.1l1.2-.9A7.4 7.4 0 0 1 8.1 12c0-.4 0-.8.1-1.2L7 9.9l1.8-3.1 1.4.6a7.8 7.8 0 0 1 1.5-.9L11.9 5h3.6l.2 1.5a7.8 7.8 0 0 1 1.5.9l1.4-.6 1.8 3.1-1.2.9c.1.4.1.8.1 1.2s0 .8-.1 1.2Z"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/>',
        'sun' => '<circle cx="12" cy="12" r="3.5"/><path stroke-linecap="round" d="M12 2.5v2M12 19.5v2M4.8 4.8l1.4 1.4M17.8 17.8l1.4 1.4M2.5 12h2M19.5 12h2M4.8 19.2l1.4-1.4M17.8 6.2l1.4-1.4"/>',
        'moon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 14.5A8.4 8.4 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5Z"/>',
        'globe' => '<circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" d="M3.8 9h16.4M3.8 15h16.4M12 3.5c2.2 2.3 3.3 5.1 3.3 8.5s-1.1 6.2-3.3 8.5c-2.2-2.3-3.3-5.1-3.3-8.5S9.8 5.8 12 3.5Z"/>',
        'command' => '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path stroke-linecap="round" d="M9 8v8M15 8v8M8 9h8M8 15h8"/>',
        'arrow-right' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>',
        default => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
    };
@endphp

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
    {!! $paths !!}
</svg>
