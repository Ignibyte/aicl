<x-filament-panels::page>
    {{-- Stack Versions --}}
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-cpu-chip" class="h-5 w-5" />
                <span>Stack Versions</span>
            </div>
        </x-slot>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            @foreach ($this->getStackVersions() as $component => $version)
                <div class="text-center rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $component }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $version }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

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

</x-filament-panels::page>
