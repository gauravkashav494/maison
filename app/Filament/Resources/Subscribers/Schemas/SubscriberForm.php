<?php

namespace App\Filament\Resources\Subscribers\Schemas;

use App\Models\Subscriber;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInput\Actions\CopyAction;
use Filament\Schemas\Schema;

class SubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(\App\Clients\ClientContext::class)->id())),
            TextInput::make('source')->maxLength(60)->placeholder('homepage, footer…'),
            // Read-only: the personal link to put in emails sent to this subscriber.
            TextInput::make('unsubscribe_link')->label('Unsubscribe link')->columnSpanFull()
                ->visibleOn('edit')->readOnly()->dehydrated(false)
                ->formatStateUsing(fn (?Subscriber $record) => $record?->unsubscribeUrl())
                ->helperText(fn (?Subscriber $record) => $record?->isUnsubscribed()
                    ? 'Unsubscribed on '.$record->unsubscribed_at->format('d M Y, H:i').'.'
                    : 'Put this in every email you send them. Opening it asks them to confirm before they are taken off the list.')
                ->suffixAction(CopyAction::make()->copyMessage('Link copied')),
        ]);
    }
}
