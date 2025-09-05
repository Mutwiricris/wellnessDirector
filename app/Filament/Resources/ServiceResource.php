<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Resources\ServiceResource\RelationManagers;
use App\Models\Service;
use App\Models\ServiceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Services';

    protected static ?string $navigationGroup = 'Business Operations';

    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Service Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->maxLength(500),
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->prefix('KES')
                            ->required()
                            ->placeholder('0.00'),

                        Forms\Components\TextInput::make('duration_minutes')
                            ->label('Duration (minutes)')
                            ->numeric()
                            ->required()
                            ->suffix('min')
                            ->placeholder('60'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'coming_soon' => 'Coming Soon',
                                'seasonal' => 'Seasonal',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\Toggle::make('requires_consultation')
                            ->label('Requires Consultation')
                            ->helperText('Check if this service requires a consultation before booking'),
                    ])->columns(2),

                Forms\Components\Section::make('Service Details')
                    ->schema([
                        Forms\Components\TagsInput::make('benefits')
                            ->placeholder('Add service benefits')
                            ->helperText('List the key benefits of this service'),

                        Forms\Components\TagsInput::make('suitable_for')
                            ->placeholder('Add suitable conditions')
                            ->helperText('Who is this service suitable for (e.g., dry skin, stress relief)'),

                        Forms\Components\Repeater::make('equipment_needed')
                            ->schema([
                                Forms\Components\TextInput::make('item')
                                    ->required()
                                    ->placeholder('Equipment or product name'),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->suffix('units'),
                                Forms\Components\Toggle::make('optional')
                                    ->default(false),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add Equipment')
                            ->collapsible(),
                    ]),

                Forms\Components\Section::make('Service Overview & Benefits')
                    ->schema([
                        Forms\Components\RichEditor::make('detailed_description')
                            ->label('Comprehensive Service Description')
                            ->placeholder('Provide a thorough description of this service including:
                            
• What the service entails and key components
• Specific techniques and methods used
• Target areas and focus of treatment
• Expected outcomes and benefits
• Duration breakdown and service flow')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('health_benefits')
                            ->label('Health & Wellness Benefits')
                            ->placeholder('Detail the specific health and wellness benefits:
                            
• Physical health improvements and relief provided
• Mental and emotional wellness benefits
• Skin, circulation, or muscular benefits
• Stress relief and relaxation effects
• Long-term wellness outcomes')
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('ideal_candidates')
                            ->label('Ideal Candidates & Recommendations')
                            ->placeholder('Describe who would benefit most from this service:
                            
• Target demographics and client types
• Specific conditions or concerns addressed
• Lifestyle factors that make this service beneficial
• Frequency recommendations for optimal results
• Complementary services that enhance results')
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList', 'orderedList'
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Pricing & Packages')
                    ->schema([
                        Forms\Components\TextInput::make('member_price')
                            ->label('Member Price')
                            ->numeric()
                            ->prefix('KES')
                            ->placeholder('0.00')
                            ->helperText('Special price for members (optional)'),

                        Forms\Components\TextInput::make('group_price')
                            ->label('Group Price')
                            ->numeric()
                            ->prefix('KES')
                            ->placeholder('0.00')
                            ->helperText('Price for group bookings (optional)'),

                        Forms\Components\TextInput::make('min_group_size')
                            ->label('Minimum Group Size')
                            ->numeric()
                            ->default(2)
                            ->helperText('Minimum people for group pricing'),

                        Forms\Components\TextInput::make('max_group_size')
                            ->label('Maximum Group Size')
                            ->numeric()
                            ->default(4)
                            ->helperText('Maximum people for group bookings'),

                        Forms\Components\Toggle::make('available_for_packages')
                            ->label('Available for Packages')
                            ->default(true)
                            ->helperText('Can this service be included in packages?'),

                        Forms\Components\Toggle::make('is_couple_service')
                            ->label('Couple Service')
                            ->default(false)
                            ->helperText('Designed specifically for couples'),
                    ])->columns(3),

                Forms\Components\Section::make('Scheduling & Availability')
                    ->schema([
                        Forms\Components\TextInput::make('preparation_time')
                            ->label('Preparation Time (minutes)')
                            ->numeric()
                            ->default(0)
                            ->suffix('min')
                            ->helperText('Time needed to prepare room/equipment'),

                        Forms\Components\TextInput::make('cleanup_time')
                            ->label('Cleanup Time (minutes)')
                            ->numeric()
                            ->default(0)
                            ->suffix('min')
                            ->helperText('Time needed to clean up after service'),

                        Forms\Components\TextInput::make('max_advance_booking_days')
                            ->label('Max Advance Booking (days)')
                            ->numeric()
                            ->default(30)
                            ->suffix('days')
                            ->helperText('How far in advance can this service be booked?'),

                        Forms\Components\TextInput::make('cancellation_hours')
                            ->label('Cancellation Notice (hours)')
                            ->numeric()
                            ->default(24)
                            ->suffix('hours')
                            ->helperText('Minimum notice required for cancellation'),
                    ])->columns(2),

                Forms\Components\Section::make('Client Information & Safety')
                    ->schema([
                        Forms\Components\Textarea::make('instructions')
                            ->label('Pre-service Instructions')
                            ->placeholder('What clients should do before arriving (e.g., arrive early, avoid caffeine, wear comfortable clothing)')
                            ->maxLength(1000)
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('aftercare')
                            ->label('Aftercare Instructions')
                            ->placeholder('What clients should do after the service (e.g., drink water, avoid sun exposure, skincare routine)')
                            ->maxLength(1000)
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('contraindications')
                            ->label('Contraindications & Restrictions')
                            ->placeholder('Medical conditions, medications, or situations where this service should not be performed')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('age_restriction_min')
                            ->label('Minimum Age')
                            ->numeric()
                            ->suffix('years')
                            ->helperText('Minimum age requirement'),

                        Forms\Components\TextInput::make('age_restriction_max')
                            ->label('Maximum Age')
                            ->numeric()
                            ->suffix('years')
                            ->helperText('Maximum age recommendation (optional)'),

                        Forms\Components\Toggle::make('pregnancy_safe')
                            ->label('Pregnancy Safe')
                            ->default(false)
                            ->helperText('Safe for pregnant clients'),

                        Forms\Components\Toggle::make('requires_medical_clearance')
                            ->label('Requires Medical Clearance')
                            ->default(false)
                            ->helperText('Requires doctor approval for certain conditions'),
                    ])->columns(2),

                Forms\Components\Section::make('Marketing & Display')
                    ->schema([
                        Forms\Components\FileUpload::make('featured_image')
                            ->label('Featured Image')
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048)
                            ->helperText('Main promotional image for this service'),

                        Forms\Components\Repeater::make('gallery_images')
                            ->label('Gallery Images')
                            ->schema([
                                Forms\Components\FileUpload::make('image')
                                    ->image()
                                    ->required(),
                                Forms\Components\TextInput::make('caption')
                                    ->placeholder('Image caption'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Gallery Image')
                            ->collapsible(),

                        Forms\Components\TagsInput::make('seo_keywords')
                            ->label('SEO Keywords')
                            ->placeholder('Add relevant keywords for search optimization'),

                        Forms\Components\TextInput::make('promotion_tag')
                            ->label('Promotion Tag')
                            ->placeholder('e.g., "NEW", "POPULAR", "SEASONAL"')
                            ->helperText('Special tag displayed with the service'),

                        Forms\Components\Toggle::make('featured_service')
                            ->label('Featured Service')
                            ->default(false)
                            ->helperText('Display prominently on website'),

                        Forms\Components\Toggle::make('online_booking_enabled')
                            ->label('Online Booking Enabled')
                            ->default(true)
                            ->helperText('Allow clients to book this service online'),
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

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->money('KES')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('member_price')
                    ->label('Member Price')
                    ->money('KES')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => $state . ' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'coming_soon' => 'warning',
                        'seasonal' => 'info',
                        default => 'gray'
                    }),

                Tables\Columns\IconColumn::make('requires_consultation')
                    ->label('Consultation')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('this_month_bookings')
                    ->label('This Month')
                    ->getStateUsing(function (Service $record) {
                        $tenant = Filament::getTenant();
                        return \App\Models\Booking::where('branch_id', $tenant?->id)
                            ->where('service_id', $record->id)
                            ->whereMonth('appointment_date', now()->month)
                            ->whereYear('appointment_date', now()->year)
                            ->count();
                    })
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('this_month_revenue')
                    ->label('Revenue')
                    ->getStateUsing(function (Service $record) {
                        $tenant = Filament::getTenant();
                        return \App\Models\Booking::where('branch_id', $tenant?->id)
                            ->where('service_id', $record->id)
                            ->whereMonth('appointment_date', now()->month)
                            ->whereYear('appointment_date', now()->year)
                            ->where('payment_status', 'completed')
                            ->sum('total_amount');
                    })
                    ->money('KES')
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('branches_count')
                    ->label('Branches')
                    ->getStateUsing(function (Service $record) {
                        return $record->branches()->count();
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('staff_count')
                    ->label('Qualified Staff')
                    ->getStateUsing(function (Service $record) {
                        return $record->staff()->count();
                    })
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('featured_service')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('online_booking_enabled')
                    ->label('Online Booking')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'coming_soon' => 'Coming Soon',
                        'seasonal' => 'Seasonal',
                    ]),

                Tables\Filters\Filter::make('requires_consultation')
                    ->label('Requires Consultation')
                    ->query(fn (Builder $query): Builder => $query->where('requires_consultation', true))
                    ->toggle(),

                Tables\Filters\Filter::make('popular_this_month')
                    ->label('Popular This Month')
                    ->query(function (Builder $query): Builder {
                        $tenant = Filament::getTenant();
                        return $query->whereHas('bookings', function($q) use ($tenant) {
                            $q->where('branch_id', $tenant?->id)
                              ->whereMonth('appointment_date', now()->month)
                              ->whereYear('appointment_date', now()->year);
                        }, '>=', 5);
                    })
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('view_bookings')
                    ->label('Bookings')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->url(fn (Service $record) => route('filament.director.resources.bookings.index', [
                        'tenant' => Filament::getTenant()?->id,
                        'tableFilters[service_id][values][0]' => $record->id,
                    ])),

                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (Service $record): string => $record->status === 'active' ? 'Deactivate' : 'Activate')
                    ->icon(fn (Service $record): string => $record->status === 'active' ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Service $record): string => $record->status === 'active' ? 'warning' : 'success')
                    ->action(function (Service $record) {
                        $record->update([
                            'status' => $record->status === 'active' ? 'inactive' : 'active'
                        ]);
                    })
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (Service $record) {
                        $newService = $record->replicate();
                        $newService->name = $record->name . ' (Copy)';
                        $newService->save();
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
                            $records->each(fn (Service $record) => $record->update(['status' => 'active']));
                        })
                        ->requiresConfirmation(),
                        
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->action(function ($records) {
                            $records->each(fn (Service $record) => $record->update(['status' => 'inactive']));
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No services found')
            ->emptyStateDescription('Start by adding services that can be booked at this branch.')
            ->emptyStateIcon('heroicon-o-sparkles');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BranchesRelationManager::class,
            RelationManagers\StaffRelationManager::class,
            RelationManagers\BookingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'view' => Pages\ViewService::route('/{record}'),
            'edit' => Pages\EditService::route('/{record}/edit'),
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