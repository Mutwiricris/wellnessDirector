<?php

namespace App\Filament\Resources\PosTransactionResource\Pages;

use App\Filament\Resources\PosTransactionResource;
use App\Models\Service;
use App\Models\InventoryItem;
use App\Models\Staff;
use App\Models\PosTransaction;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

class PosInterface extends Page
{
    protected static string $resource = PosTransactionResource::class;

    protected static string $view = 'filament.resources.pos-transaction-resource.pages.pos-interface';

    protected static ?string $title = 'Point of Sale';

    protected static ?string $navigationLabel = 'POS Terminal';

    protected static ?string $navigationIcon = 'heroicon-o-device-tablet';

    protected static ?int $navigationSort = 1;

    public array $cart = [];
    public array $customerData = [];
    public ?int $selectedStaffId = null;
    public string $paymentMethod = 'cash';
    public float $subtotal = 0;
    public float $discountAmount = 0;
    public float $tipAmount = 0;
    public float $taxAmount = 0;
    public float $totalAmount = 0;
    public string $selectedCategory = 'all';
    public string $searchTerm = '';
    public bool $isProcessingPayment = false;

    public function mount(): void
    {
        $this->customerData = [
            'type' => 'walk_in',
            'client_id' => null,
            'name' => '',
            'phone' => '',
            'email' => ''
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_transaction')
                ->label('New Transaction')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->action('clearCart'),
                
            Action::make('view_transactions')
                ->label('View Transactions')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(PosTransactionResource::getUrl('index')),
        ];
    }

    public function getServices()
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        $query = Service::whereHas('branches', function($q) use ($tenant) {
            $q->where('branch_id', $tenant->id);
        })->where('is_active', true);

        if ($this->selectedCategory !== 'all') {
            $query->where('category', $this->selectedCategory);
        }

        if (!empty($this->searchTerm)) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $this->searchTerm . '%');
            });
        }

        return $query->get();
    }

    public function getProducts()
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        $query = InventoryItem::where('branch_id', $tenant->id)
            ->where('is_active', true)
            ->whereNotNull('selling_price')
            ->where('current_stock', '>', 0);

        if ($this->selectedCategory !== 'all') {
            $query->where('category', $this->selectedCategory);
        }

        if (!empty($this->searchTerm)) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $this->searchTerm . '%');
            });
        }

        return $query->get();
    }

    public function getStaff()
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return Staff::whereHas('branches', function($query) use ($tenant) {
            $query->where('branch_id', $tenant->id);
        })->where('status', 'active')->get();
    }

    public function getServiceCategories()
    {
        return [
            'all' => 'All Services',
            'facial' => 'Facial',
            'massage' => 'Massage',
            'manicure' => 'Manicure',
            'pedicure' => 'Pedicure',
            'hair' => 'Hair Care',
            'body' => 'Body Treatments',
            'other' => 'Other'
        ];
    }

    public function addToCart($itemType, $itemId)
    {
        $item = $itemType === 'service' 
            ? Service::find($itemId) 
            : InventoryItem::find($itemId);

        if (!$item) return;

        $cartItemId = $itemType . '_' . $itemId;

        if (isset($this->cart[$cartItemId])) {
            // Increase quantity for products only
            if ($itemType === 'product') {
                $this->cart[$cartItemId]['quantity']++;
            }
        } else {
            $this->cart[$cartItemId] = [
                'id' => $itemId,
                'type' => $itemType,
                'name' => $item->name,
                'description' => $item->description ?? '',
                'price' => $itemType === 'service' ? $item->price : $item->selling_price,
                'quantity' => 1,
                'staff_id' => $itemType === 'service' ? $this->selectedStaffId : null,
                'duration' => $itemType === 'service' ? $item->duration_minutes : null,
            ];
        }

        $this->calculateTotals();
        
        Notification::make()
            ->title('Added to Cart')
            ->body($item->name . ' added successfully')
            ->success()
            ->send();
    }

    public function removeFromCart($cartItemId)
    {
        unset($this->cart[$cartItemId]);
        $this->calculateTotals();
        
        Notification::make()
            ->title('Removed from Cart')
            ->body('Item removed successfully')
            ->success()
            ->send();
    }

    public function updateQuantity($cartItemId, $quantity)
    {
        if ($quantity <= 0) {
            $this->removeFromCart($cartItemId);
            return;
        }

        if (isset($this->cart[$cartItemId])) {
            $this->cart[$cartItemId]['quantity'] = $quantity;
            $this->calculateTotals();
        }
    }

    public function updateStaffAssignment($cartItemId, $staffId)
    {
        if (isset($this->cart[$cartItemId])) {
            $this->cart[$cartItemId]['staff_id'] = $staffId;
        }
    }

    public function calculateTotals()
    {
        $this->subtotal = collect($this->cart)->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });

        // Apply 16% VAT for Kenya
        $this->taxAmount = $this->subtotal * 0.16;
        
        $this->totalAmount = $this->subtotal + $this->taxAmount + $this->tipAmount - $this->discountAmount;
    }

    public function processPayment()
    {
        if (empty($this->cart)) {
            Notification::make()
                ->title('Cart Empty')
                ->body('Please add items to cart before processing payment')
                ->warning()
                ->send();
            return;
        }

        if (!$this->selectedStaffId) {
            Notification::make()
                ->title('Staff Required')
                ->body('Please select a staff member')
                ->warning()
                ->send();
            return;
        }

        $this->isProcessingPayment = true;

        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            
            // Create POS Transaction
            $transaction = PosTransaction::create([
                'branch_id' => $tenant->id,
                'staff_id' => $this->selectedStaffId,
                'client_id' => $this->customerData['client_id'],
                'transaction_type' => $this->getTransactionType(),
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->discountAmount,
                'tax_amount' => $this->taxAmount,
                'tip_amount' => $this->tipAmount,
                'total_amount' => $this->totalAmount,
                'payment_method' => $this->paymentMethod,
                'payment_status' => $this->paymentMethod === 'cash' ? 'completed' : 'processing',
                'customer_info' => $this->customerData['type'] === 'walk_in' ? [
                    'name' => $this->customerData['name'],
                    'phone' => $this->customerData['phone'],
                    'email' => $this->customerData['email']
                ] : null,
            ]);

            // Add transaction items
            foreach ($this->cart as $cartItem) {
                $transaction->items()->create([
                    'item_type' => $cartItem['type'],
                    'item_id' => $cartItem['id'],
                    'item_name' => $cartItem['name'],
                    'item_description' => $cartItem['description'],
                    'quantity' => $cartItem['quantity'],
                    'unit_price' => $cartItem['price'],
                    'total_price' => $cartItem['price'] * $cartItem['quantity'],
                    'assigned_staff_id' => $cartItem['staff_id'],
                    'duration_minutes' => $cartItem['duration'],
                ]);
            }

            if ($this->paymentMethod === 'cash') {
                $transaction->markAsCompleted();
                $this->handlePaymentSuccess($transaction);
            } elseif ($this->paymentMethod === 'mpesa') {
                $this->initiateMpesaPayment($transaction);
            }

        } catch (\Exception $e) {
            $this->isProcessingPayment = false;
            
            Notification::make()
                ->title('Payment Failed')
                ->body('An error occurred while processing payment: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function getTransactionType(): string
    {
        $hasServices = collect($this->cart)->contains('type', 'service');
        $hasProducts = collect($this->cart)->contains('type', 'product');

        if ($hasServices && $hasProducts) return 'mixed';
        if ($hasServices) return 'service';
        if ($hasProducts) return 'product';
        
        return 'service';
    }

    private function initiateMpesaPayment($transaction)
    {
        // M-Pesa STK Push integration will be implemented here
        // For now, simulate payment processing
        
        $this->dispatch('mpesa-payment-initiated', [
            'transaction_id' => $transaction->id,
            'amount' => $this->totalAmount,
            'phone' => $this->customerData['phone']
        ]);
    }

    #[On('mpesa-payment-success')]
    public function handleMpesaSuccess($transactionId, $mpesaTransactionId)
    {
        $transaction = PosTransaction::find($transactionId);
        
        if ($transaction) {
            $transaction->update([
                'payment_status' => 'completed',
                'mpesa_transaction_id' => $mpesaTransactionId
            ]);
            
            $transaction->markAsCompleted();
            $this->handlePaymentSuccess($transaction);
        }
    }

    #[On('mpesa-payment-failed')]
    public function handleMpesaFailure($transactionId, $error)
    {
        $transaction = PosTransaction::find($transactionId);
        
        if ($transaction) {
            $transaction->update(['payment_status' => 'failed']);
        }

        $this->isProcessingPayment = false;
        
        Notification::make()
            ->title('M-Pesa Payment Failed')
            ->body($error)
            ->danger()
            ->send();
    }

    private function handlePaymentSuccess($transaction)
    {
        $this->isProcessingPayment = false;
        
        Notification::make()
            ->title('Payment Successful!')
            ->body('Transaction completed successfully. Receipt will be sent.')
            ->success()
            ->duration(5000)
            ->send();

        // Clear cart and reset form
        $this->clearCart();
        
        // Dispatch event to print receipt
        $this->dispatch('print-receipt', ['transaction_id' => $transaction->id]);
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->subtotal = 0;
        $this->discountAmount = 0;
        $this->tipAmount = 0;
        $this->taxAmount = 0;
        $this->totalAmount = 0;
        $this->customerData = [
            'type' => 'walk_in',
            'client_id' => null,
            'name' => '',
            'phone' => '',
            'email' => ''
        ];
        $this->selectedStaffId = null;
        $this->paymentMethod = 'cash';
        $this->isProcessingPayment = false;
    }

    public function setCustomer($customerId)
    {
        $customer = User::find($customerId);
        
        if ($customer) {
            $this->customerData = [
                'type' => 'registered',
                'client_id' => $customerId,
                'name' => $customer->first_name . ' ' . $customer->last_name,
                'phone' => $customer->phone,
                'email' => $customer->email
            ];
        }
    }

    public function updatedDiscountAmount()
    {
        $this->calculateTotals();
    }

    public function updatedTipAmount()
    {
        $this->calculateTotals();
    }

    public function updatedSearchTerm()
    {
        // Trigger re-render of services/products
    }

    public function updatedSelectedCategory()
    {
        // Trigger re-render of services/products
    }
}