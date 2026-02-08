<div {{ $attributes->merge(['class' => 'flex min-h-screen']) }}>
    {{-- Left Side: Form Content --}}
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm lg:max-w-md">
            {{ $slot }}
        </div>
    </div>

    {{-- Right Side: Background Image (hidden on mobile) --}}
    <div class="relative hidden w-0 flex-1 lg:block">
        @if($backgroundImage)
            <img
                src="{{ $backgroundImage }}"
                alt=""
                class="absolute inset-0 h-full w-full object-cover"
            />
            <div class="absolute inset-0 bg-black/{{ $overlayOpacity }}"></div>

            @if(isset($overlay))
                <div class="absolute inset-0 flex items-center justify-center p-12">
                    {{ $overlay }}
                </div>
            @endif
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-primary-600 to-primary-900"></div>

            @if(isset($overlay))
                <div class="absolute inset-0 flex items-center justify-center p-12">
                    {{ $overlay }}
                </div>
            @endif
        @endif
    </div>
</div>
