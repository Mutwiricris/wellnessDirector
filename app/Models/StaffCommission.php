<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class StaffCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'branch_id',
        'booking_id',
        'service_id',
        'commission_type',
        'commission_rate',
        'fixed_amount',
        'tiered_structure',
        'service_amount',
        'commission_amount',
        'tip_amount',
        'bonus_amount',
        'penalty_amount',
        'total_earning',
        'payment_status',
        'earned_date',
        'payment_date',
        'payment_method',
        'payment_reference',
        'bonuses',
        'penalties',
        'quality_multiplier',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'calculation_details',
        'is_recurring',
        'period_identifier'
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'service_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'total_earning' => 'decimal:2',
        'quality_multiplier' => 'decimal:2',
        'earned_date' => 'date',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'tiered_structure' => 'array',
        'bonuses' => 'array',
        'penalties' => 'array',
        'calculation_details' => 'array',
        'is_recurring' => 'boolean'
    ];

    // Relationships
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('earned_date', [$startDate, $endDate]);
    }

    public function scopeForPeriod($query, $period)
    {
        return $query->where('period_identifier', $period);
    }

    // Commission calculation methods
    public static function calculateCommission($booking, $staff, $commissionStructure = null)
    {
        $serviceAmount = $booking->total_amount;
        $commissionAmount = 0;
        $calculationDetails = [];

        // Get commission structure from staff or service
        $structure = $commissionStructure ?? self::getCommissionStructure($staff, $booking->service);
        
        switch ($structure['type']) {
            case 'percentage':
                $commissionAmount = $serviceAmount * ($structure['rate'] / 100);
                $calculationDetails = [
                    'type' => 'percentage',
                    'rate' => $structure['rate'],
                    'service_amount' => $serviceAmount,
                    'calculation' => "{$serviceAmount} * {$structure['rate']}% = {$commissionAmount}"
                ];
                break;

            case 'fixed':
                $commissionAmount = $structure['amount'];
                $calculationDetails = [
                    'type' => 'fixed',
                    'amount' => $structure['amount'],
                    'calculation' => "Fixed amount: {$commissionAmount}"
                ];
                break;

            case 'tiered':
                $commissionAmount = self::calculateTieredCommission($serviceAmount, $structure['tiers']);
                $calculationDetails = [
                    'type' => 'tiered',
                    'tiers' => $structure['tiers'],
                    'service_amount' => $serviceAmount,
                    'commission_amount' => $commissionAmount
                ];
                break;

            case 'hybrid':
                // Combination of fixed + percentage
                $baseAmount = $structure['fixed_amount'] ?? 0;
                $percentageAmount = $serviceAmount * (($structure['percentage_rate'] ?? 0) / 100);
                $commissionAmount = $baseAmount + $percentageAmount;
                $calculationDetails = [
                    'type' => 'hybrid',
                    'fixed_amount' => $baseAmount,
                    'percentage_rate' => $structure['percentage_rate'] ?? 0,
                    'percentage_amount' => $percentageAmount,
                    'total_commission' => $commissionAmount
                ];
                break;
        }

        // Apply quality multiplier based on performance
        $qualityMultiplier = self::getQualityMultiplier($staff, $booking);
        $finalCommission = $commissionAmount * $qualityMultiplier;

        return [
            'commission_type' => $structure['type'],
            'commission_rate' => $structure['rate'] ?? null,
            'fixed_amount' => $structure['amount'] ?? null,
            'tiered_structure' => $structure['tiers'] ?? null,
            'service_amount' => $serviceAmount,
            'commission_amount' => $finalCommission,
            'quality_multiplier' => $qualityMultiplier,
            'calculation_details' => $calculationDetails,
            'total_earning' => $finalCommission
        ];
    }

    private static function calculateTieredCommission($amount, $tiers)
    {
        $commission = 0;
        $remaining = $amount;

        foreach ($tiers as $tier) {
            $tierMin = $tier['min'] ?? 0;
            $tierMax = $tier['max'] ?? PHP_FLOAT_MAX;
            $tierRate = $tier['rate'] ?? 0;

            if ($remaining <= 0) break;

            $tierAmount = min($remaining, $tierMax - $tierMin);
            if ($tierAmount > 0 && $amount >= $tierMin) {
                $commission += $tierAmount * ($tierRate / 100);
                $remaining -= $tierAmount;
            }
        }

        return $commission;
    }

    private static function getCommissionStructure($staff, $service)
    {
        // Default commission structure - this would typically come from database
        // For now, using a simple percentage-based structure
        
        // Check if staff has custom commission rate
        if ($staff && isset($staff->commission_rate)) {
            return [
                'type' => 'percentage',
                'rate' => $staff->commission_rate
            ];
        }

        // Check if service has specific commission structure
        if ($service && isset($service->commission_rate)) {
            return [
                'type' => 'percentage',
                'rate' => $service->commission_rate
            ];
        }

        // Default structure
        return [
            'type' => 'percentage',
            'rate' => 25.00 // 25% default commission
        ];
    }

    private static function getQualityMultiplier($staff, $booking)
    {
        // Base multiplier
        $multiplier = 1.0;

        // Get recent performance metrics for this staff member
        $recentPerformance = StaffPerformance::where('staff_id', $staff->id)
            ->where('performance_date', '>=', now()->subDays(30))
            ->avg('average_rating');

        if ($recentPerformance) {
            if ($recentPerformance >= 4.8) {
                $multiplier = 1.15; // 15% bonus for excellent service
            } elseif ($recentPerformance >= 4.5) {
                $multiplier = 1.10; // 10% bonus for very good service
            } elseif ($recentPerformance >= 4.0) {
                $multiplier = 1.05; // 5% bonus for good service
            } elseif ($recentPerformance < 3.0) {
                $multiplier = 0.90; // 10% penalty for poor service
            }
        }

        return $multiplier;
    }

    // Payment processing methods
    public function markAsPaid($paymentMethod = null, $paymentReference = null)
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_date' => now(),
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference
        ]);
    }

    public function approve($approvedBy, $notes = null)
    {
        $this->update([
            'approval_status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    public function reject($approvedBy, $notes)
    {
        $this->update([
            'approval_status' => 'rejected',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    // Analytics methods
    public static function getTotalEarnings($staffId, $startDate = null, $endDate = null)
    {
        $query = self::where('staff_id', $staffId)
            ->where('payment_status', 'paid');

        if ($startDate && $endDate) {
            $query->whereBetween('earned_date', [$startDate, $endDate]);
        }

        return $query->sum('total_earning');
    }

    public static function getPendingCommissions($staffId)
    {
        return self::where('staff_id', $staffId)
            ->where('payment_status', 'pending')
            ->where('approval_status', 'approved')
            ->sum('total_earning');
    }

    public static function getCommissionSummary($staffId, $period = 'month')
    {
        $dateRange = match($period) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()]
        };

        $commissions = self::where('staff_id', $staffId)
            ->whereBetween('earned_date', $dateRange)
            ->get();

        return [
            'total_earned' => $commissions->sum('total_earning'),
            'total_paid' => $commissions->where('payment_status', 'paid')->sum('total_earning'),
            'total_pending' => $commissions->where('payment_status', 'pending')->sum('total_earning'),
            'total_services' => $commissions->count(),
            'average_commission' => $commissions->avg('commission_amount'),
            'total_tips' => $commissions->sum('tip_amount'),
            'total_bonuses' => $commissions->sum('bonus_amount'),
            'total_penalties' => $commissions->sum('penalty_amount')
        ];
    }

    public static function getTopEarners($branchId, $period = 'month', $limit = 10)
    {
        $dateRange = match($period) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()]
        };

        return self::with('staff')
            ->where('branch_id', $branchId)
            ->whereBetween('earned_date', $dateRange)
            ->selectRaw('
                staff_id,
                SUM(total_earning) as total_earnings,
                SUM(commission_amount) as total_commissions,
                SUM(tip_amount) as total_tips,
                COUNT(*) as total_services,
                AVG(commission_amount) as avg_commission
            ')
            ->groupBy('staff_id')
            ->orderBy('total_earnings', 'desc')
            ->limit($limit)
            ->get();
    }
}