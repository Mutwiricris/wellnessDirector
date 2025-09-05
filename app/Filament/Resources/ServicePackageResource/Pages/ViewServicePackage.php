<?php

namespace App\Filament\Resources\ServicePackageResource\Pages;

use App\Filament\Resources\ServicePackageResource;
use App\Models\ServicePackage;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewServicePackage extends ViewRecord
{
    protected static string $resource = ServicePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('duplicate')
                ->label('Duplicate Package')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->action(function () {
                    $record = $this->getRecord();
                    $newPackage = $record->replicate();
                    $newPackage->package_code = ServicePackage::generatePackageCode();
                    $newPackage->name = $record->name . ' (Copy)';
                    $newPackage->status = 'draft';
                    $newPackage->popular = false;
                    $newPackage->featured = false;
                    $newPackage->save();

                    // Copy services
                    foreach ($record->services as $service) {
                        $newPackage->services()->attach($service->id, [
                            'quantity' => $service->pivot->quantity,
                            'order' => $service->pivot->order,
                            'is_required' => $service->pivot->is_required,
                            'notes' => $service->pivot->notes,
                        ]);
                    }

                    Notification::make()
                        ->title('Package duplicated successfully')
                        ->body("New package code: {$newPackage->package_code}")
                        ->success()
                        ->send();

                    return redirect()->route('filament.admin.resources.service-packages.edit', $newPackage);
                }),

            Actions\Action::make('toggle_status')
                ->label(fn () => $this->getRecord()->status === 'active' ? 'Deactivate' : 'Activate')
                ->icon(fn () => $this->getRecord()->status === 'active' ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn () => $this->getRecord()->status === 'active' ? 'warning' : 'success')
                ->requiresConfirmation()
                ->action(function () {
                    $record = $this->getRecord();
                    $newStatus = $record->status === 'active' ? 'inactive' : 'active';
                    $record->update(['status' => $newStatus]);
                    
                    Notification::make()
                        ->title("Package {$newStatus}")
                        ->success()
                        ->send();
                        
                    $this->refreshFormData([
                        'status',
                    ]);
                }),

            Actions\Action::make('toggle_popular')
                ->label(fn () => $this->getRecord()->popular ? 'Remove from Popular' : 'Mark as Popular')
                ->icon(fn () => $this->getRecord()->popular ? 'heroicon-o-star' : 'heroicon-s-star')
                ->color('info')
                ->action(function () {
                    $record = $this->getRecord();
                    $record->update(['popular' => !$record->popular]);
                    
                    $status = $record->popular ? 'marked as popular' : 'removed from popular';
                    Notification::make()
                        ->title("Package {$status}")
                        ->success()
                        ->send();
                        
                    $this->refreshFormData([
                        'popular',
                    ]);
                }),

            Actions\Action::make('toggle_featured')
                ->label(fn () => $this->getRecord()->featured ? 'Remove from Featured' : 'Mark as Featured')
                ->icon(fn () => $this->getRecord()->featured ? 'heroicon-o-bookmark' : 'heroicon-s-bookmark')
                ->color('warning')
                ->action(function () {
                    $record = $this->getRecord();
                    $record->update(['featured' => !$record->featured]);
                    
                    $status = $record->featured ? 'marked as featured' : 'removed from featured';
                    Notification::make()
                        ->title("Package {$status}")
                        ->success()
                        ->send();
                        
                    $this->refreshFormData([
                        'featured',
                    ]);
                }),

            Actions\Action::make('view_sales')
                ->label('View Sales')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(fn () => route('package-sales.index', ['package' => $this->getRecord()->id]))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()->packageSales()->count() > 0),
        ];
    }
}