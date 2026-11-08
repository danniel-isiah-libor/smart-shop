<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderStatusActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('total')
                    ->money('PHP')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['created_from'] ?? null) {
                            $indicators[] = Indicator::make(__('Created from :date', ['date' => Carbon::parse($data['created_from'])->toFormattedDateString()]))
                                ->removeField('created_from');
                        }

                        if ($data['created_until'] ?? null) {
                            $indicators[] = Indicator::make(__('Created until :date', ['date' => Carbon::parse($data['created_until'])->toFormattedDateString()]))
                                ->removeField('created_until');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make(OrderStatusActions::make()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
