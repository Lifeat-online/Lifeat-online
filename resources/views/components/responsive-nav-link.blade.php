@props(['active'])

@php
$classes = ($active ?? false)
            ? 'backend-responsive-link backend-responsive-link-active block ps-3 pe-4 py-2 text-start text-base font-semibold text-indigo-700 focus:outline-none transition duration-150 ease-in-out'
            : 'backend-responsive-link block ps-3 pe-4 py-2 text-start text-base font-semibold text-gray-600 hover:text-gray-800 hover:bg-gray-50 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
