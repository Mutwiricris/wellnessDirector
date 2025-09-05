<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class ClientResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Client Management';

    protected static ?string $navigationGroup = 'Customer Management';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Client Information')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\DatePicker::make('date_of_birth')
                            ->before('today')
                            ->displayFormat('Y-m-d'),

                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                                'prefer_not_to_say' => 'Prefer not to say',
                            ]),
                    ])->columns(2),

                Forms\Components\Section::make('Address & Contact Details')
                    ->schema([
                        Forms\Components\Textarea::make('address')
                            ->maxLength(500)
                            ->placeholder('Street address')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('postal_code')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->label('Emergency Contact Name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->label('Emergency Contact Phone')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Select::make('preferred_contact_method')
                            ->options([
                                'phone' => 'Phone',
                                'email' => 'Email',
                                'sms' => 'SMS',
                                'whatsapp' => 'WhatsApp',
                            ])
                            ->default('phone'),
                    ])->columns(3),

                Forms\Components\Section::make('Health & Wellness Profile')
                    ->schema([
                        Forms\Components\RichEditor::make('health_conditions')
                            ->label('Health Conditions & Medical History')
                            ->placeholder('Document any relevant health conditions, allergies, or medical considerations:
                            
• Chronic conditions or ongoing health issues
• Allergies (especially to products, oils, or materials)
• Medications that may affect treatments
• Previous injuries or areas of concern
• Pregnancy status or hormonal considerations')
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('wellness_goals')
                            ->label('Wellness Goals & Preferences')
                            ->placeholder('Client wellness objectives and treatment preferences:
                            
• Primary wellness goals and desired outcomes
• Preferred treatment types and techniques
• Areas of focus (stress relief, pain management, beauty, etc.)
• Frequency preferences and availability
• Special requests or accommodations needed')
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('treatment_history')
                            ->label('Treatment History & Notes')
                            ->placeholder('Previous treatments and practitioner notes:
                            
• Past treatments received and responses
• Preferred staff members or techniques
• Treatment outcomes and client feedback
• Areas to focus on or avoid
• Progress tracking and recommendations')
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Membership & Preferences')
                    ->schema([
                        Forms\Components\Select::make('membership_type')
                            ->options([
                                'standard' => 'Standard',
                                'premium' => 'Premium',
                                'vip' => 'VIP',
                                'corporate' => 'Corporate',
                            ])
                            ->default('standard'),

                        Forms\Components\DatePicker::make('membership_start_date')
                            ->default(now()),

                        Forms\Components\DatePicker::make('membership_end_date')
                            ->after('membership_start_date'),

                        Forms\Components\Toggle::make('marketing_consent')
                            ->label('Marketing Communications')
                            ->helperText('Consent to receive promotional communications'),

                        Forms\Components\Toggle::make('sms_notifications')
                            ->label('SMS Notifications')
                            ->default(true)
                            ->helperText('Appointment reminders and updates'),

                        Forms\Components\Toggle::make('email_notifications')
                            ->label('Email Notifications')
                            ->default(true)
                            ->helperText('Newsletters and service updates'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $tenant = Filament::getTenant();
                if ($tenant) {
                    $query->where('user_type', 'client')
                          ->whereHas('bookings', function($q) use ($tenant) {
                              $q->where('branch_id', $tenant->id);
                          });
                } else {
                    $query->where('user_type', 'client');
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Client Name')
                    ->getStateUsing(fn ($record) => $record->first_name . ' ' . $record->last_name)
                    ->weight('bold')
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('membership_type')
                    ->label('Membership')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'vip' => 'success',
                        'premium' => 'info',
                        'corporate' => 'warning',
                        'standard' => 'gray',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('total_bookings')
                    ->label('Total Bookings')
                    ->getStateUsing(function ($record) {
                        $tenant = Filament::getTenant();
                        return \App\Models\Booking::where('client_id', $record->id)
                            ->when($tenant, fn($q) => $q->where('branch_id', $tenant->id))
                            ->count();
                    })
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('last_visit')
                    ->getStateUsing(function ($record) {
                        $tenant = Filament::getTenant();
                        $lastBooking = \App\Models\Booking::where('client_id', $record->id)
                            ->when($tenant, fn($q) => $q->where('branch_id', $tenant->id))
                            ->where('status', 'completed')
                            ->latest('appointment_date')
                            ->first();
                        return $lastBooking?->appointment_date?->format('Y-m-d') ?? 'Never';
                    })
                    ->date(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->getStateUsing(function ($record) {
                        $tenant = Filament::getTenant();
                        return \App\Models\Booking::where('client_id', $record->id)
                            ->when($tenant, fn($q) => $q->where('branch_id', $tenant->id))
                            ->where('payment_status', 'completed')
                            ->sum('total_amount');
                    })
                    ->money('KES')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('average_rating')
                    ->label('Avg Rating')
                    ->getStateUsing(function ($record) {
                        $tenant = Filament::getTenant();
                        $avgRating = \App\Models\Booking::where('client_id', $record->id)
                            ->when($tenant, fn($q) => $q->where('branch_id', $tenant->id))
                            ->whereNotNull('rating')
                            ->avg('rating');
                        return $avgRating ? round($avgRating, 1) . '/5' : 'No ratings';
                    })
                    ->color('warning'),

                Tables\Columns\IconColumn::make('marketing_consent')
                    ->label('Marketing')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('membership_type')
                    ->options([
                        'standard' => 'Standard',
                        'premium' => 'Premium',
                        'vip' => 'VIP',
                        'corporate' => 'Corporate',
                    ]),

                Tables\Filters\Filter::make('frequent_clients')
                    ->label('Frequent Clients (5+ bookings)')
                    ->query(function (Builder $query) {
                        $tenant = Filament::getTenant();
                        return $query->whereHas('bookings', function($q) use ($tenant) {
                            $q->when($tenant, fn($subQ) => $subQ->where('branch_id', $tenant->id));
                        }, '>=', 5);
                    }),

                Tables\Filters\Filter::make('high_value_clients')
                    ->label('High Value Clients (10K+ spent)')
                    ->query(function (Builder $query) {
                        $tenant = Filament::getTenant();
                        return $query->whereHas('bookings', function($q) use ($tenant) {
                            $q->when($tenant, fn($subQ) => $subQ->where('branch_id', $tenant->id))
                              ->where('payment_status', 'completed')
                              ->havingRaw('SUM(total_amount) >= 10000');
                        });
                    }),

                Tables\Filters\Filter::make('recent_clients')
                    ->label('Recent Clients (30 days)')
                    ->query(function (Builder $query) {
                        $tenant = Filament::getTenant();
                        return $query->whereHas('bookings', function($q) use ($tenant) {
                            $q->when($tenant, fn($subQ) => $subQ->where('branch_id', $tenant->id))
                              ->where('appointment_date', '>=', now()->subDays(30));
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('view_bookings')
                    ->label('Bookings')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->url(fn (User $record) => route('filament.director.resources.bookings.index', [
                        'tenant' => Filament::getTenant()?->id,
                        'tableFilters[client_id][values][0]' => $record->id,
                    ])),

                Tables\Actions\Action::make('new_booking')
                    ->label('New Booking')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->url(fn (User $record) => route('filament.director.resources.bookings.create', [
                        'tenant' => Filament::getTenant()?->id,
                        'client_id' => $record->id,
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('export_clients')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(function ($records) {
                            // Export functionality would be implemented here
                        }),
                ]),
            ])
            ->emptyStateHeading('No clients found')
            ->emptyStateDescription('No clients have booked services at this branch yet.')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BookingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->where('user_type', 'client')
            ->when($tenant, function (Builder $query) use ($tenant) {
                $query->whereHas('bookings', function($q) use ($tenant) {
                    $q->where('branch_id', $tenant->id);
                });
            });
    }
}