<x-filament-panels::page>
    <div wire:poll.30s>
        {{-- Service Health Checks Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($this->getServiceChecks() as $check)
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-filament::icon
                                :icon="$check->icon"
                                class="h-5 w-5"
                            />
                            <span>{{ $check->name }}</span>
                        </div>
                    </x-slot>

                    <x-slot name="afterHeader">
                        <x-filament::badge :color="$check->status->color()">
                            {{ $check->status->label() }}
                        </x-filament::badge>
                    </x-slot>

                    @if ($check->error)
                        <div class="mb-3 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-950 dark:text-danger-400">
                            {{ $check->error }}
                        </div>
                    @endif

                    @if (count($check->details) > 0)
                        <dl class="space-y-2 text-sm">
                            @foreach ($check->details as $label => $value)
                                <div class="flex justify-between">
                                    <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="text-gray-900 dark:text-white">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </x-filament::section>
            @endforeach
        </div>
    </div>

    {{-- Connected Sessions --}}
    @php
        $sessions = $this->getActiveSessions();
        $currentSessionId = request()->session()->getId();
        $isSuperAdmin = auth()->user()?->hasRole('super_admin') ?? false;
    @endphp

    <div wire:poll.5s>
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-users"
                        class="h-5 w-5"
                    />
                    <span>Connected Sessions</span>
                </div>
            </x-slot>

            <x-slot name="afterHeader">
                <x-filament::badge color="info">
                    {{ $sessions->count() }} active
                </x-filament::badge>
            </x-slot>

            @if ($sessions->isEmpty())
                <div class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">
                    No active sessions tracked yet. Sessions appear after user activity.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">User</th>
                                <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Session ID</th>
                                <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Current Page</th>
                                <th class="text-left py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Last Seen</th>
                                @if ($isSuperAdmin)
                                    <th class="text-right py-3 px-4 font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                @php
                                    $isCurrentSession = ($session['session_id'] ?? '') === $currentSessionId;
                                    $lastSeen = \Carbon\Carbon::parse($session['last_seen_at'] ?? now());
                                    $pagePath = parse_url($session['current_url'] ?? '', PHP_URL_PATH) ?: '—';
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <span class="relative flex h-2.5 w-2.5">
                                                @if ($isCurrentSession || $lastSeen->diffInMinutes(now()) < 2)
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-success-500"></span>
                                                @else
                                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-warning-500"></span>
                                                @endif
                                            </span>
                                            <div>
                                                <span class="font-medium text-gray-900 dark:text-white">
                                                    {{ $session['user_name'] ?? 'Unknown' }}
                                                    @if ($isCurrentSession)
                                                        <span class="text-xs text-gray-400">(you)</span>
                                                    @endif
                                                </span>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $session['user_email'] ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono text-gray-700 dark:text-gray-300">
                                            {{ $session['session_id_short'] ?? '—' }}
                                        </code>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600 dark:text-gray-400">
                                        {{ $pagePath }}
                                    </td>
                                    <td class="py-3 px-4 text-gray-600 dark:text-gray-400">
                                        @if ($isCurrentSession)
                                            Now
                                        @else
                                            {{ $lastSeen->diffForHumans(short: true) }}
                                        @endif
                                    </td>
                                    @if ($isSuperAdmin)
                                        <td class="py-3 px-4 text-right">
                                            @if (! $isCurrentSession)
                                                <x-filament::button
                                                    color="danger"
                                                    size="xs"
                                                    icon="heroicon-o-x-mark"
                                                    wire:click="mountAction('killSession', { sessionId: '{{ $session['session_id'] }}', userName: '{{ addslashes($session['user_name'] ?? 'this user') }}' })"
                                                >
                                                    Kill
                                                </x-filament::button>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
