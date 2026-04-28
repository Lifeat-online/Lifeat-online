<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <p>You are signed in as <strong>{{ auth()->user()->name }}</strong>.</p>
                    <p>Role: <strong>{{ ucfirst(auth()->user()->role) }}</strong></p>
                    @if (in_array(auth()->user()->role, ['admin', 'editor', 'staff'], true))
                        <a href="{{ route('admin.dashboard') }}" class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-white">Open Management Area</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>