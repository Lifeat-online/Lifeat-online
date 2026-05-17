@props(['active'])

@php
$classes = ($active ?? false)
            ? 'backend-nav-link backend-nav-link-active inline-flex shrink-0 items-center whitespace-nowrap text-sm font-semibold leading-5 focus:outline-none transition duration-150 ease-in-out'
            : 'backend-nav-link inline-flex shrink-0 items-center whitespace-nowrap text-sm font-semibold leading-5 text-gray-600 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
