<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Operator Assistant</h2>
                <p class="mt-1 text-sm text-gray-500">Developer research, editorial, directory, and platform tasks with evidence and audited execution.</p>
            </div>
            <a href="{{ route('admin.ai-operations.index') }}" class="text-sm font-medium text-indigo-600">AI operations</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[16rem_1fr] lg:px-8">
            <aside class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Conversations</h3>
                <a href="{{ route('admin.ai-operator.index') }}" class="mt-3 block rounded-lg bg-indigo-600 px-3 py-2 text-center text-sm font-medium text-white">New developer task</a>
                <div class="mt-3 space-y-2">
                    @forelse ($conversations as $item)
                        <a href="{{ route('admin.ai-operator.index', ['conversation' => $item->id]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $conversation?->is($item) ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50' }}">{{ $item->title }}</a>
                    @empty
                        <p class="text-sm text-gray-500">Your first request starts a conversation.</p>
                    @endforelse
                </div>
            </aside>

            <main class="space-y-6">
                @if ($errors->any())
                    <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ implode(' ', $errors->all()) }}</div>
                @endif

                <section class="rounded-xl bg-gradient-to-br from-indigo-700 to-slate-900 p-6 text-white shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-200">Developer task</p>
                            <h3 class="mt-2 text-xl font-semibold">What should I research, create, update, or inspect?</h3>
                            <p class="mt-2 max-w-3xl text-sm text-indigo-100">Search public sources, retain evidence, add unclaimed businesses, write event articles, update content, or inspect platform operations.</p>
                        </div>
                        <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium">R0/R1 automatic · R2/R3 approval</span>
                    </div>
                    @if ($agentEnabled)
                        <form method="post" action="{{ route('admin.ai-operator.tasks.store') }}" class="mt-5" data-task-create>
                            @csrf
                            @if ($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
                            <label for="operator-message" class="sr-only">Developer request</label>
                            <textarea id="operator-message" name="message" rows="4" required maxlength="5000" class="w-full rounded-xl border-white/20 bg-white text-gray-900 shadow-sm" placeholder="For example: Search for Acme Bakery in Bethlehem, verify its details, and add it to the directory."></textarea>
                            <div class="mt-3 flex items-center justify-between gap-3">
                                <p class="text-xs text-indigo-200">Web content is untrusted evidence and retained with source links.</p>
                                <button type="submit" class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-indigo-700">Start task</button>
                            </div>
                            <p class="mt-2 hidden rounded-lg bg-red-500/20 p-3 text-sm" data-task-error></p>
                        </form>
                    @else
                        <p class="mt-5 rounded-lg bg-amber-300/15 p-4 text-sm text-amber-100">The enhanced developer agent is disabled. Enable AI_OPERATOR_ASSISTANT and AI_OPERATOR_AGENT_ENABLED.</p>
                    @endif
                </section>

                @if ($conversation)
                    <section class="space-y-4">
                        @forelse ($conversation->tasks->sortByDesc('created_at') as $task)
                            @php
                                $waitingStep = $task->steps->firstWhere('status', \App\Models\OperatorTask::STATUS_WAITING_FOR_APPROVAL);
                                $active = in_array($task->status, [\App\Models\OperatorTask::STATUS_PLANNED, \App\Models\OperatorTask::STATUS_RUNNING], true);
                            @endphp
                            <article class="rounded-xl bg-white p-6 shadow-sm" data-task-status="{{ $task->status }}" @if($active) data-task-poll="{{ route('admin.ai-operator.tasks.show', $task) }}" @endif>
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Task {{ Str::limit($task->id, 8, '') }}</p>
                                        <h3 class="mt-1 font-semibold text-gray-900">{{ $task->goal }}</h3>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ match($task->status) { 'completed' => 'bg-emerald-100 text-emerald-700', 'failed', 'cancelled' => 'bg-red-100 text-red-700', 'waiting_for_approval', 'waiting_for_input' => 'bg-amber-100 text-amber-800', default => 'bg-indigo-100 text-indigo-700' } }}">{{ str($task->status)->replace('_', ' ')->title() }}</span>
                                </div>

                                @if ($task->plan)
                                    <ol class="mt-4 grid gap-2 text-sm text-gray-600 sm:grid-cols-2">
                                        @foreach ($task->plan as $planStep)
                                            <li class="rounded-lg bg-gray-50 px-3 py-2"><span class="mr-2 font-semibold text-indigo-600">{{ $loop->iteration }}.</span>{{ $planStep }}</li>
                                        @endforeach
                                    </ol>
                                @endif

                                @if ($task->steps->isNotEmpty())
                                    <div class="mt-4 space-y-2">
                                        @foreach ($task->steps as $step)
                                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm">
                                                <span class="text-gray-700">{{ $step->tool ?: str($step->action)->replace('_', ' ')->title() }}</span>
                                                <span class="text-xs text-gray-500">{{ $step->risk }} {{ str($step->status)->replace('_', ' ') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($task->sources)
                                    <div class="mt-5">
                                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sources</h4>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            @foreach ($task->sources as $sourceId)
                                                @if ($source = $sourceSnapshots->get($sourceId))
                                                    <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-200 p-3 text-sm hover:border-indigo-300">
                                                        <span class="block font-medium text-gray-900">{{ parse_url($source->url, PHP_URL_HOST) }}</span>
                                                        <span class="mt-1 block truncate text-xs text-gray-500">{{ $source->url }}</span>
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if ($task->result || $task->error)
                                    <p class="mt-4 rounded-lg {{ $task->error ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-800' }} p-3 text-sm">{{ $task->error ?: data_get($task->result, 'summary', data_get($task->result, 'message', 'Task completed.')) }}</p>
                                @endif

                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    @if ($task->status === \App\Models\OperatorTask::STATUS_WAITING_FOR_APPROVAL && $waitingStep)
                                        <button type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white" data-task-action="{{ route('admin.ai-operator.tasks.approve', $task) }}" data-task-payload='@json(["step_id" => $waitingStep->id])'>Approve {{ $waitingStep->tool }}</button>
                                        <button type="button" class="rounded-md bg-red-100 px-3 py-2 text-sm font-medium text-red-700" data-task-reject="{{ route('admin.ai-operator.tasks.reject', $task) }}">Reject</button>
                                    @elseif ($task->status === \App\Models\OperatorTask::STATUS_WAITING_FOR_INPUT)
                                        <form method="post" action="{{ route('admin.ai-operator.tasks.resume', $task) }}" class="flex w-full gap-2" data-task-resume>
                                            @csrf
                                            <input name="message" required maxlength="5000" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm" placeholder="Reply with the missing detail or another source">
                                            <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white">Resume</button>
                                        </form>
                                    @elseif ($active)
                                        <button type="button" class="rounded-md bg-red-100 px-3 py-2 text-sm font-medium text-red-700" data-task-action="{{ route('admin.ai-operator.tasks.cancel', $task) }}" data-task-payload="{}">Cancel task</button>
                                    @endif
                                    <span class="ml-auto text-xs text-gray-500">{{ data_get($task->usage, 'steps', 0) }} steps · ${{ number_format((float) data_get($task->usage, 'cost', 0), 4) }}</span>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">No developer tasks in this conversation yet.</div>
                        @endforelse
                    </section>
                @endif

                <details class="rounded-xl bg-white shadow-sm">
                    <summary class="cursor-pointer p-5 font-semibold text-gray-900">Manual tool runner <span class="ml-2 text-xs font-normal text-gray-500">Developer/debug</span></summary>
                    <div class="border-t border-gray-100 p-5">
                        <form method="post" action="{{ route('admin.ai-operator.messages.store') }}" class="space-y-4">
                            @csrf
                            @if ($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
                            <div class="grid gap-3 md:grid-cols-2">
                                @foreach ($tools as $tool)
                                    <label class="cursor-pointer rounded-lg border border-gray-200 p-3 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" name="tool" value="{{ $tool['name'] }}" @checked(old('tool', $tools->first()['name'] ?? '') === $tool['name'])>
                                            <span class="text-sm font-medium text-gray-900">{{ $tool['label'] }}</span>
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $tool['risk'] }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ $tool['description'] }}</p>
                                    </label>
                                @endforeach
                            </div>
                            <div>
                                <label for="arguments" class="text-sm font-medium text-gray-700">Arguments (JSON object)</label>
                                <textarea id="arguments" name="arguments" rows="4" class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm">{{ old('arguments', '{}') }}</textarea>
                            </div>
                            <button class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white" type="submit">Run tool</button>
                        </form>
                    </div>
                </details>

                @if ($conversation?->messages->isNotEmpty())
                    <details class="rounded-xl bg-white shadow-sm">
                        <summary class="cursor-pointer p-5 font-semibold text-gray-900">Conversation log</summary>
                        <div class="space-y-3 border-t border-gray-100 p-5">
                            @foreach ($conversation->messages as $message)
                                <article class="rounded-lg p-4 {{ $message->role === 'user' ? 'bg-indigo-50' : 'bg-gray-50' }}">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $message->role }} @if($message->tool)· {{ $message->tool }}@endif</span>
                                    <pre class="mt-2 overflow-x-auto whitespace-pre-wrap text-sm text-gray-800">{{ $message->content }}</pre>
                                </article>
                            @endforeach
                        </div>
                    </details>
                @endif
            </main>
        </div>
    </div>

    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const post = async (url, payload) => {
                const response = await fetch(url, {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify(payload)});
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Request failed.');
                return data;
            };
            document.querySelector('[data-task-create]')?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const form = event.currentTarget;
                try {
                    const data = await post(form.action, Object.fromEntries(new FormData(form)));
                    window.location.assign(`{{ route('admin.ai-operator.index') }}?conversation=${encodeURIComponent(data.conversation_id)}`);
                } catch (exception) {
                    const error = form.querySelector('[data-task-error]');
                    error.textContent = exception.message;
                    error.classList.remove('hidden');
                }
            });
            document.querySelectorAll('[data-task-action]').forEach((button) => button.addEventListener('click', async () => {
                button.disabled = true;
                try { await post(button.dataset.taskAction, JSON.parse(button.dataset.taskPayload || '{}')); window.location.reload(); }
                catch (exception) { window.alert(exception.message); button.disabled = false; }
            }));
            document.querySelectorAll('[data-task-reject]').forEach((button) => button.addEventListener('click', async () => {
                const reason = window.prompt('Why should this critical action be rejected?');
                if (!reason) return;
                try { await post(button.dataset.taskReject, {reason}); window.location.reload(); }
                catch (exception) { window.alert(exception.message); }
            }));
            document.querySelectorAll('[data-task-resume]').forEach((form) => form.addEventListener('submit', async (event) => {
                event.preventDefault();
                try { await post(form.action, Object.fromEntries(new FormData(form))); window.location.reload(); }
                catch (exception) { window.alert(exception.message); }
            }));
            const active = [...document.querySelectorAll('[data-task-poll]')];
            if (active.length) setInterval(async () => {
                for (const card of active) {
                    try {
                        const response = await fetch(card.dataset.taskPoll, {headers: {'Accept': 'application/json'}});
                        if (!response.ok) continue;
                        const task = await response.json();
                        if (task.status !== card.dataset.taskStatus) { window.location.reload(); return; }
                    } catch (_) {}
                }
            }, 4000);
        })();
    </script>
</x-app-layout>
