<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Name'),
                        TextEntry::make('user.email')
                            ->label('Email'),
                        TextEntry::make('shipping_address')
                            ->state(fn (Order $record): array => self::address($record, 'shipping'))
                            ->placeholder('-')
                            ->listWithLineBreaks(),
                        TextEntry::make('billing_address')
                            ->state(fn (Order $record): array => self::address($record, 'billing'))
                            ->placeholder('-')
                            ->listWithLineBreaks(),
                    ]),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label('Product'),
                                TextEntry::make('unit_price')
                                    ->money('PHP'),
                                TextEntry::make('quantity'),
                                TextEntry::make('line_total')
                                    ->money('PHP'),
                            ]),
                    ]),

                Section::make('Coupon')
                    ->columns(2)
                    ->hidden(fn (Order $record): bool => blank($record->coupon_code))
                    ->schema([
                        TextEntry::make('coupon_code')
                            ->label('Code')
                            ->placeholder('None'),
                        TextEntry::make('discount')
                            ->money('PHP'),
                    ]),

                Section::make('Totals')
                    ->columns(5)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->money('PHP'),
                        TextEntry::make('discount')
                            ->money('PHP'),
                        TextEntry::make('tax')
                            ->money('PHP'),
                        TextEntry::make('shipping_fee')
                            ->money('PHP'),
                        TextEntry::make('total')
                            ->money('PHP')
                            ->weight('bold'),
                    ]),

                Section::make('Status')
                    ->schema([
                        TextEntry::make('status')
                            ->badge(),
                    ]),
            ]);
    }

    /**
     * The non-empty lines of a shipping or billing address.
     *
     * @return list<string>
     */
    private static function address(Order $order, string $prefix): array
    {
        $region = collect([
            $order->{$prefix.'_city'},
            $order->{$prefix.'_state'},
            $order->{$prefix.'_postal_code'},
        ])->filter()->implode(', ');

        return array_values(collect([
            $order->{$prefix.'_name'},
            $order->{$prefix.'_address_line1'},
            $order->{$prefix.'_address_line2'},
            $region,
            $order->{$prefix.'_country'},
        ])->filter()->map(fn (mixed $line): string => (string) $line)->all());
    }
}
