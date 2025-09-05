<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Expense extends Model
{
    protected $fillable = [
        'branch_id',
        'category',
        'subcategory',
        'description',
        'amount',
        'payment_method',
        'vendor_name',
        'receipt_number',
        'expense_date',
        'status',
        'approved_by',
        'notes',
        'attachments',
        'is_recurring',
        'recurring_frequency',
        'next_due_date'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'next_due_date' => 'date',
        'attachments' => 'array',
        'is_recurring' => 'boolean'
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public static function getCategories(): array
    {
        return [
            'supplies' => 'Supplies & Products',
            'utilities' => 'Utilities',
            'rent' => 'Rent & Facilities',
            'marketing' => 'Marketing & Advertising',
            'staff' => 'Staff & Payroll',
            'equipment' => 'Equipment & Maintenance',
            'insurance' => 'Insurance',
            'licenses' => 'Licenses & Permits',
            'professional' => 'Professional Services',
            'miscellaneous' => 'Miscellaneous'
        ];
    }

    public static function getSubcategories(): array
    {
        return [
            'supplies' => ['oils', 'towels', 'creams', 'masks', 'tools', 'cleaning'],
            'utilities' => ['electricity', 'water', 'gas', 'internet', 'phone'],
            'rent' => ['rent', 'security', 'maintenance', 'cleaning'],
            'marketing' => ['social_media', 'print_ads', 'radio', 'promotions'],
            'staff' => ['salaries', 'bonuses', 'training', 'uniforms'],
            'equipment' => ['purchase', 'repair', 'maintenance', 'calibration'],
            'insurance' => ['liability', 'property', 'health', 'workers_comp'],
            'licenses' => ['business_license', 'health_permit', 'beauty_license'],
            'professional' => ['accounting', 'legal', 'consulting'],
            'miscellaneous' => ['office_supplies', 'petty_cash', 'other']
        ];
    }

    public function generateNextDueDate(): void
    {
        if (!$this->is_recurring || !$this->recurring_frequency) {
            return;
        }

        $baseDate = $this->next_due_date ?? $this->expense_date;

        $this->next_due_date = match ($this->recurring_frequency) {
            'weekly' => $baseDate->addWeek(),
            'monthly' => $baseDate->addMonth(),
            'quarterly' => $baseDate->addQuarter(),
            'yearly' => $baseDate->addYear(),
            default => null
        };

        $this->save();
    }

    public function createRecurringExpense(): self
    {
        if (!$this->is_recurring) {
            throw new \InvalidArgumentException('This expense is not set as recurring');
        }

        return static::create([
            'branch_id' => $this->branch_id,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'description' => $this->description . ' (Auto-generated)',
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'vendor_name' => $this->vendor_name,
            'expense_date' => $this->next_due_date,
            'status' => 'pending',
            'notes' => 'Auto-generated recurring expense',
            'is_recurring' => true,
            'recurring_frequency' => $this->recurring_frequency
        ]);
    }

    public static function getTotalByCategory(int $branchId, string $startDate, string $endDate): array
    {
        return static::where('branch_id', $branchId)
            ->approved()
            ->byDateRange($startDate, $endDate)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();
    }

    public static function getMonthlyTrend(int $branchId, int $months = 6): array
    {
        $result = [];
        $startDate = now()->subMonths($months);

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $total = static::where('branch_id', $branchId)
                ->approved()
                ->byDateRange($monthStart, $monthEnd)
                ->sum('amount');

            $result[] = [
                'month' => $monthStart->format('M Y'),
                'total' => $total
            ];
        }

        return $result;
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'KES ' . number_format($this->amount, 2);
    }

    public function getCategoryLabelAttribute(): string
    {
        return static::getCategories()[$this->category] ?? ucfirst($this->category);
    }
}