<?php

namespace App\Filament\Resources\DiscountCouponResource\Pages;

use App\Filament\Resources\DiscountCouponResource;
use App\Models\DiscountCoupon;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewDiscountCoupon extends ViewRecord
{
    protected static string $resource = DiscountCouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->getRecord()->status === 'inactive')
                ->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->update(['status' => 'active']);
                    
                    Notification::make()
                        ->title('Coupon activated successfully')
                        ->success()
                        ->send();
                        
                    $this->refreshFormData([
                        'status',
                    ]);
                }),

            Actions\Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn () => $this->getRecord()->status === 'active')
                ->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->update(['status' => 'inactive']);
                    
                    Notification::make()
                        ->title('Coupon deactivated successfully')
                        ->success()
                        ->send();
                        
                    $this->refreshFormData([
                        'status',
                    ]);
                }),

            Actions\Action::make('duplicate')
                ->label('Duplicate Coupon')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->action(function () {
                    $record = $this->getRecord();
                    $newCoupon = $record->replicate();
                    $newCoupon->coupon_code = DiscountCoupon::generateCouponCode();
                    $newCoupon->name = $record->name . ' (Copy)';
                    $newCoupon->used_count = 0;
                    $newCoupon->status = 'inactive';
                    $newCoupon->starts_at = now();
                    $newCoupon->expires_at = now()->addMonth();
                    $newCoupon->save();

                    Notification::make()
                        ->title('Coupon duplicated successfully')
                        ->body("New coupon code: {$newCoupon->coupon_code}")
                        ->success()
                        ->send();

                    return redirect()->route('filament.admin.resources.discount-coupons.edit', $newCoupon);
                }),

            Actions\Action::make('view_usage')
                ->label('View Usage History')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(fn () => route('discount-coupons.usage', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()->used_count > 0),
        ];
    }
}