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
    @default
        <svg {{ $attributes->merge($common)->except('name') }} />
@endswitch
