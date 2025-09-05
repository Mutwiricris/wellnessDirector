<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Cross-Branch Analytics Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold">Cross-Branch Analytics</h2>
                    <p class="text-indigo-100 mt-1">Compare performance across all spa locations</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-indigo-200">Analyzing</div>
                    <div class="text-lg font-semibold">
                        {{ count($this->selectedBranches ?? []) }} Branches
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white rounded-lg p-6 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Analysis Filters</h3>
            {{ $this->form }}
        </div>

        <!-- Branch Comparison Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Top Performing Branch</p>
                        <p class="text-xl font-bold text-gray-900" id="top-branch">Loading...</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Average Branch Revenue</p>
                        <p class="text-xl font-bold text-gray-900" id="avg-revenue">Loading...</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Network Revenue</p>
                        <p class="text-xl font-bold text-gray-900" id="total-revenue">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Widgets -->
        <div class="space-y-6">
            @foreach ($this->getWidgets() as $widget)
                @livewire($widget, ['data' => $this->getWidgetData()], key($widget))
            @endforeach
        </div>

        <!-- Branch Performance Matrix -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Performance Rankings -->
            <div class="bg-white rounded-lg p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Branch Performance Rankings</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center text-sm font-bold">1</div>
                            <div class="ml-3">
                                <p class="font-medium text-gray-900">Downtown Branch</p>
                                <p class="text-sm text-gray-600">Revenue: KES 2,450,000</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-green-600">+18.5%</p>
                            <p class="text-xs text-gray-500">vs last month</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-bold">2</div>
                            <div class="ml-3">
                                <p class="font-medium text-gray-900">Westlands Branch</p>
                                <p class="text-sm text-gray-600">Revenue: KES 2,180,000</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-blue-600">+12.3%</p>
                            <p class="text-xs text-gray-500">vs last month</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-yellow-600 text-white rounded-full flex items-center justify-center text-sm font-bold">3</div>
                            <div class="ml-3">
                                <p class="font-medium text-gray-900">Karen Branch</p>
                                <p class="text-sm text-gray-600">Revenue: KES 1,920,000</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-yellow-600">+8.7%</p>
                            <p class="text-xs text-gray-500">vs last month</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-gray-600 text-white rounded-full flex items-center justify-center text-sm font-bold">4</div>
                            <div class="ml-3">
                                <p class="font-medium text-gray-900">Kilimani Branch</p>
                                <p class="text-sm text-gray-600">Revenue: KES 1,680,000</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-red-600">-2.1%</p>
                            <p class="text-xs text-gray-500">vs last month</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Insights -->
            <div class="bg-white rounded-lg p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Strategic Insights</h3>
                <div class="space-y-4">
                    <div class="border-l-4 border-green-500 pl-4">
                        <h4 class="font-medium text-gray-900">Revenue Growth Opportunity</h4>
                        <p class="text-sm text-gray-600 mt-1">
                            Downtown Branch shows 18.5% growth - consider replicating successful strategies across other locations.
                        </p>
                    </div>
                    
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h4 class="font-medium text-gray-900">Staff Utilization Optimization</h4>
                        <p class="text-sm text-gray-600 mt-1">
                            Westlands Branch has 95% staff utilization - potential for capacity expansion or staff redistribution.
                        </p>
                    </div>
                    
                    <div class="border-l-4 border-yellow-500 pl-4">
                        <h4 class="font-medium text-gray-900">Service Mix Analysis</h4>
                        <p class="text-sm text-gray-600 mt-1">
                            Premium services generate 40% higher margins - opportunity to promote at underperforming branches.
                        </p>
                    </div>
                    
                    <div class="border-l-4 border-red-500 pl-4">
                        <h4 class="font-medium text-gray-900">Performance Alert</h4>
                        <p class="text-sm text-gray-600 mt-1">
                            Kilimani Branch showing decline - requires immediate attention and support.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparative Analysis Tools -->
        <div class="bg-white rounded-lg p-6 shadow-sm border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Comparative Analysis Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button class="p-4 border border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-colors">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h4 class="font-medium text-gray-900">Revenue Comparison</h4>
                        <p class="text-sm text-gray-600 mt-1">Compare revenue streams across branches</p>
                    </div>
                </button>
                
                <button class="p-4 border border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition-colors">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h4 class="font-medium text-gray-900">Staff Performance</h4>
                        <p class="text-sm text-gray-600 mt-1">Analyze staff metrics across locations</p>
                    </div>
                </button>
                
                <button class="p-4 border border-gray-200 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-colors">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4h-8z"></path>
                            </svg>
                        </div>
                        <h4 class="font-medium text-gray-900">Customer Analytics</h4>
                        <p class="text-sm text-gray-600 mt-1">Compare customer behavior patterns</p>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh analytics data every 2 minutes
        setInterval(function() {
            // Update quick metrics
            fetch('/api/cross-branch-metrics')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('top-branch').textContent = data.top_branch || 'Loading...';
                    document.getElementById('avg-revenue').textContent = 'KES ' + (data.avg_revenue || 0).toLocaleString();
                    document.getElementById('total-revenue').textContent = 'KES ' + (data.total_revenue || 0).toLocaleString();
                })
                .catch(error => console.log('Error updating metrics:', error));
        }, 120000);
    </script>
</x-filament-panels::page>