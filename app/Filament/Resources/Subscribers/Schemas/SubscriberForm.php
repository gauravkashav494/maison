<?php

namespace App\Filament\Resources\Subscribers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(\App\Clients\ClientContext::class)->id())),
            TextInput::make('source')->maxLength(60)->placeholder('homepage, footer…'),
        ]);
    }
}
