<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi fi-color-gray fi-theme-light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'Laravel') }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @filamentStyles
    @stack('styles')
</head>
<body class="fi-body fi-panel-app min-h-screen bg-gray-50 font-normal text-gray-950 antialiased dark:bg-gray-950 dark:text-white">
    <div class="fi-layout flex min-h-screen w-full overflow-x-clip">
        <!-- Sidebar -->
        <aside class="fi-sidebar fi-sidebar-open fixed inset-y-0 start-0 z-20 flex h-screen w-64 flex-col overflow-hidden bg-white shadow-sm ring-1 ring-gray-950/5 transition-all duration-300 dark:bg-gray-900 dark:ring-white/10 lg:z-0">
            {{ $sidebar ?? '' }}
        </aside>
        
        <!-- Main Content -->
        <div class="fi-main flex w-0 flex-1 flex-col overflow-hidden lg:pl-64">
            <!-- Header -->
            <header class="fi-header flex h-16 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:px-6 lg:px-8">
                {{ $header ?? '' }}
            </header>
            
            <!-- Page Content -->
            <main class="fi-main-content flex-1 w-full px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    
    @filamentScripts
    @stack('scripts')
</body>
</html>
