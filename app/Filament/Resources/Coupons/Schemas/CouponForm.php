<?php

namespace App\Filament\Resources\Coupons\Schemas;

use App\Clients\ClientContext;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Code')->columns(2)->schema([
                TextInput::make('code')->required()->maxLength(40)->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(ClientContext::class)->id()))->helperText('Customers enter this at checkout. Case-insensitive.'),
                TextInput::make('description')->maxLength(120)->placeholder('10% off your first order'),
                Select::make('type')
                    ->options(['percent' => 'Percentage off', 'fixed' => 'Fixed amount off', 'free_shipping' => 'Free shipping'])
                    ->default('percent')->required()->live()->native(false),
                TextInput::make('value')
                    ->numeric()->minValue(0)->default(0)
                    ->label(fn (Get $get) => $get('type') === 'percent' ? 'Percent off' : 'Amount off (₹)')
                    ->suffix(fn (Get $get) => $get('type') === 'percent' ? '%' : null)
                    ->hidden(fn (Get $get) => $get('type') === 'free_shipping'),
            ]),
            Section::make('Rules')->columns(2)->schema([
                TextInput::make('min_subtotal')->label('Minimum subtotal')->numeric()->minValue(0)
                    ->rule('gte:0')
                    ->validationMessages(['min' => 'Minimum subtotal cannot be negative.', 'gte' => 'Minimum subtotal cannot be negative.'])
                    ->extraInputAttributes(['min' => '0', 'onkeydown' => "if (event.key === '-') event.preventDefault()"])
                    ->prefix('₹')->default(0),
                TextInput::make('usage_limit')->label('Total uses allowed')->numeric()->minValue(1)->placeholder('Unlimited'),
                DateTimePicker::make('starts_at')->native(false)->seconds(false)->live(),
                DateTimePicker::make('ends_at')->native(false)->seconds(false)->afterOrEqual('starts_at')
                    ->validationMessages(['after_or_equal' => 'The end date must be on or after the start date.']),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
            Grid::make(1)->schema([
                TextInput::make('used_count')->label('Times used')->disabled()->dehydrated(false),
            ]),
        ]);
    }
}
