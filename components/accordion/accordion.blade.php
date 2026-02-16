@props([
    'allowMultiple' => false,
    'defaultOpen' => null,
])

<div
    x-data="aiclAccordion({
        allowMultiple: @js($allowMultiple),
        defaultOpen: {{ $defaultOpenJson() }},
    })"
    {{ $attributes->merge(['class' => 'divide-y divide-gray-200 rounded-xl border border-gray-200 dark:divide-gray-700 dark:border-gray-700']) }}
    role="presentation"
>
    {{ $slot }}
</div>
