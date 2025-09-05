<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GiftVoucherResource\Pages;
use App\Models\GiftVoucher;
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
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;

class GiftVoucherResource extends Resource
{
    protected static ?string $model = GiftVoucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Marketing & Promotions';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'voucher_code';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Voucher Details')
                    ->schema([
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn() => \Filament\Facades\Filament::getTenant()?->id),

                        Forms\Components\TextInput::make('voucher_code')
                            ->label('Voucher Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->default(fn () => GiftVoucher::generateVoucherCode())
                            ->suffixAction(
                                Action::make('generate')
                                    ->icon('heroicon-m-arrow-path')
                                    ->action(fn (Forms\Set $set) => $set('voucher_code', GiftVoucher::generateVoucherCode()))
                            ),

                        Forms\Components\Select::make('voucher_type')
                            ->label('Voucher Type')
                            ->options(GiftVoucher::getVoucherTypes())
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('applicable_services', null)),

                        Forms\Components\TextInput::make('original_amount')
                            ->label('Original Amount (KES)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('KES'),

                        Forms\Components\TextInput::make('remaining_amount')
                            ->label('Remaining Amount (KES)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('KES')
                            ->default(fn (Forms\Get $get) => $get('original_amount'))
                            ->reactive(),

                        Forms\Components\Select::make('applicable_services')
                            ->label('Applicable Services')
                            ->multiple()
                            ->relationship('branch.services', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => in_array($get('voucher_type'), ['service', 'package'])),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(GiftVoucher::getStatusOptions())
                            ->required()
                            ->default('active'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Recipient Information')
                    ->schema([
                        Forms\Components\TextInput::make('recipient_name')
                            ->label('Recipient Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('recipient_phone')
                            ->label('Recipient Phone')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('recipient_email')
                            ->label('Recipient Email')
                            ->email()
                            ->maxLength(100),

                        Forms\Components\Textarea::make('message')
                            ->label('Gift Message')
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Purchaser Information')
                    ->schema([
                        Forms\Components\TextInput::make('purchaser_name')
                            ->label('Purchaser Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('purchaser_phone')
                            ->label('Purchaser Phone')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('purchaser_email')
                            ->label('Purchaser Email')
                            ->email()
                            ->maxLength(100),

                        Forms\Components\Select::make('sold_by_staff_id')
                            ->label('Sold By Staff')
                            ->relationship('soldByStaff', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dates & Commission')
                    ->schema([
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Purchase Date')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->required()
                            ->after('purchase_date')
                            ->default(now()->addYear()),

                        Forms\Components\TextInput::make('commission_amount')
                            ->label('Commission Amount (KES)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('KES'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('voucher_code')
                    ->label('Voucher Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Voucher code copied!')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('voucher_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => GiftVoucher::getVoucherTypes()[$state] ?? $state)
                    ->colors([
                        'success' => 'monetary',
                        'info' => 'service',
                        'warning' => 'package',
                    ]),

                Tables\Columns\TextColumn::make('recipient_name')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_amount')
                    ->label('Original Amount')
                    ->money('KES')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('KES')
                    ->sortable()
                    ->color(fn ($state, $record) => !$record ? 'gray' : ($state <= 0 ? 'danger' : ($state < $record->original_amount * 0.2 ? 'warning' : 'success'))),

                Tables\Columns\TextColumn::make('usage_percentage')
                    ->label('Usage')
                    ->getStateUsing(fn ($record) => $record ? round($record->usage_percentage, 1) . '%' : '0%')
                    ->badge()
                    ->color(function ($state, $record) {
                        if (!$record) return 'gray';
                        $percentage = $record->usage_percentage;
                        if ($percentage >= 100) return 'danger';
                        if ($percentage >= 80) return 'warning';
                        if ($percentage >= 50) return 'info';
                        return 'success';
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => GiftVoucher::getStatusOptions()[$state] ?? $state)
                    ->colors([
                        'success' => 'active',
                        'danger' => 'redeemed',
                        'warning' => 'expired',
                        'gray' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->diffInDays() <= 30 ? 'warning' : null)),

                Tables\Columns\TextColumn::make('soldByStaff.name')
                    ->label('Sold By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Purchase Date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([

                Tables\Filters\SelectFilter::make('voucher_type')
                    ->label('Type')
                    ->options(GiftVoucher::getVoucherTypes()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(GiftVoucher::getStatusOptions()),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon (30 days)')
                    ->query(fn (Builder $query): Builder => $query->expiringSoon()),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder => $query->expired()),

                Tables\Filters\Filter::make('high_value')
                    ->label('High Value (≥ KES 5,000)')
                    ->query(fn (Builder $query): Builder => $query->where('original_amount', '>=', 5000)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('mark_expired')
                    ->label('Mark as Expired')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn ($record) => $record && $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'expired']);
                        Notification::make()
                            ->title('Voucher marked as expired')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('cancel_voucher')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record && in_array($record->status, ['active', 'expired']))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'cancelled']);
                        Notification::make()
                            ->title('Voucher cancelled successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('mark_expired')
                        ->label('Mark as Expired')
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = $records->where('status', 'active')->count();
                            $records->where('status', 'active')->each->update(['status' => 'expired']);
                            
                            Notification::make()
                                ->title("{$count} vouchers marked as expired")
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
                Infolists\Components\Section::make('Voucher Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('voucher_code')
                            ->label('Voucher Code')
                            ->copyable()
                            ->fontFamily('mono'),

                        Infolists\Components\TextEntry::make('branch.name')
                            ->label('Branch'),

                        Infolists\Components\TextEntry::make('voucher_type')
                            ->label('Type')
                            ->formatStateUsing(fn (string $state): string => GiftVoucher::getVoucherTypes()[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'monetary' => 'success',
                                'service' => 'info',
                                'package' => 'warning',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn (string $state): string => GiftVoucher::getStatusOptions()[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'redeemed' => 'danger',
                                'expired' => 'warning',
                                'cancelled' => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('original_amount')
                            ->label('Original Amount')
                            ->money('KES'),

                        Infolists\Components\TextEntry::make('remaining_amount')
                            ->label('Remaining Amount')
                            ->money('KES')
                            ->color(fn ($state, $record) => $state <= 0 ? 'danger' : ($state < $record->original_amount * 0.2 ? 'warning' : 'success')),

                        Infolists\Components\TextEntry::make('usage_percentage')
                            ->label('Usage Percentage')
                            ->formatStateUsing(fn ($state) => number_format($state, 1) . '%'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Recipient & Purchaser')
                    ->schema([
                        Infolists\Components\TextEntry::make('recipient_name')
                            ->label('Recipient Name'),

                        Infolists\Components\TextEntry::make('recipient_phone')
                            ->label('Recipient Phone'),

                        Infolists\Components\TextEntry::make('recipient_email')
                            ->label('Recipient Email'),

                        Infolists\Components\TextEntry::make('purchaser_name')
                            ->label('Purchaser Name'),

                        Infolists\Components\TextEntry::make('purchaser_phone')
                            ->label('Purchaser Phone'),

                        Infolists\Components\TextEntry::make('purchaser_email')
                            ->label('Purchaser Email'),

                        Infolists\Components\TextEntry::make('message')
                            ->label('Gift Message')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Dates & Staff')
                    ->schema([
                        Infolists\Components\TextEntry::make('purchase_date')
                            ->label('Purchase Date')
                            ->date(),

                        Infolists\Components\TextEntry::make('expiry_date')
                            ->label('Expiry Date')
                            ->date()
                            ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->diffInDays() <= 30 ? 'warning' : null)),

                        Infolists\Components\TextEntry::make('days_until_expiry')
                            ->label('Days Until Expiry')
                            ->formatStateUsing(fn ($state) => $state > 0 ? $state . ' days' : 'Expired')
                            ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= 30 ? 'warning' : 'success')),

                        Infolists\Components\TextEntry::make('soldByStaff.name')
                            ->label('Sold By Staff'),

                        Infolists\Components\TextEntry::make('commission_amount')
                            ->label('Commission Amount')
                            ->money('KES'),

                        Infolists\Components\TextEntry::make('last_used_at')
                            ->label('Last Used')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Applicable Services')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('applicable_services')
                            ->label('Services')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->getStateUsing(fn ($state) => Service::find($state)?->name ?? 'Service not found'),
                            ])
                            ->visible(fn ($record) => $record && $record->voucher_type !== 'monetary' && !empty($record->applicable_services)),
                    ]),

                Infolists\Components\Section::make('Redemption History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('redemption_history')
                            ->label('Usage History')
                            ->schema([
                                Infolists\Components\TextEntry::make('amount_used')
                                    ->label('Amount Used')
                                    ->money('KES'),
                                
                                Infolists\Components\TextEntry::make('used_at')
                                    ->label('Used At')
                                    ->dateTime(),
                                
                                Infolists\Components\TextEntry::make('remaining_after')
                                    ->label('Remaining After')
                                    ->money('KES'),
                            ])
                            ->columns(3)
                            ->visible(fn ($record) => $record && !empty($record->redemption_history)),
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
            'index' => Pages\ListGiftVouchers::route('/'),
            'create' => Pages\CreateGiftVoucher::route('/create'),
            'view' => Pages\ViewGiftVoucher::route('/{record}'),
            'edit' => Pages\EditGiftVoucher::route('/{record}/edit'),
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