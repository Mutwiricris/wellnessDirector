<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class PosTransaction extends Model
{
    protected $fillable = [
        'transaction_number',
        'branch_id',
        'staff_id',
        'client_id',
        'booking_id',
        'transaction_type',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'tip_amount',
        'total_amount',
        'payment_status',
        'payment_method',
        'payment_details',
        'mpesa_transaction_id',
        'receipt_number',
        'receipt_sent',
        'notes',
        'customer_info',
        'completed_at',
        'coupon_discount_amount',
        'voucher_discount_amount',
        'loyalty_points_used',
        'loyalty_points_earned'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_details' => 'array',
        'customer_info' => 'array',
        'receipt_sent' => 'boolean',
        'completed_at' => 'datetime',
        'coupon_discount_amount' => 'decimal:2',
        'voucher_discount_amount' => 'decimal:2',
        'loyalty_points_used' => 'integer',
        'loyalty_points_earned' => 'integer'
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosTransactionItem::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PosReceipt::class);
    }

    public function paymentSplits(): HasMany
    {
        return $this->hasMany(PosPaymentSplit::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            $transaction->transaction_number = static::generateTransactionNumber();
        });
    }

    public static function generateTransactionNumber(): string
    {
        $prefix = 'POS';
        $date = now()->format('Ymd');
        $sequence = static::whereDate('created_at', today())->count() + 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function scopeCompleted($query)
    {
        return $query->where('payment_status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeByPaymentMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function isCompleted(): bool
    {
        return $this->payment_status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function canBeRefunded(): bool
    {
        return $this->isCompleted() && 
               $this->completed_at->gt(now()->subDays(7)); // 7 days refund window
    }

    public function getTotalWithoutTax(): float
    {
        return $this->subtotal - $this->discount_amount;
    }

    public function getCustomerName(): string
    {
        if ($this->client) {
            return $this->client->first_name . ' ' . $this->client->last_name;
        }

        if ($this->customer_info && isset($this->customer_info['name'])) {
            return $this->customer_info['name'];
        }

        return 'Walk-in Customer';
    }

    public function getCustomerPhone(): ?string
    {
        if ($this->client) {
            return $this->client->phone;
        }

        return $this->customer_info['phone'] ?? null;
    }

    public function getCustomerEmail(): ?string
    {
        if ($this->client) {
            return $this->client->email;
        }

        return $this->customer_info['email'] ?? null;
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'payment_status' => 'completed',
            'completed_at' => now()
        ]);

        // Update related booking if exists
        if ($this->booking) {
            $this->booking->update(['status' => 'completed']);
        }

        // Generate receipt
        $this->generateReceipt();
    }

    public function generateReceipt(): PosReceipt
    {
        return PosReceipt::create([
            'pos_transaction_id' => $this->id,
            'receipt_number' => $this->generateReceiptNumber(),
            'receipt_type' => 'digital',
            'customer_email' => $this->getCustomerEmail(),
            'customer_phone' => $this->getCustomerPhone(),
            'receipt_data' => $this->getReceiptData()
        ]);
    }

    private function generateReceiptNumber(): string
    {
        return 'RCP' . now()->format('Ymd') . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    private function getReceiptData(): array
    {
        return [
            'transaction' => $this->only([
                'transaction_number', 'total_amount', 'payment_method', 'created_at'
            ]),
            'customer' => [
                'name' => $this->getCustomerName(),
                'phone' => $this->getCustomerPhone(),
                'email' => $this->getCustomerEmail()
            ],
            'items' => $this->items->map(function ($item) {
                return [
                    'name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'staff' => $item->assignedStaff?->name
                ];
            }),
            'staff' => $this->staff->name,
            'branch' => $this->branch->name
        ];
    }

    public static function getDailySummary(int $branchId, Carbon $date): array
    {
        $transactions = static::where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->with('items')
            ->get();

        return [
            'total_transactions' => $transactions->count(),
            'completed_transactions' => $transactions->where('payment_status', 'completed')->count(),
            'total_revenue' => $transactions->where('payment_status', 'completed')->sum('total_amount'),
            'cash_sales' => $transactions->where('payment_method', 'cash')->where('payment_status', 'completed')->sum('total_amount'),
            'mpesa_sales' => $transactions->where('payment_method', 'mpesa')->where('payment_status', 'completed')->sum('total_amount'),
            'service_revenue' => $transactions->where('transaction_type', 'service')->where('payment_status', 'completed')->sum('total_amount'),
            'total_discounts' => $transactions->sum('discount_amount'),
            'total_tips' => $transactions->sum('tip_amount'),
            'avg_transaction_value' => $transactions->where('payment_status', 'completed')->avg('total_amount') ?? 0
        ];
    }

    public static function getPaymentMethods(): array
    {
        return [
            'cash' => 'Cash',
            'mpesa' => 'M-Pesa',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'mixed' => 'Mixed Payment'
        ];
    }

    public static function getTransactionTypes(): array
    {
        return [
            'service' => 'Service Only',
            'product' => 'Product Only',
            'package' => 'Service Package',
            'mixed' => 'Service + Product'
        ];
    }

    public static function getPaymentStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'refunded' => 'Refunded'
        ];
    }
}