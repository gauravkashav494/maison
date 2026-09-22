<?php

namespace App\Filament\Resources\ServiceRequests\Schemas;

use App\Models\ServiceRequest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Status')->columns(3)->schema([
                TextInput::make('reference')->disabled()->dehydrated(false),
                Select::make('type')->options(ServiceRequest::TYPES)->required()->native(false),
                Select::make('status')->options(ServiceRequest::STATUSES)->required()->native(false),
            ]),
            Section::make('Request')->columns(2)->schema([
                Select::make('service_id')->label('Service')->relationship('service', 'name', fn ($q) => $q->withoutGlobalScope(\App\Templates\Scopes\TemplateVisibility::NAME))->searchable()->preload()->native(false),
                TextInput::make('service_name')->label('Service (as requested)')->maxLength(120),
                Textarea::make('problem')->rows(3)->columnSpan(2),
                DatePicker::make('preferred_date')->native(false),
                Select::make('time_slot')->options(ServiceRequest::SLOTS)->native(false),
                TextInput::make('address')->maxLength(300)->columnSpan(2),
                TextInput::make('area')->maxLength(120),
                Select::make('service_area_id')->label('Service area')->relationship('serviceArea', 'name', fn ($q) => $q->withoutGlobalScope(\App\Templates\Scopes\TemplateVisibility::NAME))->searchable()->preload()->native(false),
            ]),
            Section::make('Customer')->columns(3)->schema([
                TextInput::make('name')->required()->maxLength(80),
                TextInput::make('phone')->required()->maxLength(30),
                TextInput::make('email')->email()->maxLength(190),
            ]),
            Textarea::make('admin_notes')->label('Internal notes')->rows(3),
        ]);
    }
}
