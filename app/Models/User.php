<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'user_type',
        'branch_id',
        'first_name',
        'last_name', 
        'phone',
        'date_of_birth',
        'gender',
        'allergies',
        'preferences',
        'marketing_consent',
        'create_account_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'preferences' => 'array',
            'marketing_consent' => 'boolean',
        ];
    }

    /**
     * Get the user's bookings
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    /**
     * Get the branch this user manages (for branch managers)
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: $this->name;
    }

    /**
     * Get formatted phone number
     */
    public function getFormattedPhoneAttribute(): string
    {
        if (!$this->phone) return '';
        
        $phone = preg_replace('/\D/', '', $this->phone);
        
        if (str_starts_with($phone, '254')) {
            return '+254 ' . substr($phone, 3, 3) . ' ' . substr($phone, 6, 3) . ' ' . substr($phone, 9, 3);
        } elseif (str_starts_with($phone, '0') && strlen($phone) === 10) {
            return substr($phone, 0, 4) . ' ' . substr($phone, 4, 3) . ' ' . substr($phone, 7, 3);
        }
        
        return $this->phone;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    /**
     * Check if user is staff
     */
    public function isStaff(): bool
    {
        return $this->user_type === 'staff';
    }

    /**
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->user_type === 'user';
    }

    /**
     * Check if user is branch manager
     */
    public function isBranchManager(): bool
    {
        return $this->user_type === 'branch_manager';
    }

    /**
     * Check if user is director
     */
    public function isDirector(): bool
    {
        return $this->user_type === 'director';
    }

    /**
     * Check if user can login to admin area
     */
    public function canAccessAdmin(): bool
    {
        return in_array($this->user_type, ['admin', 'staff', 'branch_manager']);
    }

    /**
     * Check if user can access branch management
     */
    public function canManageBranch(): bool
    {
        return $this->user_type === 'branch_manager' && $this->branch_id;
    }

    /**
     * Get the branch ID this user can manage
     */
    public function getManagedBranchId(): ?int
    {
        return $this->isBranchManager() ? $this->branch_id : null;
    }

    /**
     * Scope to get only users who can login
     */
    public function scopeCanLogin($query)
    {
        return $query->whereIn('user_type', ['admin', 'staff', 'branch_manager']);
    }

    /**
     * Scope to get only regular users (booking customers)
     */
    public function scopeCustomers($query)
    {
        return $query->where('user_type', 'user');
    }

    /**
     * Scope to get only branch managers
     */
    public function scopeBranchManagers($query)
    {
        return $query->where('user_type', 'branch_manager');
    }

    /**
     * Scope to get branch managers for a specific branch
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Allow branch managers and directors to access the admin panel
        if ($panel->getId() === 'director') {
            return in_array($this->user_type, ['branch_manager', 'director', 'admin']);
        }
        
        return false;
    }

    /**
     * Get the user's display name for Filament
     */
    public function getFilamentName(): string
    {
        return $this->getFullNameAttribute() ?: ($this->name ?: 'Unknown User');
    }

    /**
     * Get the user's avatar URL for Filament
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return null; // Will use default UI Avatars
    }

    /**
     * Get the tenants (branches) that this user can access
     */
    public function getTenants(Panel $panel): \Illuminate\Database\Eloquent\Collection
    {
        // Directors and admins can access all branches
        if (in_array($this->user_type, ['director', 'admin'])) {
            return Branch::active()->get();
        }
        
        // Branch managers can only access their assigned branch
        if ($this->user_type === 'branch_manager' && $this->branch_id) {
            return Branch::where('id', $this->branch_id)->active()->get();
        }
        
        return Branch::query()->whereRaw('1 = 0')->get(); // Empty collection
    }

    /**
     * Check if the user can access a specific tenant (branch)
     */
    public function canAccessTenant(Model $tenant): bool
    {
        // Ensure the tenant is a Branch model
        if (!$tenant instanceof Branch) {
            return false;
        }
        
        // Directors and admins can access all active branches
        if (in_array($this->user_type, ['director', 'admin'])) {
            return $tenant->status === 'active';
        }
        
        // Branch managers can only access their assigned branch
        if ($this->user_type === 'branch_manager') {
            return $this->branch_id === $tenant->id && $tenant->status === 'active';
        }
        
        return false;
    }
}
