<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_transaction_id',
        'checkout_request_id',
        'merchant_request_id',
        'phone_number',
        'amount',
        'mpesa_receipt_number',
        'transaction_date',
        'status',
        'result_code',
        'result_desc',
        'callback_data',
        'account_reference',
        'transaction_desc',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'callback_data' => 'array',
    ];

    // Transaction statuses
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_TIMEOUT = 'timeout';

    /**
     * Get the POS transaction that owns this M-Pesa transaction
     */
    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class);
    }

    /**
     * Check if transaction is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS && $this->result_code === '0';
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction failed
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_TIMEOUT]);
    }

    /**
     * Update transaction status from callback
     */
    public function updateFromCallback(array $callbackData): void
    {
        $resultCode = $callbackData['Body']['stkCallback']['ResultCode'] ?? null;
        $resultDesc = $callbackData['Body']['stkCallback']['ResultDesc'] ?? null;
        
        $this->update([
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'callback_data' => $callbackData,
            'status' => $this->determineStatusFromResultCode($resultCode),
        ]);

        // If successful, extract additional data
        if ($resultCode === '0') {
            $callbackMetadata = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];
            
            foreach ($callbackMetadata as $item) {
                switch ($item['Name']) {
                    case 'MpesaReceiptNumber':
                        $this->mpesa_receipt_number = $item['Value'];
                        break;
                    case 'TransactionDate':
                        $this->transaction_date = \Carbon\Carbon::createFromFormat('YmdHis', $item['Value']);
                        break;
                }
            }
            
            $this->save();
        }
    }

    /**
     * Determine status from result code
     */
    private function determineStatusFromResultCode(?string $resultCode): string
    {
        switch ($resultCode) {
            case '0':
                return self::STATUS_SUCCESS;
            case '1032':
                return self::STATUS_CANCELLED;
            case '1037':
                return self::STATUS_TIMEOUT;
            default:
                return self::STATUS_FAILED;
        }
    }

    /**
     * Scope for successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS)->where('result_code', '0');
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_TIMEOUT]);
    }
}
