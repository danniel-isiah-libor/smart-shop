<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\URL;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->default(fn () => URL::getRequest()->query('category_id'))
                    ->required(),

                TextInput::make('name')->required(),

                Textarea::make('description')->rows(3)->required(),

                TextInput::make('price')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.01),

                TextInput::make('stock'),

                SpatieMediaLibraryFileUpload::make('images')
                    ->collection(Product::IMAGE_COLLECTION)
                    ->conversion('thumb')
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->acceptedFileTypes(Product::IMAGE_MIME_TYPES)
                    ->maxFiles(Product::MAX_IMAGES)
                    ->maxSize(Product::MAX_IMAGE_SIZE_KB)
                    ->helperText('Up to 5 images (JPG, JPEG or PNG), 2 MB each.')
                    ->circleCropper()
                    ->imageEditor()
                    ->visibility('public')
                    ->disk('public')
                    ->columnSpanFull(),
            ]);
    }
}
