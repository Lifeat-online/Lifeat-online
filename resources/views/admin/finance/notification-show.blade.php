<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notification {{ $notification->id }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->has('notification'))
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('notification') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <p><strong>Type:</strong> {{ $notification->notification_type }}</p>
                <p><strong>Recipient:</strong> {{ $notification->recipient ?: '-' }}</p>
                <p><strong>Channel:</strong> {{ ucfirst($notification->channel) }}</p>
                <p><strong>Status:</strong> {{ ucfirst($notification->status) }}</p>
                <p><strong>Sent At:</strong> {{ optional($notification->sent_at)->format('j M Y H:i') ?: '-' }}</p>
                @if (($notification->meta_json['error_message'] ?? null))
                    <p><strong>Last Error:</strong> {{ $notification->meta_json['error_message'] }}</p>
                @endif
                @if (! auth()->user()->hasRole('admin', 'editor'))
                    <p class="mt-4 text-sm text-slate-600">Read-only access. Notification resend is limited to admin and editor roles.</p>
                @elseif (! $canResend)
                    <p class="mt-4 text-sm text-slate-600">This notification is a delivery log only and cannot be resent from this screen.</p>
                @elseif ($resendAvailableAt)
                    <p class="mt-4 text-sm text-amber-700">Resend available after {{ $resendAvailableAt->format('H:i') }}.</p>
                @else
                    <form class="mt-4" method="post" action="{{ route('admin.finance.notifications.resend', $notification) }}">
                        @csrf
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Resend Notification</button>
                    </form>
                @endif
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Metadata</h3>
                <pre style="overflow:auto; white-space:pre-wrap;">{{ json_encode($notification->meta_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</x-app-layout>
