<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderStatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Status history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('to_status')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime(),
                TextColumn::make('from_status')
                    ->label('From')
                    ->badge()
                    ->placeholder('Created'),
                TextColumn::make('to_status')
                    ->label('To')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('Changed by')
                    ->placeholder('System'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
