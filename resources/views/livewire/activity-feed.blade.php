<div @if($pollInterval > 0) wire:poll.{{ $pollInterval }}s @endif>
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $heading }}</h3>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse($this->activities as $activity)
                <div class="flex items-start gap-3 px-4 py-3">
                    <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-600 dark:bg-primary-900/20 dark:text-primary-400">
                        @if($activity->event === 'created')
                            <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                        @elseif($activity->event === 'updated')
                            <x-filament::icon icon="heroicon-m-pencil" class="h-4 w-4" />
                        @elseif($activity->event === 'deleted')
                            <x-filament::icon icon="heroicon-m-trash" class="h-4 w-4" />
                        @else
                            <x-filament::icon icon="heroicon-m-bolt" class="h-4 w-4" />
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-900 dark:text-white">
                            {{ $activity->description }}
                        </p>

                        <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <time datetime="{{ $activity->created_at->toIso8601String() }}">
                                {{ $activity->created_at->diffForHumans() }}
                            </time>

                            @if($showCauser && $activity->causer)
                                <span>&middot;</span>
                                <span>{{ $activity->causer->name ?? 'System' }}</span>
                            @endif

                            @if($showSubject && $activity->subject)
                                <span>&middot;</span>
                                <span>{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span>
                            @endif
                        </div>

                        @if($activity->properties->isNotEmpty())
                            <details class="mt-1">
                                <summary class="cursor-pointer text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    Changes
                                </summary>
                                <div class="mt-1 rounded bg-gray-50 p-2 text-xs dark:bg-gray-900">
                                    @if($activity->properties->has('attributes'))
                                        @foreach($activity->properties['attributes'] as $key => $value)
                                            <div class="flex gap-2">
                                                <span class="font-medium text-gray-600 dark:text-gray-400">{{ $key }}:</span>
                                                @if($activity->properties->has('old') && isset($activity->properties['old'][$key]))
                                                    <span class="text-red-500 line-through">{{ is_array($activity->properties['old'][$key]) ? json_encode($activity->properties['old'][$key]) : $activity->properties['old'][$key] }}</span>
                                                    <span>&rarr;</span>
                                                @endif
                                                <span class="text-green-600 dark:text-green-400">{{ is_array($value) ? json_encode($value) : $value }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    No activity recorded yet.
                </div>
            @endforelse
        </div>

        @if($this->activities->hasPages())
            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                {{ $this->activities->links() }}
            </div>
        @endif
    </div>
</div>
