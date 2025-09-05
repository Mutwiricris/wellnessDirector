<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffResource\Pages;
use App\Filament\Resources\StaffResource\RelationManagers;
use App\Models\Staff;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Staff Management';

    protected static ?string $navigationGroup = 'Staff & Scheduling';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Staff Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(Staff::class, 'email', ignoreRecord: true),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(20),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'on_leave' => 'On Leave',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\TagsInput::make('specialties')
                            ->placeholder('Add specialties (e.g., Massage, Facial, Manicure)')
                            ->helperText('Enter skills and services this staff member specializes in'),
                    ])->columns(2),

                Forms\Components\Section::make('Work Details')
                    ->schema([
                        Forms\Components\TextInput::make('hourly_rate')
                            ->numeric()
                            ->prefix('KES')
                            ->placeholder('0.00')
                            ->helperText('Hourly rate for this staff member'),

                        Forms\Components\Select::make('employment_type')
                            ->options([
                                'full_time' => 'Full Time',
                                'part_time' => 'Part Time',
                                'contract' => 'Contract',
                                'freelance' => 'Freelance',
                            ])
                            ->default('full_time'),

                        Forms\Components\DatePicker::make('hire_date')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('birth_date')
                            ->before('today')
                            ->helperText('Optional: For birthday notifications'),
                    ])->columns(2),

                Forms\Components\Section::make('Schedule & Availability')
                    ->schema([
                        Forms\Components\Repeater::make('working_hours')
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->options([
                                        'monday' => 'Monday',
                                        'tuesday' => 'Tuesday',
                                        'wednesday' => 'Wednesday',
                                        'thursday' => 'Thursday',
                                        'friday' => 'Friday',
                                        'saturday' => 'Saturday',
                                        'sunday' => 'Sunday',
                                    ])
                                    ->required(),

                                Forms\Components\TimePicker::make('start_time')
                                    ->required(),

                                Forms\Components\TimePicker::make('end_time')
                                    ->required()
                                    ->after('start_time'),

                                Forms\Components\Toggle::make('is_available')
                                    ->default(true)
                                    ->label('Available'),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Add Working Day')
                            ->collapsible(),
                    ]),

                Forms\Components\Section::make('Professional Profile')
                    ->schema([
                        Forms\Components\RichEditor::make('bio')
                            ->label('Professional Biography')
                            ->placeholder('Write a comprehensive professional biography including:
                            
• Educational background and certifications
• Years of experience in the wellness industry  
• Specialized training and expertise areas
• Professional achievements and awards
• Personal approach to client care and wellness philosophy')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('qualifications')
                            ->label('Qualifications & Certifications')
                            ->placeholder('Detail all professional qualifications:
                            
• Formal education (degrees, diplomas)
• Professional certifications and licenses
• Continuing education and recent training
• Membership in professional organizations
• Language proficiencies')
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('specialization_details')
                            ->label('Specialization & Expertise')
                            ->placeholder('Describe specialized skills and techniques:
                            
• Primary areas of expertise and specialization
• Advanced techniques and methodologies
• Unique skills or specialized equipment proficiency
• Client demographics and conditions of expertise
• Signature treatments or approaches')
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('bio')
                            ->label('Professional Bio')
                            ->maxLength(1000)
                            ->placeholder('Brief professional bio or description for client-facing materials')
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Internal Notes')
                            ->maxLength(500)
                            ->placeholder('Internal management notes about this staff member')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->label('Emergency Contact Name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->label('Emergency Contact Phone')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('national_id')
                            ->label('National ID/SSN')
                            ->maxLength(50)
                            ->helperText('For HR records'),

                        Forms\Components\DatePicker::make('contract_end_date')
                            ->label('Contract End Date')
                            ->after('hire_date')
                            ->helperText('For contract employees'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $tenant = Filament::getTenant();
                if ($tenant) {
                    $query->whereHas('branches', function($q) use ($tenant) {
                        $q->where('branches.id', $tenant->id);
                    });
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('specialties')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'on_leave' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('employment_type')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('hourly_rate')
                    ->money('KES')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('today_bookings')
                    ->label('Today\'s Bookings')
                    ->getStateUsing(function (Staff $record) {
                        $tenant = Filament::getTenant();
                        return \App\Models\Booking::where('branch_id', $tenant?->id)
                            ->where('staff_id', $record->id)
                            ->whereDate('appointment_date', today())
                            ->count();
                    })
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('this_month_revenue')
                    ->label('This Month Revenue')
                    ->getStateUsing(function (Staff $record) {
                        $tenant = Filament::getTenant();
                        return \App\Models\Booking::where('branch_id', $tenant?->id)
                            ->where('staff_id', $record->id)
                            ->whereMonth('appointment_date', now()->month)
                            ->whereYear('appointment_date', now()->year)
                            ->where('payment_status', 'completed')
                            ->sum('total_amount');
                    })
                    ->money('KES')
                    ->color('success')
                    ->weight('bold')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('hire_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('branches_count')
                    ->label('Branches')
                    ->getStateUsing(function (Staff $record) {
                        return $record->branches()->count();
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('services_count')
                    ->label('Services')
                    ->getStateUsing(function (Staff $record) {
                        return $record->services()->count();
                    })
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('hire_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'on_leave' => 'On Leave',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\SelectFilter::make('employment_type')
                    ->options([
                        'full_time' => 'Full Time',
                        'part_time' => 'Part Time',
                        'contract' => 'Contract',
                        'freelance' => 'Freelance',
                    ]),

                Tables\Filters\Filter::make('has_bookings_today')
                    ->label('Has Bookings Today')
                    ->query(function (Builder $query) {
                        $tenant = Filament::getTenant();
                        return $query->whereHas('bookings', function($q) use ($tenant) {
                            $q->where('branch_id', $tenant?->id)
                              ->whereDate('appointment_date', today());
                        });
                    })
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('view_schedule')
                    ->label('Schedule')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->url(fn (Staff $record) => route('filament.director.resources.bookings.index', [
                        'tenant' => Filament::getTenant()?->id,
                        'tableFilters[staff_id][values][0]' => $record->id,
                    ])),

                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (Staff $record): string => $record->status === 'active' ? 'Deactivate' : 'Activate')
                    ->icon(fn (Staff $record): string => $record->status === 'active' ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Staff $record): string => $record->status === 'active' ? 'warning' : 'success')
                    ->action(function (Staff $record) {
                        $record->update([
                            'status' => $record->status === 'active' ? 'inactive' : 'active'
                        ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn (Staff $record) => $record->update(['status' => 'active']));
                        })
                        ->requiresConfirmation(),
                        
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->action(function ($records) {
                            $records->each(fn (Staff $record) => $record->update(['status' => 'inactive']));
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No staff members found')
            ->emptyStateDescription('Start by adding staff members to this branch.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BranchesRelationManager::class,
            RelationManagers\ServicesRelationManager::class,
            RelationManagers\BookingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'view' => Pages\ViewStaff::route('/{record}'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->when($tenant, function (Builder $query) use ($tenant) {
                $query->whereHas('branches', function($q) use ($tenant) {
                    $q->where('branches.id', $tenant->id);
                });
            });
    }
}