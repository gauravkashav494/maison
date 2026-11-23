<?php

namespace App\Filament\Resources\Testimonials\Pages;

use App\Filament\Concerns\HasTemplateTabs;
use App\Filament\Resources\Testimonials\TestimonialResource;
use Filament\Resources\Pages\ListRecords;

class ListTestimonials extends ListRecords
{
    use HasTemplateTabs;

    protected static string $resource = TestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->templateCreateAction(),
        ];
    }
}
