<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([20, 50, 100])
            ->contentGrid([
                'md' => 3,
                'xl' => 4,
            ])
            ->columns([
                Stack::make([
                    TextColumn::make('name')->searchable(),

                    TextColumn::make('category.name')
                        ->badge(),

                    TextColumn::make('price')
                        ->sortable()
                        ->formatStateUsing(fn($state) => '₱' . number_format($state, 2)),

                    TextColumn::make('stock')
                        ->sortable()
                        ->color(fn($state) => $state > 0 ? 'success' : 'danger'),

                    TextColumn::make('created_at')
                        ->sortable()
                        ->dateTime('m/d/Y h:i A'),

                    TextColumn::make('updated_at')
                        ->sortable()
                        ->dateTime('m/d/Y h:i A'),
                ])
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),

                SelectFilter::make('stock')->options([
                    'in_stock' => 'In Stock',
                    'out_of_stock' => 'Out of Stock',
                ])
                    ->query(fn(Builder $query, array $data) => $data['value'] === 'out_of_stock' ? $query->where('stock', '=', 0) : $query->where('stock', '>', 0)),

                Filter::make('created_at')
                    ->schema([
                        DateTimePicker::make('created_from')->native(false),
                        DateTimePicker::make('created_until')
                            ->native(false)
                            ->default(now()),
                    ]),

                Filter::make('updated_at')
                    ->schema([
                        DateTimePicker::make('updated_from')->native(false),
                        DateTimePicker::make('updated_until')
                            ->native(false)
                            ->default(now()),
                    ])
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
