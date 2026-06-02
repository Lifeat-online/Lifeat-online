@props(['name'])

@php
    $class = $attributes->get('class') ?: 'w-5 h-5';
    $common = [
        'class' => $class,
        'xmlns' => 'http://www.w3.org/2000/svg',
        'fill' => 'none',
        'viewBox' => '0 0 24 24',
        'stroke' => 'currentColor',
        'stroke-width' => '1.8',
        'aria-hidden' => 'true',
        'focusable' => 'false',
    ];
@endphp

@switch($name)
    @case('menu')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        @break
    @case('x')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
        </svg>
        @break
    @case('arrow-right')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h13.5m0 0l-5.25-5.25M18 12l-5.25 5.25" />
        </svg>
        @break
    @case('search')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 3.75a6.75 6.75 0 105.15 11.1l3.6 3.6 1.5-1.5-3.6-3.6a6.75 6.75 0 00-6.65-9.6z" />
        </svg>
        @break
    @case('calendar')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75V6M16.5 3.75V6M4.5 9h15M5.25 6h13.5A1.5 1.5 0 0120.25 7.5v12A1.5 1.5 0 0118.75 21H5.25A1.5 1.5 0 013.75 19.5v-12A1.5 1.5 0 015.25 6z" />
        </svg>
        @break
    @case('building')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M6 21V4.5A1.5 1.5 0 017.5 3h9A1.5 1.5 0 0118 4.5V21M9 7.5h.01M12 7.5h.01M15 7.5h.01M9 11.25h.01M12 11.25h.01M15 11.25h.01M9 15h.01M12 15h.01M15 15h.01" />
        </svg>
        @break
    @case('sparkles')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3l1.4 3.9L10 8.3 6.4 9.7 5 13.6 3.6 9.7 0 8.3l3.6-1.4L5 3z" transform="translate(3 3)" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3l.9 2.5L8.5 6.4 5.9 7.3 5 9.8 4.1 7.3 1.5 6.4l2.6-.9L5 3z" transform="translate(13 9)" />
        </svg>
        @break
    @case('ticket')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 8.25A2.25 2.25 0 016.75 6h10.5a2.25 2.25 0 012.25 2.25v1.2a2.55 2.55 0 000 5.1v1.2A2.25 2.25 0 0117.25 18H6.75a2.25 2.25 0 01-2.25-2.25v-1.2a2.55 2.55 0 000-5.1v-1.2zM9 8.25v7.5" />
        </svg>
        @break
    @case('newspaper')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h12.75A2.25 2.25 0 0119.5 7.5v10.25A1.25 1.25 0 0118.25 19H5.75A1.25 1.25 0 014.5 17.75V5.25zM7.5 8.25h5.25M7.5 11.25h9M7.5 14.25h6M16.5 5.25v12.5" />
        </svg>
        @break
    @case('tag')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.75v5.1c0 .6.24 1.18.66 1.6l6.9 6.9a2.25 2.25 0 003.18 0l3.1-3.1a2.25 2.25 0 000-3.18l-6.9-6.9a2.25 2.25 0 00-1.6-.67H4.5zM8.25 8.25h.01" />
        </svg>
        @break
    @case('megaphone')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 13.5h2.25l8.25 4.5V6L6.75 10.5H4.5a1.5 1.5 0 00-1.5 1.5v0a1.5 1.5 0 001.5 1.5zM7.5 13.5l1.2 4.2a1.5 1.5 0 001.44 1.08h1.11M18 9.5a4 4 0 010 5" />
        </svg>
        @break
    @case('map-pin')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s6.75-4.95 6.75-11.25a6.75 6.75 0 10-13.5 0C5.25 16.05 12 21 12 21zM12 12.25a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
        </svg>
        @break
    @case('volume')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 9.75h3L12 6v12l-4.5-3.75h-3v-4.5zM15.75 8.25a5.25 5.25 0 010 7.5M18 6a8.25 8.25 0 010 12" />
        </svg>
        @break
    @case('volume-off')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 9.75h3L12 6v12l-4.5-3.75h-3v-4.5zM16.5 9.5l4 4M20.5 9.5l-4 4" />
        </svg>
        @break
    @case('trash')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5h13.5M9 7.5V5.25h6V7.5M7.5 7.5l.75 12h7.5l.75-12M10.5 10.5v6M13.5 10.5v6" />
        </svg>
        @break
    @case('thumb-up')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 10.5v9M4.5 10.5h3v9h-3v-9zM7.5 10.5l4.2-6.3A1.5 1.5 0 0114.4 5.4l-.65 3.1h3.8a2.25 2.25 0 012.2 2.72l-1.2 5.65A3 3 0 0115.6 19.5H7.5" />
        </svg>
        @break
    @case('thumb-down')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 13.5v-9M4.5 4.5h3v9h-3v-9zM7.5 13.5l4.2 6.3a1.5 1.5 0 002.7-1.2l-.65-3.1h3.8a2.25 2.25 0 002.2-2.72l-1.2-5.65A3 3 0 0015.6 4.5H7.5" />
        </svg>
        @break
    @case('external-link')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6H6.75A2.25 2.25 0 004.5 8.25v9A2.25 2.25 0 006.75 19.5h9A2.25 2.25 0 0018 17.25V13.5M13.5 4.5h6v6M19.5 4.5l-9 9" />
        </svg>
        @break
    @case('phone')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.25 4.5l2.1-.75 2.1 4.5-1.55 1.05a12.5 12.5 0 004.8 4.8l1.05-1.55 4.5 2.1-.75 2.1a2.25 2.25 0 01-2.45 1.48C10.5 17.2 6.8 13.5 5.77 6.95A2.25 2.25 0 017.25 4.5z" />
        </svg>
        @break
    @case('heart')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.75c0 5.25-8.25 10.5-8.25 10.5S3.75 14 3.75 8.75A4.25 4.25 0 0112 7.15a4.25 4.25 0 018.25 1.6z" />
        </svg>
        @break
    @case('taxi')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 17.25h10.5M7.5 17.25v1.5M16.5 17.25v1.5M5.25 12.75l1.55-4.2A2.25 2.25 0 018.9 7.08h6.2c.94 0 1.78.59 2.1 1.47l1.55 4.2M4.5 12.75h15v4.5h-15v-4.5zM7.5 14.85h.01M16.5 14.85h.01" />
        </svg>
        @break
    @case('users')
        <svg {{ $attributes->merge($common)->except('name') }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 8.25a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0M18.75 10.5a2.5 2.5 0 110-5M20.25 18.75a5.2 5.2 0 00-2.25-3.5" />
        </svg>
        @break
    @default
        <svg {{ $attributes->merge($common)->except('name') }} />
@endswitch
