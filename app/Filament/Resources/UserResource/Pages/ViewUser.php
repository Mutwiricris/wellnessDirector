<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Personal Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Full Name')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight('bold')
                                    ->icon('heroicon-o-user'),
                                Infolists\Components\TextEntry::make('email')
                                    ->icon('heroicon-o-envelope')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('phone')
                                    ->icon('heroicon-o-phone')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('date_of_birth')
                                    ->label('Date of Birth')
                                    ->date()
                                    ->icon('heroicon-o-cake')
                                    ->placeholder('Not provided'),
                                Infolists\Components\TextEntry::make('gender')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'male' => 'blue',
                                        'female' => 'pink',
                                        'other' => 'gray',
                                        'prefer_not_to_say' => 'gray',
                                        default => 'gray'
                                    })
                                    ->placeholder('Not specified'),
                            ]),
                    ])->columns(1),

                Infolists\Components\Section::make('Health & Preferences')
                    ->schema([
                        Infolists\Components\Grid::make(1)
                            ->schema([
                                Infolists\Components\TextEntry::make('allergies')
                                    ->label('Allergies & Sensitivities')
                                    ->placeholder('No allergies recorded')
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->color('warning'),
                                Infolists\Components\KeyValueEntry::make('preferences')
                                    ->label('Service Preferences')
                                    ->placeholder('No preferences recorded'),
                            ]),
                    ])->columns(1),

                Infolists\Components\Section::make('Account Settings')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('create_account_status')
                                    ->label('Account Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'accepted' => 'warning',
                                        'no_creation' => 'gray',
                                        default => 'gray'
                                    }),
                                Infolists\Components\IconEntry::make('marketing_consent')
                                    ->boolean()
                                    ->label('Marketing Consent')
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('gray'),
                                Infolists\Components\TextEntry::make('user_type')
                                    ->label('User Type')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                                    ->color('info'),
                            ]),
                    ])->columns(1),

                Infolists\Components\Section::make('System Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime()
                                    ->label('Client Since'),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->dateTime()
                                    ->label('Last Updated'),
                            ]),
                    ])->columns(1)
                    ->collapsible(),
            ]);
    }
}