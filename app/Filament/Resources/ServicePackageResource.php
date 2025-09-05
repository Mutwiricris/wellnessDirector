<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServicePackageResource\Pages;
use App\Models\ServicePackage;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;

class ServicePackageResource extends Resource
{
    protected static ?string $model = ServicePackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Business Operations';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Package Information')
                    ->schema([
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn() => \Filament\Facades\Filament::getTenant()?->id),

                        Forms\Components\TextInput::make('name')
                            ->label('Package Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('package_code')
                            ->label('Package Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->default(fn () => ServicePackage::generatePackageCode())
                            ->suffixAction(
                                Action::make('generate')
                                    ->icon('heroicon-m-arrow-path')
                                    ->action(fn (Forms\Set $set) => $set('package_code', ServicePackage::generatePackageCode()))
                            ),

                        Forms\Components\Select::make('package_type')
                            ->label('Package Type')
                            ->options(ServicePackage::getPackageTypes())
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(1000)
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('image_path')
                            ->label('Package Image')
                            ->image()
                            ->directory('packages')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Services Included')
                    ->schema([
                        Forms\Components\Repeater::make('services')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('service_id')
                                    ->label('Service')
                                    ->relationship('service', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        if ($state) {
                                            $service = Service::find($state);
                                            if ($service) {
                                                $set('notes', "Duration: {$service->duration_minutes} minutes");
                                            }
                                        }
                                    }),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),

                                Forms\Components\TextInput::make('order')
                                    ->label('Order')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),

                                Forms\Components\Toggle::make('is_required')
                                    ->label('Required Service')
                                    ->default(true),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Notes')
                                    ->maxLength(500)
                                    ->rows(2),
                            ])
                            ->columnSpanFull()
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Add Service')
                            ->reorderableWithButtons()
                            ->collapsible(),
                    ]),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('total_price')
                            ->label('Total Price (KES)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('KES')
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $discount = (float) ($get('discount_amount') ?? 0);
                                $set('final_price', max(0, (float) $state - $discount));
                            }),

                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount Amount (KES)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('KES')
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $total = (float) ($get('total_price') ?? 0);
                                $set('final_price', max(0, $total - (float) $state));
                            }),

                        Forms\Components\TextInput::make('final_price')
                            ->label('Final Price (KES)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('KES')
                            ->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Package Settings')
                    ->schema([
                        Forms\Components\TextInput::make('validity_days')
                            ->label('Validity (Days)')
                            ->numeric()
                            ->default(365)
                            ->minValue(1)
                            ->required()
                            ->helperText('Number of days from purchase date'),

                        Forms\Components\TextInput::make('max_bookings')
                            ->label('Max Simultaneous Bookings')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Leave empty for no limit'),

                        Forms\Components\TextInput::make('booking_interval_days')
                            ->label('Min Days Between Bookings')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Minimum days between consecutive bookings'),

                        Forms\Components\Toggle::make('is_couple_package')
                            ->label('Couples Package')
                            ->helperText('Package designed for couples'),

                        Forms\Components\Toggle::make('requires_consecutive_booking')
                            ->label('Requires Consecutive Booking')
                            ->helperText('Services must be booked in order'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ServicePackage::getStatusOptions())
                            ->required()
                            ->default('active'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Marketing')
                    ->schema([
                        Forms\Components\Toggle::make('popular')
                            ->label('Mark as Popular')
                            ->helperText('Show in popular packages section'),

                        Forms\Components\Toggle::make('featured')
                            ->label('Mark as Featured')
                            ->helperText('Highlight this package'),

                        Forms\Components\Select::make('created_by_staff_id')
                            ->label('Created By Staff')
                            ->relationship('createdByStaff', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Terms & Conditions')
                    ->schema([
                        Forms\Components\Repeater::make('terms_conditions')
                            ->label('Terms & Conditions')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Title')
                                    ->required(),
                                
                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->required()
                                    ->rows(3),
                            ])
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->addActionLabel('Add Term')
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('package_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Package Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('package_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => ServicePackage::getPackageTypes()[$state] ?? $state)
                    ->colors([
                        'success' => 'wellness',
                        'info' => 'beauty',
                        'warning' => 'spa',
                        'danger' => 'couples',
                        'primary' => 'premium',
                        'secondary' => 'seasonal',
                        'gray' => 'membership',
                    ]),

                Tables\Columns\TextColumn::make('services_count')
                    ->label('Services')
                    ->counts('services')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total Price')
                    ->money('KES')
                    ->sortable(),

                Tables\Columns\TextColumn::make('final_price')
                    ->label('Final Price')
                    ->money('KES')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('discount_percentage')
                    ->label('Discount')
                    ->getStateUsing(fn ($record) => $record->getDiscountPercentage())
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                    ->color('success')
                    ->visible(fn ($record) => $record && $record->discount_amount > 0),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => ServicePackage::getStatusOptions()[$state] ?? $state)
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'gray' => 'draft',
                        'danger' => 'expired',
                    ]),

                Tables\Columns\IconColumn::make('popular')
                    ->label('Popular')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_couple_package')
                    ->label('Couples')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('validity_days')
                    ->label('Validity')
                    ->suffix(' days')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('packageSales')
                    ->label('Sales')
                    ->counts('packageSales')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([

                Tables\Filters\SelectFilter::make('package_type')
                    ->label('Package Type')
                    ->options(ServicePackage::getPackageTypes()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ServicePackage::getStatusOptions()),

                Tables\Filters\Filter::make('popular')
                    ->label('Popular Packages')
                    ->query(fn (Builder $query): Builder => $query->where('popular', true)),

                Tables\Filters\Filter::make('featured')
                    ->label('Featured Packages')
                    ->query(fn (Builder $query): Builder => $query->where('featured', true)),

                Tables\Filters\Filter::make('couples')
                    ->label('Couples Packages')
                    ->query(fn (Builder $query): Builder => $query->where('is_couple_package', true)),

                Tables\Filters\Filter::make('high_discount')
                    ->label('High Discount (≥20%)')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('(discount_amount / total_price) >= 0.2')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function ($record) {
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

                Tables\Actions\Action::make('toggle_status')
                    ->label(fn ($record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->status === 'active' ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn ($record) => $record->status === 'active' ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $newStatus = $record->status === 'active' ? 'inactive' : 'active';
                        $record->update(['status' => $newStatus]);
                        
                        Notification::make()
                            ->title("Package {$newStatus}")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->where('status', '!=', 'active')->count();
                            $records->where('status', '!=', 'active')->each->update(['status' => 'active']);
                            
                            Notification::make()
                                ->title("{$count} packages activated")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->where('status', 'active')->count();
                            $records->where('status', 'active')->each->update(['status' => 'inactive']);
                            
                            Notification::make()
                                ->title("{$count} packages deactivated")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_popular')
                        ->label('Mark as Popular')
                        ->icon('heroicon-o-star')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['popular' => true]);
                            
                            Notification::make()
                                ->title("{$records->count()} packages marked as popular")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Package Overview')
                    ->schema([
                        Infolists\Components\ImageEntry::make('image_path')
                            ->label('Package Image')
                            ->height(200)
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('package_code')
                            ->label('Package Code')
                            ->copyable()
                            ->fontFamily('mono')
                            ->size('lg')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Package Name')
                            ->size('lg'),

                        Infolists\Components\TextEntry::make('package_type')
                            ->label('Package Type')
                            ->formatStateUsing(fn (string $state): string => ServicePackage::getPackageTypes()[$state] ?? $state)
                            ->badge(),

                        Infolists\Components\TextEntry::make('branch.name')
                            ->label('Branch'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn (string $state): string => ServicePackage::getStatusOptions()[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'warning',
                                'draft' => 'gray',
                                'expired' => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Pricing Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_price')
                            ->label('Total Price')
                            ->money('KES'),

                        Infolists\Components\TextEntry::make('discount_amount')
                            ->label('Discount Amount')
                            ->money('KES'),

                        Infolists\Components\TextEntry::make('final_price')
                            ->label('Final Price')
                            ->money('KES')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('formatted_discount_percentage')
                            ->label('Discount Percentage'),

                        Infolists\Components\TextEntry::make('formatted_duration')
                            ->label('Total Duration'),

                        Infolists\Components\TextEntry::make('validity_days')
                            ->label('Validity Period')
                            ->suffix(' days'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Services Included')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('services')
                            ->label('Services')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Service Name'),
                                
                                Infolists\Components\TextEntry::make('pivot.quantity')
                                    ->label('Quantity'),
                                
                                Infolists\Components\TextEntry::make('pivot.order')
                                    ->label('Order'),
                                
                                Infolists\Components\IconEntry::make('pivot.is_required')
                                    ->label('Required')
                                    ->boolean(),
                                
                                Infolists\Components\TextEntry::make('duration_minutes')
                                    ->label('Duration')
                                    ->suffix(' min'),
                                
                                Infolists\Components\TextEntry::make('pivot.notes')
                                    ->label('Notes'),
                            ])
                            ->columns(3),
                    ]),

                Infolists\Components\Section::make('Package Settings')
                    ->schema([
                        Infolists\Components\IconEntry::make('is_couple_package')
                            ->label('Couples Package')
                            ->boolean(),

                        Infolists\Components\IconEntry::make('requires_consecutive_booking')
                            ->label('Consecutive Booking Required')
                            ->boolean(),

                        Infolists\Components\TextEntry::make('booking_interval_days')
                            ->label('Min Booking Interval')
                            ->suffix(' days')
                            ->placeholder('No restriction'),

                        Infolists\Components\TextEntry::make('max_bookings')
                            ->label('Max Simultaneous Bookings')
                            ->placeholder('No limit'),

                        Infolists\Components\IconEntry::make('popular')
                            ->label('Popular Package')
                            ->boolean(),

                        Infolists\Components\IconEntry::make('featured')
                            ->label('Featured Package')
                            ->boolean(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('packageSales')
                            ->label('Total Sales')
                            ->getStateUsing(fn ($record) => $record->packageSales()->count()),

                        Infolists\Components\TextEntry::make('active_sales')
                            ->label('Active Sales')
                            ->getStateUsing(fn ($record) => $record->packageSales()->active()->count()),

                        Infolists\Components\TextEntry::make('total_revenue')
                            ->label('Total Revenue')
                            ->getStateUsing(fn ($record) => 'KES ' . number_format($record->packageSales()->sum('final_price'), 2)),

                        Infolists\Components\TextEntry::make('createdByStaff.name')
                            ->label('Created By'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Terms & Conditions')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('terms_conditions')
                            ->label('Terms & Conditions')
                            ->schema([
                                Infolists\Components\TextEntry::make('title')
                                    ->label('Title')
                                    ->weight('bold'),
                                
                                Infolists\Components\TextEntry::make('description')
                                    ->label('Description'),
                            ])
                            ->visible(fn ($record) => !empty($record->terms_conditions)),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServicePackages::route('/'),
            'create' => Pages\CreateServicePackage::route('/create'),
            'view' => Pages\ViewServicePackage::route('/{record}'),
            'edit' => Pages\EditServicePackage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}