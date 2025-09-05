<div class="fi-layout flex min-h-screen w-full">
    <!-- Sidebar -->
    <aside class="fi-sidebar fixed inset-y-0 start-0 z-20 flex h-screen w-64 flex-col bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <x-filament-panels::sidebar />
    </aside>
    
    <!-- Main Content Area -->
    <div class="fi-main flex w-0 flex-1 flex-col lg:pl-64">
        <!-- Header -->
        <header class="fi-header flex h-16 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:px-6 lg:px-8">
            <x-filament-panels::header />
        </header>
        
        <!-- Page Content with optimized spacing -->
        <main class="fi-main-content flex-1 w-full max-w-none px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
</div>
