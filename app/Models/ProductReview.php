<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'customer_id',
        'order_id',
        'rating',
        'title',
        'comment',
        'images',
        'status',
        'is_verified_purchase',
        'reviewed_at'
    ];

    protected $casts = [
        'images' => 'array',
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'reviewed_at' => 'datetime'
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeVerifiedPurchases($query)
    {
        return $query->where('is_verified_purchase', true);
    }

    // Helper methods
    public function getStatusDisplayAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->status)
        };
    }

    public function getRatingStarsAttribute()
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    public function isPositive()
    {
        return $this->rating >= 4;
    }

    public function isNegative()
    {
        return $this->rating <= 2;
    }

    public function isNeutral()
    {
        return $this->rating === 3;
    }

    public function canBeApproved()
    {
        return $this->status === 'pending';
    }

    public function canBeRejected()
    {
        return $this->status === 'pending';
    }

    public function approve()
    {
        $this->update(['status' => 'approved']);
    }

    public function reject()
    {
        $this->update(['status' => 'rejected']);
    }

    public function getHelpfulnessScoreAttribute()
    {
        // This could be extended to track helpful votes
        $score = 0;
        
        if ($this->is_verified_purchase) {
            $score += 10;
        }
        
        if (strlen($this->comment) > 50) {
            $score += 5;
        }
        
        if ($this->images && count($this->images) > 0) {
            $score += 5;
        }
        
        return $score;
    }
}