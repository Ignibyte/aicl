<x-filament-panels::page.simple>
    <div class="fi-aicl-login-split flex min-h-screen w-full">
        {{-- Left Side: Form --}}
        <div class="flex w-full flex-col justify-center px-6 py-12 sm:px-12 lg:w-1/2 lg:px-20 xl:px-28">
            <div class="mx-auto w-full max-w-md">
                {{-- Ignibyte Logo --}}
                <div class="mb-10">
                    <x-aicl-ignibyte-logo size="md" />
                </div>

                {{-- Heading --}}
                <div class="mb-2">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white font-display">
                        {{ __('Sign in to your account') }}
                    </h2>
                </div>

                {{-- Subheading --}}
                <p class="mb-8 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Enter your details below to access your secure portal') }}
                </p>

                {{-- SAML SSO Button --}}
                @if($this->hasSamlLogin())
                    <div class="mb-6">
                        <a
                            href="{{ $this->getSamlRedirectUrl() }}"
                            class="flex w-full items-center justify-center gap-3 rounded-lg bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span>{{ __('Sign in with :idp', ['idp' => $this->getSamlIdpName()]) }}</span>
                        </a>
                    </div>

                    @if($this->hasSocialLogin())
                        {{-- Divider between SSO and social --}}
                        <div class="relative mb-6">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="bg-white px-4 text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                    {{ __('Or') }}
                                </span>
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Social Login --}}
                @if($this->hasSocialLogin())
                    <div class="mb-6 space-y-3">
                        @foreach($this->getSocialProviders() as $provider => $config)
                            <a
                                href="{{ $config['url'] }}"
                                class="flex w-full items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                @if($provider === 'google')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/>
                                    </svg>
                                @elseif($provider === 'github')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                    </svg>
                                @elseif($provider === 'microsoft')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.4 24H0V12.6h11.4V24zM24 24H12.6V12.6H24V24zM11.4 11.4H0V0h11.4v11.4zm12.6 0H12.6V0H24v11.4z"/>
                                    </svg>
                                @else
                                    <x-filament::icon :icon="$config['icon']" class="h-5 w-5" />
                                @endif
                                <span>{{ $config['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- Divider before email form --}}
                @if($this->hasSamlLogin() || $this->hasSocialLogin())
                    <div class="relative mb-6">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="bg-white px-4 text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                {{ __('Or continue with email') }}
                            </span>
                        </div>
                    </div>
                @endif

                {{-- Filament Login Form --}}
                {{ $this->content }}

                {{-- Registration Link (runtime check — respects admin settings toggle immediately) --}}
                @if(\Aicl\AiclPlugin::isRegistrationEnabled())
                    <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Don\'t have an account?') }}
                        <a href="{{ filament()->getRegistrationUrl() }}" class="font-medium text-primary-600 hover:text-primary-500">
                            {{ __('Register') }}
                        </a>
                    </p>
                @endif
            </div>
        </div>

        {{-- Right Side: Full-height gradient + glassmorphic logo (hidden on mobile) --}}
        <div class="relative hidden overflow-hidden lg:block lg:w-1/2">
            {{-- Background gradient --}}
            <div class="absolute inset-0 bg-gradient-to-br from-primary-500 via-orange-600 to-red-600 opacity-90"></div>

            {{-- Logo texture overlay — faded, blurred, scaled --}}
            <div class="absolute inset-0 bg-no-repeat bg-center opacity-5 mix-blend-overlay scale-[2] blur-sm"
                 style="background-image: url('{{ asset(config('aicl.theme.logo', 'vendor/aicl/images/logo.png')) }}')">
            </div>

            {{-- Grid texture overlay --}}
            <div class="absolute inset-0 opacity-20"
                 style="background-image: linear-gradient(rgba(0,0,0,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.1) 1px, transparent 1px); background-size: 40px 40px;">
            </div>

            {{-- Content --}}
            <div class="relative z-10 flex h-full w-full flex-col items-center justify-center space-y-6 p-12 text-center text-white">
                {{-- Glassmorphic logo container --}}
                <div class="mb-4 rounded-full border border-white/20 bg-white/10 p-8 shadow-2xl backdrop-blur-md">
                    <img
                        src="{{ asset(config('aicl.theme.logo', 'vendor/aicl/images/logo.png')) }}"
                        alt="{{ config('aicl.theme.brand_name', 'IGNIBYTE') }}"
                        class="h-24 w-auto drop-shadow-lg"
                    />
                </div>

                <h2 class="text-4xl font-bold tracking-wide drop-shadow-md font-display">
                    {{ __('Welcome Back!') }}
                </h2>

                <p class="max-w-md text-lg font-light leading-relaxed text-white/80">
                    {{ __('Access your secure dashboard, manage your projects, and monitor your systems with real-time analytics.') }}
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
