<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosReceipt extends Model
{
    protected $fillable = [
        'pos_transaction_id',
        'receipt_number',
        'receipt_type',
        'customer_email',
        'customer_phone',
        'delivery_method',
        'delivered',
        'delivered_at',
        'delivery_details',
        'receipt_data',
        'receipt_html',
        'receipt_pdf_path',
        'delivery_attempts',
        'last_delivery_attempt',
        'delivery_errors'
    ];

    protected $casts = [
        'delivered' => 'boolean',
        'delivered_at' => 'datetime',
        'delivery_details' => 'array',
        'receipt_data' => 'array',
        'delivery_attempts' => 'integer',
        'last_delivery_attempt' => 'datetime'
    ];

    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class);
    }

    public function scopeDelivered($query)
    {
        return $query->where('delivered', true);
    }

    public function scopePending($query)
    {
        return $query->where('delivered', false);
    }

    public function scopeByDeliveryMethod($query, string $method)
    {
        return $query->where('delivery_method', $method);
    }

    public function isDelivered(): bool
    {
        return $this->delivered;
    }

    public function canRetryDelivery(): bool
    {
        return !$this->delivered && $this->delivery_attempts < 3;
    }

    public function markAsDelivered(array $details = []): void
    {
        $this->update([
            'delivered' => true,
            'delivered_at' => now(),
            'delivery_details' => $details
        ]);
    }

    public function incrementDeliveryAttempts(string $error = null): void
    {
        $this->update([
            'delivery_attempts' => $this->delivery_attempts + 1,
            'last_delivery_attempt' => now(),
            'delivery_errors' => $error
        ]);
    }

    public function generateHtml(): string
    {
        $data = $this->receipt_data;
        
        return view('receipts.pos-receipt', [
            'receipt' => $this,
            'transaction' => $data['transaction'],
            'customer' => $data['customer'],
            'items' => $data['items'],
            'staff' => $data['staff'],
            'branch' => $data['branch']
        ])->render();
    }

    public function sendViaSms(): bool
    {
        if (!$this->customer_phone) {
            return false;
        }

        try {
            // SMS integration logic here
            // Using Africa's Talking or similar service
            
            $message = $this->getSmsMessage();
            
            // Send SMS (implementation depends on chosen provider)
            $sent = $this->sendSmsMessage($this->customer_phone, $message);
            
            if ($sent) {
                $this->markAsDelivered(['method' => 'sms', 'sent_at' => now()]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->incrementDeliveryAttempts($e->getMessage());
            return false;
        }
    }

    public function sendViaEmail(): bool
    {
        if (!$this->customer_email) {
            return false;
        }

        try {
            // Generate PDF if not exists
            if (!$this->receipt_pdf_path) {
                $this->generatePdf();
            }

            // Email sending logic
            \Mail::to($this->customer_email)->send(
                new \App\Mail\PosReceiptMail($this)
            );

            $this->markAsDelivered(['method' => 'email', 'sent_at' => now()]);
            return true;
        } catch (\Exception $e) {
            $this->incrementDeliveryAttempts($e->getMessage());
            return false;
        }
    }

    public function sendViaWhatsApp(): bool
    {
        if (!$this->customer_phone) {
            return false;
        }

        try {
            // WhatsApp Business API integration
            // Using 360dialog or similar service
            
            $message = $this->getWhatsAppMessage();
            
            $sent = $this->sendWhatsAppMessage($this->customer_phone, $message);
            
            if ($sent) {
                $this->markAsDelivered(['method' => 'whatsapp', 'sent_at' => now()]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->incrementDeliveryAttempts($e->getMessage());
            return false;
        }
    }

    public function deliver(): bool
    {
        if ($this->delivered) {
            return true;
        }

        // Try preferred delivery method first
        $method = $this->delivery_method ?? $this->determineDeliveryMethod();

        return match ($method) {
            'sms' => $this->sendViaSms(),
            'email' => $this->sendViaEmail(),
            'whatsapp' => $this->sendViaWhatsApp(),
            default => false
        };
    }

    private function determineDeliveryMethod(): string
    {
        if ($this->customer_phone) {
            return 'sms'; // Default to SMS for Kenyan market
        }
        
        if ($this->customer_email) {
            return 'email';
        }
        
        return 'sms';
    }

    private function getSmsMessage(): string
    {
        $data = $this->receipt_data;
        
        return "Thank you for visiting {$data['branch']}!\n" .
               "Receipt: {$this->receipt_number}\n" .
               "Amount: KES " . number_format($data['transaction']['total_amount'], 2) . "\n" .
               "Payment: {$data['transaction']['payment_method']}\n" .
               "Date: " . now()->format('d/m/Y H:i') . "\n" .
               "We appreciate your business!";
    }

    private function getWhatsAppMessage(): string
    {
        $data = $this->receipt_data;
        
        return "🧘‍♀️ *{$data['branch']} - Receipt*\n\n" .
               "📧 Receipt #: {$this->receipt_number}\n" .
               "💰 Amount: *KES " . number_format($data['transaction']['total_amount'], 2) . "*\n" .
               "💳 Payment: {$data['transaction']['payment_method']}\n" .
               "📅 Date: " . now()->format('d/m/Y H:i') . "\n\n" .
               "Thank you for choosing us! 🙏";
    }

    private function sendSmsMessage(string $phone, string $message): bool
    {
        // Implementation depends on SMS provider
        // This is a placeholder for the actual SMS sending logic
        return true;
    }

    private function sendWhatsAppMessage(string $phone, string $message): bool
    {
        // Implementation depends on WhatsApp Business API provider
        // This is a placeholder for the actual WhatsApp sending logic
        return true;
    }

    private function generatePdf(): void
    {
        // PDF generation logic using DomPDF or similar
        $html = $this->generateHtml();
        
        // Generate PDF and store path
        $pdfPath = 'receipts/' . $this->receipt_number . '.pdf';
        
        // Store PDF generation logic here
        
        $this->update(['receipt_pdf_path' => $pdfPath]);
    }

    public static function getDeliveryMethods(): array
    {
        return [
            'sms' => 'SMS',
            'email' => 'Email',
            'whatsapp' => 'WhatsApp'
        ];
    }

    public static function getReceiptTypes(): array
    {
        return [
            'digital' => 'Digital Only',
            'printed' => 'Printed Only',
            'both' => 'Digital & Printed'
        ];
    }
}