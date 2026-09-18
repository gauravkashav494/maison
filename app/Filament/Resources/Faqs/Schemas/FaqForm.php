<?php

namespace App\Filament\Resources\Faqs\Schemas;

use App\Filament\Support\Fields;
use App\Models\Faq;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(2)->schema([
                Select::make('category')->options(array_combine(Faq::CATEGORIES, Faq::CATEGORIES))->required()->native(false),
                Toggle::make('is_active')->label('Visible')->default(true)->inline(false),
                Fields::templateVisibility(),
            ]),
            TextInput::make('question')->required()->maxLength(200),
            Textarea::make('answer')->rows(5)->required(),
        ]);
    }
}
