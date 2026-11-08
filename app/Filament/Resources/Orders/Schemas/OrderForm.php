<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Customer')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('status')
                    ->options(fn (string $operation): array => collect(OrderStatus::cases())
                        ->when($operation === 'create', fn ($cases) => $cases->filter(fn (OrderStatus $status): bool => $status === OrderStatus::Pending))
                        ->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->getLabel()])
                        ->all())
                    ->default(OrderStatus::Pending)
                    ->disabled()
                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Change the status with the order actions.')
                    ->required(),

                Repeater::make('items')
                    ->relationship()
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                $set('unit_price', Product::find($state)?->price);
                            }),

                        TextInput::make('quantity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        TextInput::make('unit_price')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('₱')
                            ->required(),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::withLineDetails($data))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::withLineDetails($data))
                    ->columns(3)
                    ->minItems(1)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Add the product name snapshot and line total to an order item's form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withLineDetails(array $data): array
    {
        $data['product_name'] = Product::findOrFail($data['product_id'])->name;
        $data['line_total'] = round($data['unit_price'] * $data['quantity'], 2);

        return $data;
    }
}
