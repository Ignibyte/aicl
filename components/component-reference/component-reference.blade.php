@props([
    'component',
])

@if($definition)
<div {{ $attributes->merge(['class' => 'mt-3']) }}>
    <x-aicl-accordion>
        <x-aicl-accordion-item name="ref-{{ $definition->shortTag() }}" label="Component Reference" icon="heroicon-o-document-text">
            <div class="space-y-4">

                {{-- AI Decision Rule --}}
                @if($definition->decisionRule)
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">AI Decision Rule</h4>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $definition->decisionRule }}</p>
                    </div>
                @endif

                {{-- Context Tags --}}
                @if(!empty($definition->context))
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Rendering Contexts</h4>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @foreach($definition->context as $ctx)
                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30">{{ $ctx }}</span>
                            @endforeach
                            @if(!empty($definition->notFor))
                                @foreach($definition->notFor as $excluded)
                                    <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20 line-through dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/30">{{ $excluded }}</span>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Props Table --}}
                @if(!empty($definition->props))
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Props</h4>
                        <div class="mt-1 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400">Name</th>
                                        <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400">Type</th>
                                        <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400">Required</th>
                                        <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400">Default</th>
                                        <th class="pb-2 font-medium text-gray-600 dark:text-gray-400">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($definition->props as $propName => $prop)
                                        <tr>
                                            <td class="py-1.5 pr-4 font-mono text-xs text-gray-900 dark:text-gray-100">{{ $propName }}</td>
                                            <td class="py-1.5 pr-4 text-xs text-gray-600 dark:text-gray-400">
                                                @if(is_array($prop['type'] ?? null))
                                                    {{ implode('|', $prop['type']) }}
                                                @else
                                                    {{ $prop['type'] ?? 'mixed' }}
                                                @endif
                                            </td>
                                            <td class="py-1.5 pr-4 text-xs">
                                                @if($prop['required'] ?? false)
                                                    <span class="text-red-600 dark:text-red-400">Yes</span>
                                                @else
                                                    <span class="text-gray-400">No</span>
                                                @endif
                                            </td>
                                            <td class="py-1.5 pr-4 font-mono text-xs text-gray-500 dark:text-gray-500">
                                                @if(array_key_exists('default', $prop))
                                                    @if(is_null($prop['default']))
                                                        <span class="italic">null</span>
                                                    @elseif(is_bool($prop['default']))
                                                        {{ $prop['default'] ? 'true' : 'false' }}
                                                    @elseif(is_array($prop['default']))
                                                        {{ json_encode($prop['default']) }}
                                                    @else
                                                        {{ $prop['default'] }}
                                                    @endif
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="py-1.5 text-xs text-gray-600 dark:text-gray-400">
                                                {{ $prop['description'] ?? '' }}
                                                @if(!empty($prop['enum']))
                                                    <br><span class="font-mono text-[10px] text-gray-400">{{ implode(' | ', $prop['enum']) }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Filament Equivalent --}}
                @if($definition->filamentEquivalent)
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Filament Equivalent</h4>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                            <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-800">{{ $definition->filamentEquivalent['class'] ?? '' }}</code>
                            @if(!empty($definition->filamentEquivalent['note']))
                                <span class="ml-1 text-gray-500 dark:text-gray-400">&mdash; {{ $definition->filamentEquivalent['note'] }}</span>
                            @endif
                        </p>
                    </div>
                @endif

                {{-- Composable In --}}
                @if(!empty($definition->composableIn))
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Composable In</h4>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @foreach($definition->composableIn as $parent)
                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-mono text-gray-700 dark:bg-gray-800 dark:text-gray-300">x-aicl-{{ $parent }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </x-aicl-accordion-item>
    </x-aicl-accordion>
</div>
@endif
