<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Operator Assistant</h2>
                <p class="mt-1 text-sm text-gray-500">Authorized tools with persistent history, risk labels, and audited results.</p>
            </div>
            <a href="{{ route('admin.ai-operations.index') }}" class="text-sm font-medium text-indigo-600">AI operations</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[16rem_1fr] lg:px-8">
            <aside class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Conversations</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($conversations as $item)
                        <a href="{{ route('admin.ai-operator.index', ['conversation' => $item->id]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $conversation?->is($item) ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50' }}">
                            {{ $item->title }}
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">Your first tool run starts a conversation.</p>
                    @endforelse
                </div>
            </aside>

            <main class="space-y-6">
                @if ($errors->any())
                    <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ implode(' ', $errors->all()) }}</div>
                @endif

                <section class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Run an authorized tool</h3>
                    <form method="post" action="{{ route('admin.ai-operator.messages.store') }}" class="mt-4 space-y-4">
                        @csrf
                        @if ($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ($tools as $tool)
                                <label class="cursor-pointer rounded-lg border border-gray-200 p-4 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="tool" value="{{ $tool['name'] }}" @checked(old('tool', $tools->first()['name'] ?? '') === $tool['name'])>
                                        <span class="font-medium text-gray-900">{{ $tool['label'] }}</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $tool['risk'] }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">{{ $tool['description'] }}</p>
                                </label>
                            @endforeach
                        </div>
                        <div>
                            <label for="arguments" class="text-sm font-medium text-gray-700">Arguments (JSON object)</label>
                            <textarea id="arguments" name="arguments" rows="4" class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm">{{ old('arguments', '{}') }}</textarea>
                        </div>
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white" type="submit">Run tool</button>
                    </form>
                </section>

                <section class="space-y-3">
                    @forelse ($conversation?->messages ?? [] as $message)
                        <article class="rounded-xl p-5 shadow-sm {{ $message->role === 'user' ? 'ml-8 bg-indigo-50' : 'mr-8 bg-white' }}">
                            <div class="flex items-center justify-between gap-3 text-xs uppercase tracking-wide text-gray-500">
                                <span>{{ $message->role }} · {{ $message->tool }}</span>
                                @if ($message->run)<span>{{ $message->run->risk }} · {{ $message->run->status }}</span>@endif
                            </div>
                            <pre class="mt-3 overflow-x-auto whitespace-pre-wrap text-sm text-gray-800">{{ $message->content }}</pre>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">No tool results in this conversation yet.</div>
                    @endforelse
                </section>
            </main>
        </div>
    </div>
</x-app-layout>
