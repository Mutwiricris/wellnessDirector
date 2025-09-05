<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Financial Summary Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold">Financial Command Center</h2>
                    <p class="text-blue-100 mt-1">Complete financial oversight and analytics</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-blue-200">Last Updated</div>
                    <div class="text-lg font-semibold">{{ now()->format('M j, Y H:i') }}</div>
                </div>
            </div>
        </div>

        <!-- Quick Financial Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Today's Revenue</p>
                        <p class="text-2xl font-bold text-gray-900" id="today-revenue">Loading...</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Payments</p>
                        <p class="text-2xl font-bold text-gray-900" id="pending-payments">Loading...</p>
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
                        <p class="text-sm font-medium text-gray-600">Monthly Transactions</p>
                        <p class="text-2xl font-bold text-gray-900" id="monthly-transactions">Loading...</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow-sm border">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Profit Margin</p>
                        <p class="text-2xl font-bold text-gray-900" id="profit-margin">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Widgets -->
        <div class="space-y-6">
            @foreach ($this->getWidgets() as $widget)
                @livewire($widget, ['data' => $this->getWidgetData()], key($widget))
            @endforeach
        </div>

        <!-- Financial Insights Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Payment Method Breakdown -->
            <div class="bg-white rounded-lg p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Method Performance</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">M-Pesa</span>
                        <div class="text-right">
                            <span class="text-sm font-medium">45%</span>
                            <div class="text-xs text-gray-500">No fees</div>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: 45%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Cash</span>
                        <div class="text-right">
                            <span class="text-sm font-medium">30%</span>
                            <div class="text-xs text-gray-500">No fees</div>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: 30%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Cards</span>
                        <div class="text-right">
                            <span class="text-sm font-medium">20%</span>
                            <div class="text-xs text-red-500">3.5% fee</div>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-red-600 h-2 rounded-full" style="width: 20%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Bank Transfer</span>
                        <div class="text-right">
                            <span class="text-sm font-medium">5%</span>
                            <div class="text-xs text-red-500">KES 25 fee</div>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full" style="width: 5%"></div>
                    </div>
                </div>
            </div>

            <!-- Financial Alerts -->
            <div class="bg-white rounded-lg p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Financial Alerts & Recommendations</h3>
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-2 h-2 bg-green-400 rounded-full mt-2"></div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-gray-900 font-medium">Revenue Target On Track</p>
                            <p class="text-xs text-gray-500">Monthly target 85% achieved</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-2 h-2 bg-yellow-400 rounded-full mt-2"></div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-gray-900 font-medium">High Card Processing Fees</p>
                            <p class="text-xs text-gray-500">Consider promoting M-Pesa payments</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-2 h-2 bg-red-400 rounded-full mt-2"></div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-gray-900 font-medium">Expense Budget Variance</p>
                            <p class="text-xs text-gray-500">Supplies 15% over budget this month</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-2 h-2 bg-blue-400 rounded-full mt-2"></div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-gray-900 font-medium">Staff Commission Pending</p>
                            <p class="text-xs text-gray-500">12 commission payments awaiting approval</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh financial metrics every 60 seconds
        setInterval(function() {
            // Update quick metrics
            fetch('/api/financial-metrics')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('today-revenue').textContent = 'KES ' + data.today_revenue.toLocaleString();
                    document.getElementById('pending-payments').textContent = 'KES ' + data.pending_payments.toLocaleString();
                    document.getElementById('monthly-transactions').textContent = data.monthly_transactions.toLocaleString();
                    document.getElementById('profit-margin').textContent = data.profit_margin.toFixed(1) + '%';
                })
                .catch(error => console.log('Error updating metrics:', error));
        }, 60000);
    </script>
</x-filament-panels::page>