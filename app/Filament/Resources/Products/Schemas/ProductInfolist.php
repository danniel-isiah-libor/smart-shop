<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('category.name')->badge(),

                TextEntry::make('name'),

                TextEntry::make('description'),

                TextEntry::make('price')
                    ->formatStateUsing(fn($state) => '₱' . number_format($state, 2)),

                TextEntry::make('stock'),

                TextEntry::make('created_at')
                    ->formatStateUsing(fn($state) => $state->format('m/d/Y h:i A')),

                TextEntry::make('updated_at')
                    ->formatStateUsing(fn($state) => $state->format('m/d/Y h:i A')),
            ]);
    }
}
