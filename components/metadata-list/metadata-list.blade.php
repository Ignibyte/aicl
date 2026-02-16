<dl {{ $attributes->merge(['class' => 'divide-y divide-gray-200 dark:divide-gray-700']) }}>
    @foreach($items as $label => $value)
        <div class="flex justify-between gap-4 py-3">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
            <dd class="text-sm text-gray-900 dark:text-white">{{ $value ?? '—' }}</dd>
        </div>
    @endforeach
</dl>
