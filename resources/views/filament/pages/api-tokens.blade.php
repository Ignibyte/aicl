<x-filament-panels::page>
    <div x-data="{ activeTab: @entangle('activeTab') }" class="space-y-6">
        {{-- Tabs --}}
        <div class="flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800" role="tablist">
            <button
                x-on:click="activeTab = 'tokens'"
                :class="activeTab === 'tokens' ? 'bg-white shadow dark:bg-gray-700 text-primary-600 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition"
                role="tab"
                :aria-selected="activeTab === 'tokens'"
                aria-controls="panel-tokens"
            >
                <div class="flex items-center justify-center gap-2">
                    <x-heroicon-o-key class="h-4 w-4" />
                    {{ __('Access Tokens') }}
                </div>
            </button>
            @if($this->isMcpAvailable())
                <button
                    x-on:click="activeTab = 'mcp'"
                    :class="activeTab === 'mcp' ? 'bg-white shadow dark:bg-gray-700 text-primary-600 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition"
                    role="tab"
                    :aria-selected="activeTab === 'mcp'"
                    aria-controls="panel-mcp"
                >
                    <div class="flex items-center justify-center gap-2">
                        <x-heroicon-o-cpu-chip class="h-4 w-4" />
                        {{ __('MCP Server') }}
                    </div>
                </button>
            @endif
        </div>

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

        {{-- ==================== TOKENS TAB ==================== --}}
        <div x-show="activeTab === 'tokens'" x-cloak class="space-y-6" role="tabpanel" id="panel-tokens">
            {{-- Quick Create Form --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Create New Token') }}
                </x-slot>
                <x-slot name="description">
                    {{ __('API tokens allow third-party services and AI agents to authenticate with your application.') }}
                </x-slot>

                <form wire:submit="createToken" class="space-y-4">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    wire:model="newTokenName"
                                    type="text"
                                    aria-label="{{ __('Token name') }}"
                                    placeholder="{{ __('Token name (e.g., CI/CD Pipeline, Claude Desktop)') }}"
                                />
                            </x-filament::input.wrapper>
                        </div>
                        <x-filament::button type="submit" icon="heroicon-o-plus">
                            {{ __('Create') }}
                        </x-filament::button>
                    </div>

                    {{-- Scope Selection --}}
                    <div class="space-y-3">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Token Scopes') }}
                        </label>

                        {{-- Presets --}}
                        <div class="flex flex-wrap gap-2">
                            @foreach($this->getScopePresets() as $label => $scopes)
                                <x-filament::button
                                    wire:click="applyScopePreset('{{ $label }}')"
                                    color="gray"
                                    size="sm"
                                    outlined
                                >
                                    {{ $label }}
                                </x-filament::button>
                            @endforeach
                        </div>

                        {{-- Individual Scopes --}}
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach($this->getAvailableScopes() as $scope => $description)
                                <label class="flex items-start gap-2 rounded-lg border border-gray-200 p-3 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                    <input
                                        type="checkbox"
                                        value="{{ $scope }}"
                                        wire:model="selectedScopes"
                                        class="fi-checkbox-input mt-0.5 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $scope }}
                                        </span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ str($description)->after(' — ') }}
                                        </p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
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
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                        <span>{{ __('Created') }}: {{ $token['created_at'] }}</span>
                                        @if($token['expires_at'] !== 'Never')
                                            <span>{{ __('Expires') }}: {{ $token['expires_at'] }}</span>
                                        @endif
                                    </div>
                                    @if(!empty($token['scopes']))
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            @foreach($token['scopes'] as $scope)
                                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">
                                                    {{ $scope }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
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
                    <x-aicl-empty-state
                        icon="heroicon-o-key"
                        :heading="__('No API tokens')"
                        :description="__('Create a token to get started with the API.')"
                    />
                @endif
            </x-filament::section>

            {{-- Using Your Token --}}
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

        {{-- ==================== MCP TAB ==================== --}}
        @if($this->isMcpAvailable())
            <div x-show="activeTab === 'mcp'" x-cloak class="space-y-6" role="tabpanel" id="panel-mcp">
                {{-- Server Status --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <span>{{ __('MCP Server') }}</span>
                            <div class="flex items-center gap-2">
                                @if(config('aicl.features.mcp'))
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-400/10 dark:text-success-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                                        {{ __('Enabled') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        {{ __('Disabled') }}
                                    </span>
                                @endif
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Controlled via config') }}</span>
                            </div>
                        </div>
                    </x-slot>
                    <x-slot name="description">
                        {{ __('The MCP (Model Context Protocol) server allows AI agents like Claude Desktop, Cursor, and custom clients to interact with your application\'s entities.') }}
                    </x-slot>

                    @if(config('aicl.features.mcp'))
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Server URL') }}</dt>
                                <dd class="mt-1 flex items-center gap-2">
                                    <code class="text-sm font-mono text-gray-900 dark:text-white">{{ $this->getMcpUrl() }}</code>
                                    <button
                                        x-data="{}"
                                        x-on:click="navigator.clipboard.writeText('{{ $this->getMcpUrl() }}').then(() => $tooltip('Copied!', { timeout: 1500 }))"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <x-heroicon-o-clipboard-document class="h-4 w-4" />
                                    </button>
                                </dd>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Available Tools') }}</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">~{{ $this->getMcpToolCount() }}</dd>
                            </div>
                        </div>

                        {{-- Server Description (read-only from config) --}}
                        @if(config('aicl.mcp.server_info.description'))
                            <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Server Description') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ config('aicl.mcp.server_info.description') }}</dd>
                            </div>
                        @endif
                    @endif
                </x-filament::section>

                @if(config('aicl.features.mcp'))
                    {{-- Client Configuration --}}
                    <x-filament::section>
                        <x-slot name="heading">
                            {{ __('Client Configuration') }}
                        </x-slot>
                        <x-slot name="description">
                            {{ __('Use these snippets to connect AI agents to your MCP server.') }}
                        </x-slot>

                        <div class="space-y-4">
                            {{-- Claude Desktop --}}
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ __('Claude Desktop / claude_desktop_config.json') }}
                                </h4>
                                <div class="relative">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-x-auto text-sm font-mono"><code>{
  "mcpServers": {
    "{{ str(config('app.name', 'app'))->slug() }}": {
      "url": "{{ $this->getMcpUrl() }}",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN"
      }
    }
  }
}</code></pre>
                                    <button
                                        x-data="{}"
                                        x-on:click="navigator.clipboard.writeText(JSON.stringify({mcpServers: {'{{ str(config('app.name', 'app'))->slug() }}': {url: '{{ $this->getMcpUrl() }}', headers: {Authorization: 'Bearer YOUR_TOKEN'}}}}, null, 2)).then(() => $tooltip('Copied!', { timeout: 1500 }))"
                                        class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <x-heroicon-o-clipboard-document class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            {{-- .mcp.json --}}
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ __('Project .mcp.json') }}
                                </h4>
                                <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-x-auto text-sm font-mono"><code>{
  "mcpServers": {
    "{{ str(config('app.name', 'app'))->slug() }}": {
      "type": "url",
      "url": "{{ $this->getMcpUrl() }}",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN"
      }
    }
  }
}</code></pre>
                            </div>
                        </div>
                    </x-filament::section>

                    {{-- How it works --}}
                    <x-filament::section>
                        <x-slot name="heading">
                            {{ __('How It Works') }}
                        </x-slot>

                        <div class="prose prose-sm dark:prose-invert max-w-none">
                            <ol>
                                <li>{{ __('Create an API token with the "mcp" scope in the Access Tokens tab') }}</li>
                                <li>{{ __('Add the server configuration above to your AI client, replacing YOUR_TOKEN') }}</li>
                                <li>{{ __('Your AI agent will auto-discover all available tools for your application\'s entities') }}</li>
                            </ol>
                            <p class="text-gray-500 dark:text-gray-400">
                                {{ __('Each entity gets CRUD tools (list, show, create, update, delete) and state transition tools automatically. Authorization uses your existing roles and permissions.') }}
                            </p>
                        </div>
                    </x-filament::section>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
