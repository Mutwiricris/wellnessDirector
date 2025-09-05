<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AnalyticsData extends Model
{
    protected $fillable = [
        'branch_id',
        'date',
        'total_revenue',
        'service_revenue',
        'product_revenue',
        'commission_paid',
        'expenses',
        'net_profit',
        'average_bill_value',
        'total_bookings',
        'completed_bookings',
        'cancelled_bookings',
        'no_show_bookings',
        'online_bookings',
        'walk_in_bookings',
        'booking_conversion_rate',
        'cancellation_rate',
        'new_clients',
        'returning_clients',
        'total_unique_clients',
        'client_retention_rate',
        'client_satisfaction_score',
        'active_staff',
        'staff_utilization_rate',
        'average_staff_rating',
        'staff_efficiency_score',
        'products_sold',
        'inventory_turnover_rate',
        'low_stock_items',
        'campaign_bookings',
        'marketing_roi',
        'referral_bookings',
        'discount_amount',
        'peak_hour_utilization',
        'equipment_usage_hours',
        'cost_per_service',
        'profit_margin',
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
        'service_revenue' => 'decimal:2',
        'product_revenue' => 'decimal:2',
        'commission_paid' => 'decimal:2',
        'expenses' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'average_bill_value' => 'decimal:2',
        'booking_conversion_rate' => 'decimal:2',
        'cancellation_rate' => 'decimal:2',
        'client_retention_rate' => 'decimal:2',
        'client_satisfaction_score' => 'decimal:2',
        'staff_utilization_rate' => 'decimal:2',
        'average_staff_rating' => 'decimal:2',
        'staff_efficiency_score' => 'decimal:2',
        'inventory_turnover_rate' => 'decimal:2',
        'marketing_roi' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'peak_hour_utilization' => 'decimal:2',
        'cost_per_service' => 'decimal:2',
        'profit_margin' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function generateDailyAnalytics(int $branchId, Carbon $date = null): self
    {
        $date = $date ?? today();
        
        $analytics = self::firstOrNew([
            'branch_id' => $branchId,
            'date' => $date
        ]);

        $bookings = Booking::where('branch_id', $branchId)
            ->whereDate('appointment_date', $date);

        $payments = Payment::where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->where('status', 'completed');

        $clients = User::whereHas('bookings', function($query) use ($branchId, $date) {
            $query->where('branch_id', $branchId)
                  ->whereDate('appointment_date', $date);
        });

        // Revenue metrics
        $analytics->total_revenue = $payments->sum('amount');
        $analytics->service_revenue = $bookings->clone()->where('status', 'completed')->sum('total_amount');
        $analytics->average_bill_value = $bookings->clone()->where('status', 'completed')->avg('total_amount') ?? 0;

        // Booking metrics
        $analytics->total_bookings = $bookings->count();
        $analytics->completed_bookings = $bookings->clone()->where('status', 'completed')->count();
        $analytics->cancelled_bookings = $bookings->clone()->where('status', 'cancelled')->count();
        $analytics->no_show_bookings = $bookings->clone()->where('status', 'no_show')->count();
        
        if ($analytics->total_bookings > 0) {
            $analytics->cancellation_rate = ($analytics->cancelled_bookings / $analytics->total_bookings) * 100;
        }

        // Client metrics
        $analytics->total_unique_clients = $clients->distinct('id')->count();
        $analytics->new_clients = $clients->whereDate('created_at', $date)->count();
        $analytics->returning_clients = $analytics->total_unique_clients - $analytics->new_clients;

        // Staff metrics
        $analytics->active_staff = Staff::where('branch_id', $branchId)
            ->where('status', 'active')
            ->count();

        $analytics->save();

        return $analytics;
    }

    public static function getRevenueGrowth(int $branchId, Carbon $startDate, Carbon $endDate): array
    {
        $data = self::where('branch_id', $branchId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $labels = [];
        $revenues = [];

        foreach ($data as $record) {
            $labels[] = $record->date->format('M j');
            $revenues[] = $record->total_revenue;
        }

        return [
            'labels' => $labels,
            'data' => $revenues
        ];
    }

    public static function getTopPerformingMetrics(int $branchId, Carbon $startDate, Carbon $endDate): array
    {
        $data = self::where('branch_id', $branchId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        return [
            'total_revenue' => $data->sum('total_revenue'),
            'total_bookings' => $data->sum('total_bookings'),
            'completed_bookings' => $data->sum('completed_bookings'),
            'average_completion_rate' => $data->avg('booking_conversion_rate'),
            'average_client_retention' => $data->avg('client_retention_rate'),
            'total_new_clients' => $data->sum('new_clients'),
            'average_staff_utilization' => $data->avg('staff_utilization_rate'),
        ];
    }
}