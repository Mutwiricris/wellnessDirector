<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = [
        'name',
        'icon', 
        'slug',
        'sort_order',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer'
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    public function activeServices(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id')->where('status', 'active');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
