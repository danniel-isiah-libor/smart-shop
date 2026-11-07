<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),

                TextInput::make('name')->required(),

                Textarea::make('description')->rows(3)->required(),

                TextInput::make('price')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.01),

                TextInput::make('stock'),
            ]);
    }
}
