@php
    $navigation = filament()->getNavigation();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="fi-layout flex min-h-screen w-full">
        {{-- Sidebar --}}
        <x-filament-panels::sidebar :navigation="$navigation" />
        
        {{-- Main content area with optimized spacing --}}
        <div class="fi-main flex w-0 flex-1 flex-col transition-all duration-200 lg:pl-64">
            {{-- Header --}}
            <x-filament-panels::header />
            
            {{-- Page content with minimal padding --}}
            <main class="fi-main-content flex-1 w-full px-4 py-6 sm:px-6 lg:px-6">
                <div class="mx-auto w-full max-w-none">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</x-filament-panels::layout.base>
