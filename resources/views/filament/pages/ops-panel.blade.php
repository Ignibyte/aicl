<x-filament-panels::page>
    {{-- Platform Versions (compact bar — infrastructure versions are in health cards below) --}}
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl bg-gray-50 px-5 py-3 text-sm dark:bg-white/5">
        @foreach ($this->getStackVersions() as $component => $version)
            <div class="flex items-center gap-1.5">
                <span class="font-medium text-gray-500 dark:text-gray-400">{{ $component }}</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ $version }}</span>
            </div>
            @if (! $loop->last)
                <span class="hidden sm:inline text-gray-300 dark:text-gray-600">|</span>
            @endif
        @endforeach
    </div>

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
