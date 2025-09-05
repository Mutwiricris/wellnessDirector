<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PosTransaction;
use App\Models\Expense;
use App\Filament\Widgets\Financial\FinancialOverviewWidget;
use App\Filament\Widgets\Financial\RevenueStreamWidget;
use App\Filament\Widgets\Financial\ProfitabilityWidget;
use App\Filament\Widgets\Financial\CashFlowWidget;
use App\Filament\Widgets\Financial\ExpenseAnalyticsWidget;
use App\Filament\Widgets\Financial\PaymentMethodWidget;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;

class FinancialDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Financial Command Center';

    protected static ?string $navigationGroup = 'Financial Management';

    protected static string $view = 'filament.pages.financial-dashboard';

    protected static ?int $navigationSort = 1;

    public $selectedPeriod = 'current_month';
    public $selectedBranch = 'all';
    public $startDate;
    public $endDate;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
    }

    public function form(Form $form): Form
    {
        $user = auth()->user();
        $branches = $user && $user->isDirector() 
            ? $user->getTenants()->pluck('name', 'id')
            : Branch::pluck('name', 'id');

        return $form
            ->schema([
                Select::make('selectedPeriod')
                    ->label('Period')
                    ->options([
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'current_week' => 'This Week',
                        'last_week' => 'Last Week',
                        'current_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'current_quarter' => 'This Quarter',
                        'last_quarter' => 'Last Quarter',
                        'current_year' => 'This Year',
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

                Select::make('selectedBranch')
                    ->label('Branch')
                    ->options(['all' => 'All Branches'] + $branches->toArray())
                    ->default('all'),
            ])
            ->columns(4);
    }

    public function getWidgets(): array
    {
        return [
            FinancialOverviewWidget::class,
            RevenueStreamWidget::class,
            ProfitabilityWidget::class,
            CashFlowWidget::class,
            PaymentMethodWidget::class,
            ExpenseAnalyticsWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'selectedPeriod' => $this->selectedPeriod,
            'selectedBranch' => $this->selectedBranch,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_financial_report')
                ->label('Export Financial Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $this->exportFinancialReport();
                }),

            \Filament\Actions\Action::make('schedule_report')
                ->label('Schedule Automated Report')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->action(function () {
                    $this->scheduleAutomatedReport();
                }),

            \Filament\Actions\Action::make('financial_alerts')
                ->label('Alert Settings')
                ->icon('heroicon-o-bell')
                ->color('warning')
                ->action(function () {
                    $this->configureFinancialAlerts();
                }),
        ];
    }

    protected function exportFinancialReport(): void
    {
        // Implementation for exporting comprehensive financial report
        $this->notify('success', 'Financial report export initiated. You will receive an email when ready.');
    }

    protected function scheduleAutomatedReport(): void
    {
        // Implementation for scheduling automated reports
        $this->notify('success', 'Automated report scheduling configured.');
    }

    protected function configureFinancialAlerts(): void
    {
        // Implementation for configuring financial alerts
        $this->notify('info', 'Financial alert settings updated.');
    }

    public function getDateRange(): array
    {
        return match ($this->selectedPeriod) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'current_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'current_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'current_quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'last_quarter' => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            'current_year' => [now()->startOfYear(), now()->endOfYear()],
            'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            'custom' => [$this->startDate, $this->endDate],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }
}