@props(['name', 'label', 'icon' => null, 'badge' => null, 'disabled' => false])

<div
    x-show="activeTab === '{{ $name }}'"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    data-tab-name="{{ $name }}"
    data-tab-label="{{ $label }}"
    @if($icon) data-tab-icon="{{ $icon }}" @endif
    @if($badge !== null) data-tab-badge="{{ $badge }}" @endif
    @if($disabled) data-tab-disabled="true" @endif
    role="tabpanel"
    :id="'tab-panel-' + '{{ $name }}'"
    tabindex="0"
    {{ $attributes }}
>
    {{ $slot }}
</div>
