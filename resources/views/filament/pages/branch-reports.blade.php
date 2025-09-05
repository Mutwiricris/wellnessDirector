<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Branch Reports Header -->
        <div class="bg-gradient-to-r from-purple-600 to-purple-800 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold">Branch Analytics & Reports</h2>
                    <p class="text-purple-100 mt-1">Comprehensive performance insights and reporting</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-purple-200">Current Branch</div>
                    <div class="text-lg font-semibold">
                        {{ \Filament\Facades\Filament::getTenant()?->name ?? 'All Branches' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Report Navigation -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg p-4 shadow-sm border hover:shadow-md transition-shadow cursor-pointer">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Performance Report</p>
                        <p class="text-lg font-bold text-gray-900">View Details</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border hover:shadow-md transition-shadow cursor-pointer">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Financial Report</p>
                        <p class="text-lg font-bold text-gray-900">Generate</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border hover:shadow-md transition-shadow cursor-pointer">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Staff Report</p>
                        <p class="text-lg font-bold text-gray-900">Analyze</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border hover:shadow-md transition-shadow cursor-pointer">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4h-8z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Client Report</p>
                        <p class="text-lg font-bold text-gray-900">Export</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Widgets -->
        <div class="space-y-6">
            @foreach ($this->getWidgets() as $widget)
                @livewire($widget, ['data' => $this->getWidgetData()], key($widget))
            @endforeach
        </div>

        <!-- Detailed Analytics Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Key Performance Indicators -->
            <div class="bg-white rounded-lg p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Key Performance Indicators</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Revenue Growth Rate</span>
                        <span class="text-sm font-medium text-green-600">+15.3%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: 77%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Customer Satisfaction</span>
                        <span class="text-sm font-medium text-blue-600">4.7/5.0</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: 94%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Staff Utilization</span>
                        <span class="text-sm font-medium text-purple-600">82%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-600 h-2 rounded-full" style="width: 82%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Service Quality Score</span>
                        <span class="text-sm font-medium text-yellow-600">88%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full" style="width: 88%"></div>
                    </div>
                </div>
            </div>

            <!-- Report Generation Tools -->
            <div class="bg-white rounded-lg p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Generation</h3>
                <div class="space-y-4">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900">Daily Performance Report</h4>
                                <p class="text-sm text-gray-600">Comprehensive daily operations summary</p>
                            </div>
                            <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
                                Generate
                            </button>
                        </div>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900">Monthly Financial Report</h4>
                                <p class="text-sm text-gray-600">Revenue, expenses, and profitability analysis</p>
                            </div>
                            <button class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                                Generate
                            </button>
                        </div>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900">Staff Performance Report</h4>
                                <p class="text-sm text-gray-600">Individual and team performance metrics</p>
                            </div>
                            <button class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700">
                                Generate
                            </button>
                        </div>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900">Custom Analytics Report</h4>
                                <p class="text-sm text-gray-600">Customizable metrics and date ranges</p>
                            </div>
                            <button class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-yellow-700">
                                Configure
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Reports History -->
        <div class="bg-white rounded-lg p-6 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Reports</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Report Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Period
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Generated
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                Financial Performance
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                July 2025
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                2 hours ago
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Ready
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="#" class="text-blue-600 hover:text-blue-900">Download</a>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                Staff Performance
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Week 30
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                1 day ago
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Ready
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="#" class="text-blue-600 hover:text-blue-900">Download</a>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                Client Analytics
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Q2 2025
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                3 days ago
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    Processing
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <span class="text-gray-400">Pending</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>