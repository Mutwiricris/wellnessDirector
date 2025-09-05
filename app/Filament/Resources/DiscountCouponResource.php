<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountCouponResource\Pages;
use App\Models\DiscountCoupon;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\Service;
use App\Models\ServiceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;

class DiscountCouponResource extends Resource
{
    protected static ?string $model = DiscountCoupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Marketing & Promotions';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'coupon_code';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn() => \Filament\Facades\Filament::getTenant()?->id),

                        Forms\Components\TextInput::make('coupon_code')
                            ->label('Coupon Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->default(fn () => DiscountCoupon::generateCouponCode())
                            ->suffixAction(
                                Action::make('generate')
                                    ->icon('heroicon-m-arrow-path')
                                    ->action(fn (Forms\Set $set) => $set('coupon_code', DiscountCoupon::generateCouponCode()))
                            ),

                        Forms\Components\TextInput::make('name')
                            ->label('Coupon Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->rows(3),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(DiscountCoupon::getStatusOptions())
                            ->required()
                            ->default('active'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Discount Configuration')
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->label('Discount Type')
                            ->options(DiscountCoupon::getDiscountTypes())
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('discount_value')
                            ->label(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? 'Discount Percentage (%)' : 'Discount Amount (KES)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? 100 : null)
                            ->step(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? 0.01 : 0.01)
                            ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? '%' : 'KES'),

                        Forms\Components\TextInput::make('minimum_order_amount')
                            ->label('Minimum Order Amount (KES)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('KES')
                            ->helperText('Leave empty for no minimum requirement'),

                        Forms\Components\TextInput::make('maximum_discount_amount')
                            ->label('Maximum Discount Amount (KES)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('KES')
                            ->helperText('Leave empty for no maximum limit')
                            ->visible(fn (Forms\Get $get) => $get('discount_type') === 'percentage'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Usage Limits')
                    ->schema([
                        Forms\Components\TextInput::make('usage_limit')
                            ->label('Total Usage Limit')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Leave empty for unlimited uses'),

                        Forms\Components\TextInput::make('usage_limit_per_customer')
                            ->label('Usage Limit Per Customer')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        Forms\Components\Toggle::make('stackable')
                            ->label('Can be combined with other coupons')
                            ->helperText('Allow this coupon to be used with other coupons'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Validity Period')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start Date & Time')
                            ->required()
                            ->default(now())
                            ->before('expires_at'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiry Date & Time')
                            ->required()
                            ->after('starts_at')
                            ->default(now()->addMonth()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Service Restrictions')
                    ->schema([
                        Forms\Components\Select::make('applicable_services')
                            ->label('Applicable Services')
                            ->multiple()
                            ->relationship('branch.services', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to apply to all services'),

                        Forms\Components\Select::make('applicable_categories')
                            ->label('Applicable Categories')
                            ->multiple()
                            ->options(fn () => ServiceCategory::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to apply to all categories'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Time Restrictions')
                    ->schema([
                        Forms\Components\CheckboxList::make('time_restrictions.days_of_week')
                            ->label('Days of Week')
                            ->options([
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday',
                            ])
                            ->helperText('Leave empty to allow all days'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('time_restrictions.hours.start')
                                    ->label('Start Time')
                                    ->seconds(false),

                                Forms\Components\TimePicker::make('time_restrictions.hours.end')
                                    ->label('End Time')
                                    ->seconds(false),
                            ]),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Staff Information')
                    ->schema([
                        Forms\Components\Select::make('created_by_staff_id')
                            ->label('Created By Staff')
                            ->relationship('createdByStaff', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('coupon_code')
                    ->label('Coupon Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Coupon code copied!')
                    ->fontFamily('mono')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('discount_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => DiscountCoupon::getDiscountTypes()[$state] ?? $state)
                    ->colors([
                        'success' => 'percentage',
                        'info' => 'fixed_amount',
                    ]),

                Tables\Columns\TextColumn::make('formatted_discount_value')
                    ->label('Discount')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('discount_value', $direction);
                    }),

                Tables\Columns\TextColumn::make('minimum_order_amount')
                    ->label('Min. Order')
                    ->money('KES')
                    ->sortable()
                    ->placeholder('No minimum'),

                Tables\Columns\TextColumn::make('used_count')
                    ->label('Used')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('usage_limit')
                    ->label('Limit')
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('Unlimited'),

                Tables\Columns\TextColumn::make('usage_percentage')
                    ->label('Usage %')
                    ->getStateUsing(fn ($record) => $record ? round($record->usage_percentage, 1) . '%' : '0%')
                    ->badge()
                    ->color(function ($state, $record) {
                        if (!$record) return 'gray';
                        $percentage = $record->usage_percentage;
                        if ($percentage >= 100) return 'danger';
                        if ($percentage >= 80) return 'warning';
                        if ($percentage >= 50) return 'info';
                        return 'success';
                    })
                    ->visible(fn ($record) => $record !== null && $record->usage_limit !== null),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => DiscountCoupon::getStatusOptions()[$state] ?? $state)
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'expired',
                    ]),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->diffInDays() <= 7 ? 'warning' : null)),

                Tables\Columns\IconColumn::make('stackable')
                    ->label('Stackable')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdByStaff.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DiscountCoupon::getStatusOptions()),

                Tables\Filters\SelectFilter::make('discount_type')
                    ->label('Discount Type')
                    ->options(DiscountCoupon::getDiscountTypes()),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon (7 days)')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<=', now()->addDays(7))
                                                                  ->where('status', 'active')),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder => $query->expired()),

                Tables\Filters\Filter::make('high_usage')
                    ->label('High Usage (≥80%)')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('usage_limit')
                                                                  ->whereRaw('(used_count / usage_limit) >= 0.8')),

                Tables\Filters\Filter::make('stackable')
                    ->label('Stackable')
                    ->query(fn (Builder $query): Builder => $query->where('stackable', true)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'inactive')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'active']);
                        Notification::make()
                            ->title('Coupon activated successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'inactive']);
                        Notification::make()
                            ->title('Coupon deactivated successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function ($record) {
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
                            $count = $records->where('status', 'inactive')->count();
                            $records->where('status', 'inactive')->each->update(['status' => 'active']);
                            
                            Notification::make()
                                ->title("{$count} coupons activated")
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
                                ->title("{$count} coupons deactivated")
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
                Infolists\Components\Section::make('Coupon Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('coupon_code')
                            ->label('Coupon Code')
                            ->copyable()
                            ->fontFamily('mono')
                            ->size('lg')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Name'),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('branch.name')
                            ->label('Branch'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn (string $state): string => DiscountCoupon::getStatusOptions()[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'warning',
                                'expired' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Discount Configuration')
                    ->schema([
                        Infolists\Components\TextEntry::make('discount_type')
                            ->label('Discount Type')
                            ->formatStateUsing(fn (string $state): string => DiscountCoupon::getDiscountTypes()[$state] ?? $state)
                            ->badge(),

                        Infolists\Components\TextEntry::make('formatted_discount_value')
                            ->label('Discount Value'),

                        Infolists\Components\TextEntry::make('minimum_order_amount')
                            ->label('Minimum Order Amount')
                            ->money('KES')
                            ->placeholder('No minimum'),

                        Infolists\Components\TextEntry::make('maximum_discount_amount')
                            ->label('Maximum Discount Amount')
                            ->money('KES')
                            ->placeholder('No maximum')
                            ->visible(fn ($record) => $record->maximum_discount_amount !== null),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Usage Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('used_count')
                            ->label('Times Used'),

                        Infolists\Components\TextEntry::make('usage_limit')
                            ->label('Usage Limit')
                            ->placeholder('Unlimited'),

                        Infolists\Components\TextEntry::make('remaining_uses')
                            ->label('Remaining Uses')
                            ->placeholder('Unlimited'),

                        Infolists\Components\TextEntry::make('usage_limit_per_customer')
                            ->label('Limit Per Customer'),

                        Infolists\Components\TextEntry::make('usage_percentage')
                            ->label('Usage Percentage')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 1) . '%' : 'N/A'),

                        Infolists\Components\IconEntry::make('stackable')
                            ->label('Stackable')
                            ->boolean(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Validity Period')
                    ->schema([
                        Infolists\Components\TextEntry::make('starts_at')
                            ->label('Start Date & Time')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expiry Date & Time')
                            ->dateTime()
                            ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->diffInDays() <= 7 ? 'warning' : null)),

                        Infolists\Components\TextEntry::make('days_until_expiry')
                            ->label('Days Until Expiry')
                            ->formatStateUsing(fn ($state) => $state > 0 ? $state . ' days' : 'Expired')
                            ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= 7 ? 'warning' : 'success')),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Service Restrictions')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('applicable_services')
                            ->label('Applicable Services')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->getStateUsing(fn ($state) => Service::find($state)?->name ?? 'Service not found'),
                            ])
                            ->visible(fn ($record) => !empty($record->applicable_services)),

                        Infolists\Components\RepeatableEntry::make('applicable_categories')
                            ->label('Applicable Categories')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->getStateUsing(fn ($state) => ServiceCategory::find($state)?->name ?? 'Category not found'),
                            ])
                            ->visible(fn ($record) => !empty($record->applicable_categories)),
                    ]),

                Infolists\Components\Section::make('Time Restrictions')
                    ->schema([
                        Infolists\Components\TextEntry::make('time_restrictions.days_of_week')
                            ->label('Allowed Days')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_map('ucfirst', $state)) : 'All days')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('time_restrictions.hours.start')
                            ->label('Start Time')
                            ->placeholder('No restriction'),

                        Infolists\Components\TextEntry::make('time_restrictions.hours.end')
                            ->label('End Time')
                            ->placeholder('No restriction'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Staff Information')
                    ->schema([
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
            'index' => Pages\ListDiscountCoupons::route('/'),
            'create' => Pages\CreateDiscountCoupon::route('/create'),
            'view' => Pages\ViewDiscountCoupon::route('/{record}'),
            'edit' => Pages\EditDiscountCoupon::route('/{record}/edit'),
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