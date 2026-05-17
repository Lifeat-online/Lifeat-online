<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700 shadow-sm transition ease-in-out duration-150 hover:-translate-y-0.5 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25']) }}>
    {{ $slot }}
</button>
