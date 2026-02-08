<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Created Token Display --}}
        @if($createdToken)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2 text-success-600 dark:text-success-400">
                        <x-heroicon-o-check-circle class="h-5 w-5" />
                        {{ __('Token Created Successfully') }}
                    </div>
                </x-slot>
                <x-slot name="description">
                    {{ __('Make sure to copy your personal access token now. You won\'t be able to see it again!') }}
                </x-slot>

                <div class="space-y-4">
                    <div class="relative">
                        <input
                            type="text"
                            readonly
                            value="{{ $createdToken }}"
                            class="fi-input block w-full rounded-lg border-gray-300 bg-gray-50 font-mono text-sm shadow-sm dark:border-gray-600 dark:bg-gray-700"
                        />
                    </div>

                    <div class="flex gap-3">
                        <x-filament::button
                            x-data="{}"
                            x-on:click="navigator.clipboard.writeText('{{ $createdToken }}').then(() => $tooltip('Copied!', { timeout: 1500 }))"
                            icon="heroicon-o-clipboard-document"
                            color="primary"
                        >
                            {{ __('Copy to Clipboard') }}
                        </x-filament::button>

                        <x-filament::button
                            wire:click="clearCreatedToken"
                            color="gray"
                        >
                            {{ __('Done') }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Quick Create Form --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Create New Token') }}
            </x-slot>
            <x-slot name="description">
                {{ __('API tokens allow third-party services to authenticate with our API on your behalf.') }}
            </x-slot>

            <form wire:submit="createToken" class="space-y-4">
                <div class="flex gap-4">
                    <div class="flex-1">
                        <x-filament::input.wrapper>
                            <x-filament::input
                                wire:model="newTokenName"
                                type="text"
                                placeholder="{{ __('Token name (e.g., CI/CD Pipeline)') }}"
                            />
                        </x-filament::input.wrapper>
                    </div>
                    <x-filament::button type="submit" icon="heroicon-o-plus">
                        {{ __('Create') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Token List --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Your API Tokens') }}
            </x-slot>

            @php $tokens = $this->getTokens(); @endphp

            @if(count($tokens) > 0)
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($tokens as $token)
                        <div class="flex items-center justify-between py-4 first:pt-0 last:pb-0">
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">
                                    {{ $token['name'] }}
                                </h4>
                                <div class="mt-1 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span>
                                        {{ __('Created') }}: {{ $token['created_at'] }}
                                    </span>
                                    @if($token['expires_at'] !== 'Never')
                                        <span>
                                            {{ __('Expires') }}: {{ $token['expires_at'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <x-filament::button
                                wire:click="revokeToken('{{ $token['id'] }}')"
                                wire:confirm="{{ __('Are you sure you want to revoke this token?') }}"
                                color="danger"
                                size="sm"
                                outlined
                            >
                                {{ __('Revoke') }}
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <x-heroicon-o-key class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ __('No API tokens') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Create a token to get started with the API.') }}
                    </p>
                </div>
            @endif
        </x-filament::section>

        {{-- API Documentation Link --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Using Your Token') }}
            </x-slot>

            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('Include your token in the Authorization header of your API requests:') }}</p>
                <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-x-auto"><code>curl -H "Authorization: Bearer YOUR_TOKEN" \
     {{ config('app.url') }}/api/v1/projects</code></pre>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
