<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Model;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationGroup = 'Financial Management';
    
    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Expenses';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Expense Details')
                    ->schema([
                        Forms\Components\Hidden::make('branch_id')
                            ->default(fn (): ?int => \Filament\Facades\Filament::getTenant()?->id),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('category')
                                    ->label('Category')
                                    ->options(Expense::getCategories())
                                    ->required()
                                    ->native(false)
                                    ->reactive(),

                                Forms\Components\Select::make('subcategory')
                                    ->label('Subcategory')
                                    ->options(function (Forms\Get $get) {
                                        $category = $get('category');
                                        if (!$category) return [];
                                        
                                        $subcategories = Expense::getSubcategories();
                                        return array_combine(
                                            $subcategories[$category] ?? [],
                                            array_map('ucfirst', $subcategories[$category] ?? [])
                                        );
                                    })
                                    ->native(false),

                                Forms\Components\TextInput::make('description')
                                    ->label('Description')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Brief description of the expense'),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount (KES)')
                                    ->required()
                                    ->numeric()
                                    ->prefix('KES')
                                    ->minValue(0)
                                    ->step(0.01),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'mpesa' => 'M-Pesa',
                                        'card' => 'Card',
                                        'bank_transfer' => 'Bank Transfer',
                                        'cheque' => 'Cheque'
                                    ])
                                    ->required()
                                    ->native(false),

                                Forms\Components\DatePicker::make('expense_date')
                                    ->label('Expense Date')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now()),
                            ]),
                    ]),

                Forms\Components\Section::make('Vendor Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('vendor_name')
                                    ->label('Vendor Name')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('receipt_number')
                                    ->label('Receipt/Invoice Number')
                                    ->maxLength(255),
                            ]),
                    ]),

                Forms\Components\Section::make('Approval & Status')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'pending' => 'Pending Approval',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected'
                                    ])
                                    ->default('approved')
                                    ->required()
                                    ->native(false),

                                Forms\Components\Select::make('approved_by')
                                    ->label('Approved By')
                                    ->relationship('approvedBy', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'approved'),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Recurring Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_recurring')
                            ->label('Recurring Expense')
                            ->reactive(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('recurring_frequency')
                                    ->label('Frequency')
                                    ->options([
                                        'weekly' => 'Weekly',
                                        'monthly' => 'Monthly',
                                        'quarterly' => 'Quarterly',
                                        'yearly' => 'Yearly'
                                    ])
                                    ->visible(fn (Forms\Get $get) => $get('is_recurring'))
                                    ->native(false),

                                Forms\Components\DatePicker::make('next_due_date')
                                    ->label('Next Due Date')
                                    ->visible(fn (Forms\Get $get) => $get('is_recurring'))
                                    ->minDate(now()),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Attachments')
                    ->schema([
                        Forms\Components\FileUpload::make('attachments')
                            ->label('Receipt/Invoice Images')
                            ->multiple()
                            ->image()
                            ->maxFiles(5)
                            ->disk('public')
                            ->directory('expense-receipts')
                            ->visibility('private'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (Model $record): string {
                        return $record->description;
                    }),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => 
                        Expense::getCategories()[$state] ?? ucfirst($state)
                    ),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('KES')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('danger'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'mpesa' => 'warning',
                        'card' => 'info',
                        'bank_transfer' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->limit(20)
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-path')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(Expense::getCategories())
                    ->multiple(),

                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected'
                    ])
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'mpesa' => 'M-Pesa',
                        'card' => 'Card',
                        'bank_transfer' => 'Bank Transfer',
                        'cheque' => 'Cheque'
                    ])
                    ->multiple(),

                Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('expense_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('expense_date', '<=', $date),
                            );
                    }),

                Filter::make('amount_range')
                    ->form([
                        Forms\Components\TextInput::make('min_amount')
                            ->label('Min Amount')
                            ->numeric()
                            ->prefix('KES'),
                        Forms\Components\TextInput::make('max_amount')
                            ->label('Max Amount')
                            ->numeric()
                            ->prefix('KES'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['max_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Export Selected'),
                ]),
            ])
            ->defaultSort('expense_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
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
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if (!$tenant) return null;
            
            $pendingCount = static::getModel()::where('branch_id', $tenant->id)
                ->where('status', 'pending')
                ->count();
            return $pendingCount > 0 ? (string) $pendingCount : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['branch', 'approvedBy']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['description', 'vendor_name', 'receipt_number', 'category'];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->when($tenant, fn (Builder $query) => $query->where('branch_id', $tenant->id));
    }
}