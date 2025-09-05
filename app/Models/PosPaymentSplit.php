<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PosPaymentSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_transaction_id',
        'payment_method',
        'amount',
        'reference_number', 
        'status',
        'payment_details',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_details' => 'array',
        'processed_at' => 'datetime'
    ];

    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class);
    }

    // Mark payment as completed
    public function markAsCompleted(string $referenceNumber = null): void
    {
        $this->update([
            'status' => 'completed',
            'reference_number' => $referenceNumber,
            'processed_at' => now()
        ]);
    }

    // Mark payment as failed
    public function markAsFailed(string $reason = null): void
    {
        $details = $this->payment_details ?? [];
        if ($reason) {
            $details['failure_reason'] = $reason;
        }
        
        $this->update([
            'status' => 'failed',
            'payment_details' => $details,
            'processed_at' => now()
        ]);
    }

    // Get payment method display name
    public function getPaymentMethodDisplayAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => 'Cash',
            'mpesa' => 'M-Pesa',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'gift_voucher' => 'Gift Voucher',
            'loyalty_points' => 'Loyalty Points',
            default => ucfirst($this->payment_method)
        };
    }

    // Get formatted amount
    public function getFormattedAmountAttribute(): string
    {
        return 'KES ' . number_format($this->amount, 2);
    }

    // Get payment methods
    public static function getPaymentMethods(): array
    {
        return [
            'cash' => 'Cash',
            'mpesa' => 'M-Pesa',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'gift_voucher' => 'Gift Voucher',
            'loyalty_points' => 'Loyalty Points'
        ];
    }

    // Get status options
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed'
        ];
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByPaymentMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }
}