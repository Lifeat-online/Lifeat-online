<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Councillors</h2>
            <a href="{{ route('admin.councillors.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Add Councillor</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg bg-white p-6 shadow-sm">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-gray-500">
                            <th class="py-2">Name</th>
                            <th class="py-2">Phone</th>
                            <th class="py-2">Email</th>
                            <th class="py-2">Assigned Faults</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($councillors as $councillor)
                            <tr class="border-t">
                                <td class="py-3 font-semibold text-gray-900">{{ $councillor->full_name }}</td>
                                <td class="py-3 text-gray-700">{{ $councillor->phone }}</td>
                                <td class="py-3 text-gray-700">{{ $councillor->email }}</td>
                                <td class="py-3 text-gray-700">{{ $councillor->assigned_fault_reports_count }}</td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('admin.councillors.edit', $councillor) }}" class="rounded-md bg-slate-700 px-3 py-1.5 text-xs text-white">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-gray-500">No councillors yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $councillors->links() }}</div>
        </div>
    </div>
</x-app-layout>

