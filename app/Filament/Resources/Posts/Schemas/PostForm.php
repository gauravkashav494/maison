<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Story')->tabs([
                    Tab::make('Content')
                        ->icon('heroicon-o-newspaper')
                        ->schema([
                            Grid::make(2)->schema([
                                ...Fields::nameAndSlug('title', 'Title'),
                                TextInput::make('category')->maxLength(60)->placeholder('Editorial, Style Notes, Campaign…'),
                                TextInput::make('read_time')->maxLength(30)->placeholder('6 min read'),
                                DateTimePicker::make('published_at')->label('Publish at')->seconds(false)->default(now()),
                                Toggle::make('is_featured')->label('Lead story on homepage')->inline(false),
                            ]),
                            Textarea::make('excerpt')->rows(3)->maxLength(400)->required(),
                            RichEditor::make('body'),
                            Fields::image('image', 'Cover image (portrait 4:5)', 'journal')->required(),
                        ]),
                    Fields::seoTab(),
                ]),
            ]);
    }
}
