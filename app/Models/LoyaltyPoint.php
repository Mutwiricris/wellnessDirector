<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoyaltyPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'branch_id',
        'transaction_type',
        'points',
        'monetary_value',
        'pos_transaction_id',
        'reference_type',
        'reference_id',
        'description',
        'expiry_date',
        'status'
    ];

    protected $casts = [
        'monetary_value' => 'decimal:2',
        'expiry_date' => 'date'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class);
    }

    // Award points to customer for a purchase
    public static function awardPoints(
        int $customerId, 
        int $branchId, 
        float $purchaseAmount, 
        ?int $posTransactionId = null,
        float $pointsPerKes = 0.01
    ): self {
        $points = (int) floor($purchaseAmount * $pointsPerKes);
        $expiryDate = now()->addYear(); // Points expire after 1 year
        
        return self::create([
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'transaction_type' => 'earned',
            'points' => $points,
            'monetary_value' => $purchaseAmount,
            'pos_transaction_id' => $posTransactionId,
            'reference_type' => 'purchase',
            'description' => "Earned {$points} points from purchase of KES " . number_format($purchaseAmount, 2),
            'expiry_date' => $expiryDate,
            'status' => 'active'
        ]);
    }

    // Redeem points for discount
    public static function redeemPoints(
        int $customerId,
        int $branchId,
        int $pointsToRedeem,
        ?int $posTransactionId = null,
        float $pointValue = 1.0 // 1 point = 1 KES
    ): float {
        $availablePoints = self::getAvailablePoints($customerId, $branchId);
        
        if ($availablePoints < $pointsToRedeem) {
            throw new \Exception("Insufficient points. Available: {$availablePoints}, Requested: {$pointsToRedeem}");
        }

        $discountAmount = $pointsToRedeem * $pointValue;
        
        // Create redemption record
        self::create([
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'transaction_type' => 'redeemed',
            'points' => -$pointsToRedeem, // Negative for redemption
            'monetary_value' => $discountAmount,
            'pos_transaction_id' => $posTransactionId,
            'reference_type' => 'redemption',
            'description' => "Redeemed {$pointsToRedeem} points for KES " . number_format($discountAmount, 2) . " discount",
            'status' => 'used'
        ]);

        return $discountAmount;
    }

    // Get available points for customer
    public static function getAvailablePoints(int $customerId, int $branchId): int
    {
        return self::where('customer_id', $customerId)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->where(function($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->sum('points');
    }

    // Get customer's points balance
    public static function getPointsBalance(int $customerId, int $branchId): array
    {
        $earnedPoints = self::where('customer_id', $customerId)
            ->where('branch_id', $branchId)
            ->where('transaction_type', 'earned')
            ->where('status', 'active')
            ->sum('points');

        $redeemedPoints = abs(self::where('customer_id', $customerId)
            ->where('branch_id', $branchId)
            ->where('transaction_type', 'redeemed')
            ->sum('points'));

        $expiredPoints = self::where('customer_id', $customerId)
            ->where('branch_id', $branchId)
            ->where('status', 'expired')
            ->sum('points');

        $availablePoints = self::getAvailablePoints($customerId, $branchId);

        return [
            'earned' => $earnedPoints,
            'redeemed' => $redeemedPoints,
            'expired' => $expiredPoints,
            'available' => $availablePoints,
            'monetary_value' => $availablePoints * 1.0 // 1 point = 1 KES
        ];
    }

    // Award bonus points for special occasions
    public static function awardBonusPoints(
        int $customerId,
        int $branchId,
        int $points,
        string $reason,
        ?string $referenceType = 'bonus',
        ?string $referenceId = null
    ): self {
        $expiryDate = now()->addYear();
        
        return self::create([
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'transaction_type' => 'bonus',
            'points' => $points,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $reason,
            'expiry_date' => $expiryDate,
            'status' => 'active'
        ]);
    }

    // Expire old points (scheduled job)
    public static function expireOldPoints(): int
    {
        $expiredCount = self::where('status', 'active')
            ->where('expiry_date', '<', now())
            ->update(['status' => 'expired']);

        return $expiredCount;
    }

    // Mark specific points as expired
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    // Get transaction types
    public static function getTransactionTypes(): array
    {
        return [
            'earned' => 'Points Earned',
            'redeemed' => 'Points Redeemed',
            'expired' => 'Points Expired',
            'bonus' => 'Bonus Points',
            'penalty' => 'Points Penalty'
        ];
    }

    // Get status options
    public static function getStatusOptions(): array
    {
        return [
            'active' => 'Active',
            'expired' => 'Expired',
            'used' => 'Used'
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where(function($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>', now());
                    });
    }

    public function scopeEarned($query)
    {
        return $query->where('transaction_type', 'earned');
    }

    public function scopeRedeemed($query)
    {
        return $query->where('transaction_type', 'redeemed');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
                    ->orWhere('expiry_date', '<', now());
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    // Get formatted points
    public function getFormattedPointsAttribute(): string
    {
        $sign = $this->points >= 0 ? '+' : '';
        return $sign . number_format(abs($this->points)) . ' pts';
    }

    // Get formatted monetary value
    public function getFormattedMonetaryValueAttribute(): string
    {
        if ($this->monetary_value === null) return 'N/A';
        return 'KES ' . number_format($this->monetary_value, 2);
    }

    // Check if points are expiring soon
    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date->diffInDays(now()) <= $days;
    }

    // Get days until expiry
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) return null;
        return $this->expiry_date->diffInDays(now(), false);
    }
}