<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\User;
use App\Filament\Widgets\CrossBranch\BranchComparisonWidget;
use App\Filament\Widgets\CrossBranch\PerformanceMatrixWidget;
use App\Filament\Widgets\CrossBranch\RevenueComparisonWidget;
use App\Filament\Widgets\CrossBranch\ClientDistributionWidget;
use App\Filament\Widgets\CrossBranch\ServicePopularityWidget;
use App\Filament\Widgets\CrossBranch\StaffUtilizationWidget;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;

class CrossBranchAnalytics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Cross-Branch Analytics';

    protected static ?string $navigationGroup = 'Strategic Analytics';

    protected static string $view = 'filament.pages.cross-branch-analytics';

    protected static ?int $navigationSort = 1;

    public $selectedPeriod = 'current_month';
    public $selectedBranches = [];
    public $startDate;
    public $endDate;

    public function mount(): void
    {
        // Get current user's accessible branches
        $user = auth()->user();
        if ($user && $user->isDirector()) {
            $this->selectedBranches = $user->getTenants()->pluck('id')->toArray();
        }
        
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedPeriod')
                    ->label('Time Period')
                    ->options([
                        'current_month' => 'Current Month',
                        'last_month' => 'Last Month',
                        'current_quarter' => 'Current Quarter',
                        'last_quarter' => 'Last Quarter',
                        'current_year' => 'Current Year',
                        'last_year' => 'Last Year',
                        'custom' => 'Custom Range',
                    ])
                    ->default('current_month')
                    ->reactive(),

                DatePicker::make('startDate')
                    ->label('Start Date')
                    ->visible(fn ($get) => $get('selectedPeriod') === 'custom')
                    ->required(fn ($get) => $get('selectedPeriod') === 'custom'),

                DatePicker::make('endDate')
                    ->label('End Date')
                    ->visible(fn ($get) => $get('selectedPeriod') === 'custom')
                    ->required(fn ($get) => $get('selectedPeriod') === 'custom')
                    ->after('startDate'),

                Select::make('selectedBranches')
                    ->label('Compare Branches')
                    ->options(Branch::pluck('name', 'id'))
                    ->multiple()
                    ->default(Branch::pluck('id')->toArray())
                    ->searchable(),
            ])
            ->columns(4);
    }

    public function getWidgets(): array
    {
        return [
            BranchComparisonWidget::class,
            RevenueComparisonWidget::class,
            PerformanceMatrixWidget::class,
            ClientDistributionWidget::class,
            ServicePopularityWidget::class,
            StaffUtilizationWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'selectedPeriod' => $this->selectedPeriod,
            'selectedBranches' => $this->selectedBranches,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_report')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    // Export functionality would be implemented here
                    $this->notify('success', 'Report export initiated');
                }),

            \Filament\Actions\Action::make('schedule_report')
                ->label('Schedule Report')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->action(function () {
                    // Schedule report functionality
                    $this->notify('success', 'Report scheduled successfully');
                }),
        ];
    }
}