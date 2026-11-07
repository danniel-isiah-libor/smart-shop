<?php

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $relatedResource = ProductResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('New Product')
                    ->url(fn () => CreateProduct::getUrl(['category_id' => $this->getOwnerRecord()->id])),
            ]);
    }
}
