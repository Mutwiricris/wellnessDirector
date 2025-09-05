<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'amount',
        'payment_method',
        'branch_id',
        'transaction_reference',
        'mpesa_checkout_request_id',
        'mpesa_transaction_id',
        'status',
        'processed_at',
        'notes',
        'refund_amount',
        'refunded_at',
        'gateway_response',
        'card_last_four',
        'card_brand',
        'processing_fee',
        'net_amount',
        'bank_reference',
        'authorization_code',
        'payment_channel',
        'customer_id',
        'staff_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
    

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now()
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'processed_at' => now()
        ]);
    }

    public function refund(?float $amount = null, ?string $reason = null): bool
    {
        if (!$this->isCompleted()) {
            return false;
        }

        $refundAmount = $amount ?? $this->amount;
        
        if ($refundAmount > $this->amount) {
            return false;
        }

        $this->update([
            'status' => 'refunded',
            'refund_amount' => $refundAmount,
            'refunded_at' => now(),
            'notes' => $reason
        ]);

        return true;
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'customer_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Staff::class);
    }

    public function getPaymentMethodDisplayName(): string
    {
        return match ($this->payment_method) {
            'mpesa' => 'M-Pesa',
            'card' => 'Credit/Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
            'gift_voucher' => 'Gift Voucher',
            'loyalty_points' => 'Loyalty Points',
            default => ucfirst(str_replace('_', ' ', $this->payment_method))
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            'refunded' => 'gray',
            default => 'gray'
        };
    }

    public function calculateProcessingFee(): float
    {
        $config = config('payments.payment_methods.' . $this->payment_method, []);
        
        if (isset($config['processing_fee_percentage'])) {
            return $this->amount * ($config['processing_fee_percentage'] / 100);
        }
        
        if (isset($config['processing_fee'])) {
            return $config['processing_fee'];
        }
        
        return 0;
    }

    public function getNetAmount(): float
    {
        return $this->amount - ($this->processing_fee ?? 0);
    }

    public function getPaymentMethodIcon(): string
    {
        return match ($this->payment_method) {
            'mpesa' => 'heroicon-o-device-phone-mobile',
            'card' => 'heroicon-o-credit-card',
            'bank_transfer' => 'heroicon-o-building-library',
            'cash' => 'heroicon-o-banknotes',
            'gift_voucher' => 'heroicon-o-gift',
            'loyalty_points' => 'heroicon-o-star',
            default => 'heroicon-o-currency-dollar'
        };
    }

    public function requiresAuthorization(): bool
    {
        $config = config('payments.payment_methods.' . $this->payment_method, []);
        return $config['requires_authorization'] ?? false;
    }

    public function isCardPayment(): bool
    {
        return $this->payment_method === 'card';
    }

    public function isBankTransfer(): bool
    {
        return $this->payment_method === 'bank_transfer';
    }

    public function isMpesaPayment(): bool
    {
        return $this->payment_method === 'mpesa';
    }

    public function isCashPayment(): bool
    {
        return $this->payment_method === 'cash';
    }

    public function getFormattedAmount(): string
    {
        return 'KES ' . number_format($this->amount, 2);
    }

    public function getFormattedProcessingFee(): string
    {
        return 'KES ' . number_format($this->processing_fee ?? 0, 2);
    }

    public function getFormattedNetAmount(): string
    {
        return 'KES ' . number_format($this->getNetAmount(), 2);
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

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByPaymentMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }
}
