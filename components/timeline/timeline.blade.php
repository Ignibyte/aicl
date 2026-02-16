<div {{ $attributes->merge(['class' => 'relative']) }}>
    <div class="absolute left-4 top-0 h-full w-0.5 bg-gray-200 dark:bg-gray-700"></div>

    <ul class="space-y-6">
        @foreach($entries as $entry)
            @php
                $color = $entry['color'] ?? 'gray';
                $dotColor = match($color) {
                    'green', 'success' => 'bg-green-500',
                    'blue', 'info' => 'bg-blue-500',
                    'yellow', 'warning' => 'bg-yellow-500',
                    'red', 'danger' => 'bg-red-500',
                    default => 'bg-gray-400',
                };
            @endphp
            <li class="relative pl-10">
                <div class="absolute left-2.5 top-1 h-3 w-3 rounded-full {{ $dotColor }} ring-4 ring-white dark:ring-gray-900"></div>

                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $entry['title'] }}</span>
                        <time class="text-xs text-gray-500 dark:text-gray-400">{{ $entry['date'] }}</time>
                    </div>
                    @if(!empty($entry['description']))
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $entry['description'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</div>
