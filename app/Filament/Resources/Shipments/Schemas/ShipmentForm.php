<?php

namespace App\Filament\Resources\Shipments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ShipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('courier')
                    ->required(),
                TextInput::make('tracking_number')
                    ->unique(ignoreRecord: true)
                    ->required(),
                DateTimePicker::make('shipped_at'),
            ]);
    }
}
