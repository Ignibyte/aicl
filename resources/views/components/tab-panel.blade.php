@props(['name', 'label', 'icon' => null])

<div
    x-show="activeTab === '{{ $name }}'"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    data-tab-name="{{ $name }}"
    data-tab-label="{{ $label }}"
    role="tabpanel"
    :id="'tab-panel-' + '{{ $name }}'"
    {{ $attributes }}
>
    {{ $slot }}
</div>
