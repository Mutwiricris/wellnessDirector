<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_secondary',
        'gender',
        'date_of_birth',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'allergies',
        'medical_conditions',
        'skin_type',
        'service_preferences',
        'communication_preferences',
        'profile_picture',
        'status',
        'email_verified',
        'email_verified_at',
        'phone_verified',
        'phone_verified_at',
        'client_type',
        'acquisition_source',
        'referral_source',
        'referral_code',
        'loyalty_points',
        'loyalty_tier',
        'total_spent',
        'visit_count',
        'last_visit_date',
        'no_show_count',
        'cancellation_count',
        'marketing_consent',
        'sms_consent',
        'email_consent',
        'call_consent',
        'preferred_communication_times',
        'notes',
        'internal_notes',
        'tags',
        'terms_accepted_at',
        'privacy_policy_accepted_at',
        'data_processing_consent'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_visit_date' => 'date',
        'terms_accepted_at' => 'datetime',
        'privacy_policy_accepted_at' => 'datetime',
        'allergies' => 'array',
        'medical_conditions' => 'array',
        'skin_type' => 'array',
        'service_preferences' => 'array',
        'communication_preferences' => 'array',
        'preferred_communication_times' => 'array',
        'tags' => 'array',
        'total_spent' => 'decimal:2',
        'email_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'marketing_consent' => 'boolean',
        'sms_consent' => 'boolean',
        'email_consent' => 'boolean',
        'call_consent' => 'boolean',
        'data_processing_consent' => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    public function isVip(): bool
    {
        return $this->client_type === 'vip' || $this->loyalty_tier === 'platinum' || $this->loyalty_tier === 'diamond';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByClientType($query, $type)
    {
        return $query->where('client_type', $type);
    }

    public function scopeByLoyaltyTier($query, $tier)
    {
        return $query->where('loyalty_tier', $tier);
    }
}