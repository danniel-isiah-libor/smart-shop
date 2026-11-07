<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('products_count')->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('m/d/Y H:i A'),

                TextColumn::make('updated_at')
                    ->dateTime('m/d/Y H:i A'),
            ])
            ->filters([
                Filter::make('created_at')
                    ->schema([
                        DateTimePicker::make('created_from')
                            ->label('Created From'),

                        DateTimePicker::make('created_until')
                            ->label('Created Until'),
                    ]),

                Filter::make('updated_at')
                    ->schema([
                        DateTimePicker::make('updated_from')
                            ->label('Updated From'),

                        DateTimePicker::make('updated_until')
                            ->label('Updated Until'),
                    ])
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
