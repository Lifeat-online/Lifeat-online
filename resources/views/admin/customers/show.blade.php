<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Customer {{ $customer->name }}</h2>
            <a href="{{ route('admin.customers.index') }}" class="rounded-md bg-slate-100 px-4 py-2 text-sm text-slate-700">Back to Lookup</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm lg:col-span-2">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $customer->name }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ $customer->email }}</p>
                    @if ($customer->phone)
                        <p class="text-sm text-gray-600">{{ $customer->phone }}</p>
                    @endif
                    @if ($customer->username)
                        <p class="text-sm text-gray-600">Username: {{ $customer->username }}</p>
                    @endif
                    <p class="text-sm text-gray-600">Role: {{ ucfirst(str_replace('_', ' ', $customer->role ?: 'member')) }}</p>
                    <p class="text-sm text-gray-600">Joined: {{ optional($customer->created_at)->format('j M Y H:i') ?: '-' }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Orders</p><p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['orders'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Payments</p><p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['payments'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Subscriptions</p><p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['subscriptions'] }}</p></div>
                    <div class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-500">Active Subscriptions</p><p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['active_subscriptions'] }}</p></div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Recent Orders</h3>
                    <div class="space-y-3 text-sm">
                        @forelse ($customer->orders->take(10) as $order)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium"><a class="text-indigo-600" href="{{ route('admin.finance.orders.show', $order) }}">{{ $order->order_number }}</a> · {{ ucfirst($order->status) }}</p>
                                <p>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</p>
                                <p class="text-gray-500">{{ optional($order->created_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                        @empty
                            <p class="text-gray-500">No orders recorded.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Recent Payments</h3>
                    <div class="space-y-3 text-sm">
                        @forelse ($customer->payments->take(10) as $payment)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium"><a class="text-indigo-600" href="{{ route('admin.finance.payments.show', $payment) }}">Payment {{ $payment->id }}</a> · {{ ucfirst($payment->status) }}</p>
                                <p>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</p>
                                <p class="text-gray-500">{{ $payment->provider_transaction_id ?: 'No transaction reference' }}</p>
                                @if (auth()->user()->hasRole('admin', 'editor'))
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        @if ($payment->status !== 'paid')
                                            <form method="post" action="{{ route('admin.finance.payments.mark-paid', $payment) }}">
                                                @csrf
                                                <input type="hidden" name="return_to" value="{{ url()->current() }}">
                                                <button class="text-sm font-medium text-green-600" type="submit">Mark paid</button>
                                            </form>
                                        @endif
                                        @if (! in_array($payment->status, ['failed', 'refunded'], true))
                                            <form method="post" action="{{ route('admin.finance.payments.mark-failed', $payment) }}">
                                                @csrf
                                                <input type="hidden" name="return_to" value="{{ url()->current() }}">
                                                <button class="text-sm font-medium text-amber-600" type="submit">Mark failed</button>
                                            </form>
                                        @endif
                                        @if (auth()->user()->hasRole('admin') && $payment->status === 'paid')
                                            <form method="post" action="{{ route('admin.finance.payments.refunds.store', $payment) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="return_to" value="{{ url()->current() }}">
                                                <input class="w-24 rounded-md border-gray-300 text-xs" type="number" name="refund_amount" min="0.01" step="0.01" placeholder="Amount">
                                                <input class="rounded-md border-gray-300 text-xs" type="text" name="refund_reason" placeholder="Reason">
                                                <button class="text-sm font-medium text-red-600" type="submit">Record refund</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-gray-500">No payments recorded.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Subscriptions</h3>
                    <div class="space-y-3 text-sm">
                        @forelse ($customer->subscriptions->take(10) as $subscription)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium"><a class="text-indigo-600" href="{{ route('admin.finance.subscriptions.show', $subscription) }}">{{ $subscription->package?->name ?: 'Package' }}</a> · {{ ucfirst($subscription->status) }}</p>
                                <p class="text-gray-500">Ends {{ optional($subscription->ends_at)->format('j M Y H:i') ?: '-' }}</p>
                                @if ($subscription->subscribable)
                                    <p class="text-gray-500">{{ class_basename($subscription->subscribable_type) }} linked</p>
                                @endif
                                @if (auth()->user()->hasRole('admin', 'editor'))
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        <form method="post" action="{{ route('admin.finance.subscriptions.extend', $subscription) }}" class="flex items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ url()->current() }}">
                                            <input class="w-24 rounded-md border-gray-300 text-xs" type="number" name="extension_days" min="1" value="30">
                                            <button class="text-sm font-medium text-indigo-600" type="submit">Extend</button>
                                        </form>
                                        @if (auth()->user()->hasRole('admin') && $subscription->status !== 'suspended')
                                            <form method="post" action="{{ route('admin.finance.subscriptions.suspend', $subscription) }}">
                                                @csrf
                                                <input type="hidden" name="return_to" value="{{ url()->current() }}">
                                                <button class="text-sm font-medium text-red-600" type="submit">Suspend</button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route('admin.finance.subscriptions.reminder', $subscription) }}">
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ url()->current() }}">
                                            <button class="text-sm font-medium text-amber-600" type="submit">Log reminder</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-gray-500">No subscriptions recorded.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Listings and Events</h3>
                    <div class="space-y-3 text-sm">
                        @forelse ($customer->listings->take(8) as $listing)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium"><a class="text-indigo-600" href="{{ route('admin.listings.edit', $listing) }}">{{ $listing->title }}</a> · {{ ucfirst($listing->status) }}</p>
                                <p class="text-gray-500">{{ $listing->city ?: 'No city' }}</p>
                            </div>
                        @empty
                            <p class="text-gray-500">No listings recorded.</p>
                        @endforelse

                        @foreach ($customer->events->take(8) as $event)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium"><a class="text-indigo-600" href="{{ route('admin.events.edit', $event) }}">{{ $event->title }}</a> · {{ ucfirst($event->status) }}</p>
                                <p class="text-gray-500">{{ optional($event->start_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Articles and Applications</h3>
                    <div class="space-y-3 text-sm">
                        @forelse ($customer->articles->take(8) as $article)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium"><a class="text-indigo-600" href="{{ route('admin.articles.edit', $article) }}">{{ $article->title }}</a> · {{ ucfirst($article->status) }}</p>
                                <p class="text-gray-500">{{ optional($article->updated_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                        @empty
                            <p class="text-gray-500">No articles recorded.</p>
                        @endforelse

                        @foreach ($customer->writerApplications->take(6) as $application)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium"><a class="text-indigo-600" href="{{ route('admin.writer-applications.show', $application) }}">{{ $application->fullName() }}</a> · {{ str_replace('_', ' ', ucfirst($application->status)) }}</p>
                                <p class="text-gray-500">{{ $application->email }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Add Support Note</h3>
                    <form method="post" action="{{ route('admin.customers.notes.store', $customer) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="note">Internal note</label>
                            <textarea id="note" name="note" rows="4" class="w-full rounded-md border-gray-300 text-sm" placeholder="Capture issue context, actions taken, or follow-up needed.">{{ old('note') }}</textarea>
                            @error('note')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Save note</button>
                    </form>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-1">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Support Timeline</h3>
                    <div class="mb-4 flex flex-wrap gap-2 text-sm">
                        <a class="rounded-md px-3 py-1 {{ ($timelineFilters['timeline_filter'] ?? 'all') === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}" href="{{ route('admin.customers.show', $customer) }}">All</a>
                        <a class="rounded-md px-3 py-1 {{ ($timelineFilters['timeline_filter'] ?? '') === 'notes' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}" href="{{ route('admin.customers.show', [$customer, 'timeline_filter' => 'notes']) }}">Notes</a>
                        <a class="rounded-md px-3 py-1 {{ ($timelineFilters['timeline_filter'] ?? '') === 'finance' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}" href="{{ route('admin.customers.show', [$customer, 'timeline_filter' => 'finance']) }}">Finance</a>
                        <a class="rounded-md px-3 py-1 {{ ($timelineFilters['timeline_filter'] ?? '') === 'content' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}" href="{{ route('admin.customers.show', [$customer, 'timeline_filter' => 'content']) }}">Content</a>
                        <a class="rounded-md px-3 py-1 {{ ($timelineFilters['timeline_filter'] ?? '') === 'customer' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}" href="{{ route('admin.customers.show', [$customer, 'timeline_filter' => 'customer']) }}">Customer</a>
                    </div>
                    <form method="get" action="{{ route('admin.customers.show', $customer) }}" class="mb-4 grid gap-3 md:grid-cols-5">
                        <select class="rounded-md border-gray-300 text-sm" name="timeline_filter">
                            @foreach (['all' => 'All scopes', 'notes' => 'Notes', 'finance' => 'Finance', 'content' => 'Content', 'customer' => 'Customer'] as $value => $label)
                                <option value="{{ $value }}" @selected(($timelineFilters['timeline_filter'] ?? 'all') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input class="rounded-md border-gray-300 text-sm" type="text" name="timeline_action" placeholder="Action contains" value="{{ $timelineFilters['timeline_action'] ?? '' }}">
                        <input class="rounded-md border-gray-300 text-sm" type="date" name="timeline_from" value="{{ $timelineFilters['timeline_from'] ?? '' }}">
                        <input class="rounded-md border-gray-300 text-sm" type="date" name="timeline_to" value="{{ $timelineFilters['timeline_to'] ?? '' }}">
                        <div class="flex gap-2">
                            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Filter Timeline</button>
                            <a class="rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700" href="{{ route('admin.customers.show', $customer) }}">Reset</a>
                        </div>
                    </form>
                    <div class="space-y-3 text-sm">
                        @forelse ($supportTimeline as $entry)
                            <div class="rounded-md bg-gray-50 p-3">
                                <p class="font-medium">{{ str_replace('.', ' · ', $entry->action) }}</p>
                                <p class="text-gray-500">Actor: {{ $entry->actor?->name ?: 'System' }}</p>
                                <p class="text-gray-500">Subject: {{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }}</p>
                                @if (($entry->after_json['note'] ?? null) !== null)
                                    <p class="mt-2 whitespace-pre-line text-gray-700">{{ $entry->after_json['note'] }}</p>
                                @endif
                                <p class="text-gray-500">{{ optional($entry->created_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                        @empty
                            <p class="text-gray-500">No support timeline entries yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
