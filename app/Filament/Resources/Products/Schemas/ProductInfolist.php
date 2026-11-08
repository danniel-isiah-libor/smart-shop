<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Product;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SpatieMediaLibraryImageEntry::make('images')
                    ->collection(Product::IMAGE_COLLECTION)
                    ->conversion('thumb')
                    ->imageSize(120)
                    ->columnSpanFull(),

                TextEntry::make('category.name')
                    ->url(fn ($record) => EditCategory::getUrl(['record' => $record->category_id]))
                    ->badge()
                    ->label('Category'),

                TextEntry::make('name'),

                TextEntry::make('description'),

                TextEntry::make('price')
                    ->formatStateUsing(fn ($state) => '₱'.number_format($state, 2)),

                TextEntry::make('stock'),

                TextEntry::make('created_at')
                    ->formatStateUsing(fn ($state) => $state->format('m/d/Y h:i A')),

                TextEntry::make('updated_at')
                    ->formatStateUsing(fn ($state) => $state->format('m/d/Y h:i A')),
            ]);
    }
}
