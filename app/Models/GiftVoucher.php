<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class GiftVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'voucher_code',
        'voucher_type',
        'original_amount',
        'remaining_amount',
        'applicable_services',
        'recipient_name',
        'recipient_phone',
        'recipient_email',
        'purchaser_name',
        'purchaser_phone',
        'purchaser_email',
        'message',
        'purchase_date',
        'expiry_date',
        'status',
        'sold_by_staff_id',
        'commission_amount',
        'redemption_history',
        'last_used_at'
    ];

    protected $casts = [
        'applicable_services' => 'array',
        'redemption_history' => 'array',
        'purchase_date' => 'date',
        'expiry_date' => 'date',
        'last_used_at' => 'datetime',
        'original_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2'
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function soldByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'sold_by_staff_id');
    }

    public function posTransactionItems(): HasMany
    {
        return $this->hasMany(PosTransactionItem::class, 'gift_voucher_id');
    }

    // Generate unique voucher code
    public static function generateVoucherCode(): string
    {
        do {
            $code = 'GV' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        } while (self::where('voucher_code', $code)->exists());
        
        return $code;
    }

    // Check if voucher is valid for use
    public function isValid(): bool
    {
        return $this->status === 'active' 
            && $this->remaining_amount > 0 
            && $this->expiry_date->isFuture();
    }

    // Check if voucher is expired
    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    // Check if voucher can be applied to specific services
    public function canApplyToServices(array $serviceIds): bool
    {
        if ($this->voucher_type === 'monetary') {
            return true; // Monetary vouchers can apply to any service
        }

        if ($this->voucher_type === 'service') {
            return !empty(array_intersect($serviceIds, $this->applicable_services ?? []));
        }

        return false;
    }

    // Apply voucher to transaction
    public function applyToTransaction(float $amount): float
    {
        if (!$this->isValid()) {
            throw new \Exception('Voucher is not valid for use');
        }

        $discountAmount = min($amount, $this->remaining_amount);
        
        // Update remaining amount
        $this->remaining_amount -= $discountAmount;
        
        // Update redemption history
        $history = $this->redemption_history ?? [];
        $history[] = [
            'amount_used' => $discountAmount,
            'used_at' => now(),
            'remaining_after' => $this->remaining_amount
        ];
        $this->redemption_history = $history;
        
        // Update status and last used
        if ($this->remaining_amount <= 0) {
            $this->status = 'redeemed';
        }
        $this->last_used_at = now();
        
        $this->save();
        
        return $discountAmount;
    }

    // Mark voucher as expired (scheduled job)
    public function markAsExpired(): void
    {
        if ($this->isExpired() && $this->status === 'active') {
            $this->update(['status' => 'expired']);
        }
    }

    // Get voucher types
    public static function getVoucherTypes(): array
    {
        return [
            'monetary' => 'Monetary Value',
            'service' => 'Specific Services',
            'package' => 'Service Package'
        ];
    }

    // Get status options
    public static function getStatusOptions(): array
    {
        return [
            'active' => 'Active',
            'redeemed' => 'Fully Redeemed',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled'
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
                    ->where('status', 'active');
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    // Get formatted remaining amount
    public function getFormattedRemainingAmountAttribute(): string
    {
        return 'KES ' . number_format($this->remaining_amount, 2);
    }

    // Get usage percentage
    public function getUsagePercentageAttribute(): float
    {
        if ($this->original_amount <= 0) return 0;
        
        $used = $this->original_amount - $this->remaining_amount;
        return ($used / $this->original_amount) * 100;
    }

    // Get days until expiry
    public function getDaysUntilExpiryAttribute(): int
    {
        return $this->expiry_date->diffInDays(now(), false);
    }
}