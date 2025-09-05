<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_coupon_id',
        'customer_id',
        'pos_transaction_id',
        'discount_amount',
        'used_at'
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'used_at' => 'datetime'
    ];

    public function discountCoupon(): BelongsTo
    {
        return $this->belongsTo(DiscountCoupon::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class);
    }
}