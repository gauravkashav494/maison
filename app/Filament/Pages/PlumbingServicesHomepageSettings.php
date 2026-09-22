<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use App\Models\Service;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Homepage content for the Plumbing Services template: hero slides, quick actions,
 * section order, problem tiles (mapped to services), emergency block, how-it-works,
 * why-us, headings/limits for the data-driven rails and the final CTA.
 */
class PlumbingServicesHomepageSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 51;

    protected static ?string $title = 'Plumbing Services — Homepage';

    protected static ?string $navigationLabel = 'Plumbing Services · Homepage';

    public const SECTIONS = [
        'popular' => 'Popular services',
        'emergency' => 'Emergency plumbing',
        'problems' => 'What’s the problem?',
        'how' => 'How it works',
        'why' => 'Why choose us',
        'areas' => 'Service areas',
        'reviews' => 'Customer reviews',
        'projects' => 'Recent work',
        'quote' => 'Quote request',
        'faq' => 'FAQs',
        'blog' => 'Tips & guides',
        'cta' => 'Final call to action',
    ];

    public const ICONS = ['alert', 'droplet', 'drain', 'gauge', 'tap', 'toilet', 'bath', 'kitchen', 'pump', 'tank', 'flame', 'pipe', 'layers', 'wrench', 'building', 'clipboard', 'calendar', 'file', 'whatsapp', 'phone', 'help', 'badge', 'bolt', 'rupee', 'shield', 'clock', 'map-pin', 'chat', 'user-check', 'check-badge', 'star', 'sparkles'];

    protected function settingGroups(): array
    {
        return ['plumbing_services_home'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('plumbing-services')->defaults()['home'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'plumbing_services_home';
        $icons = array_combine(self::ICONS, array_map(fn ($i) => str($i)->headline()->value(), self::ICONS));
        $services = fn () => Service::withoutGlobalScope(\App\Templates\Scopes\TemplateVisibility::NAME)->where('template', 'plumbing-services')->orderBy('name')->pluck('name', 'slug')->all();
        $heading = fn (string $key, string $label, bool $sub = true, ?int $limitMax = null, int $limitMin = 3) => Section::make($label)->columns($limitMax ? 3 : 2)->schema(array_filter([
            TextInput::make("$g.{$key}_heading")->label('Heading'),
            $sub ? TextInput::make("$g.{$key}_sub")->label('Sub-heading') : null,
            $limitMax ? TextInput::make("$g.{$key}_limit")->label('Items shown')->numeric()->minValue($limitMin)->maxValue($limitMax) : null,
        ]));

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Plumbing Services homepage')->persistTabInQueryString()->tabs([
                    Tab::make('Hero')->icon('heroicon-o-photo')->schema([
                        Repeater::make("$g.hero_slides")->label('Hero slides')->helperText('One slide shows a static hero; two or more rotate. Secondary action "Call" and "WhatsApp" use the numbers from Business settings.')->schema([
                            TextInput::make('eyebrow')->label('Eyebrow'),
                            TextInput::make('title')->label('Heading')->required(),
                            Textarea::make('text')->label('Text')->rows(2)->columnSpan(2),
                            TextInput::make('cta_label')->label('Primary button'),
                            TextInput::make('cta_url')->label('Primary link')->placeholder('/book'),
                            TextInput::make('secondary_label')->label('Secondary button'),
                            Select::make('secondary_action')->label('Secondary action')->options(['call' => 'Call', 'whatsapp' => 'WhatsApp', 'emergency' => 'Emergency page', 'quote' => 'Quote page'])->native(false),
                            Fields::image('image', 'Image (1400×900)', 'plumbing-services')->columnSpan(2),
                        ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['title'] ?? null)->minItems(1)->maxItems(5),
                    ]),

                    Tab::make('Sections')->icon('heroicon-o-bars-3-bottom-left')->schema([
                        Repeater::make("$g.sections")->label('Order & visibility')->helperText('Drag to reorder the homepage. Switch a section off to hide it without losing its content. The hero and mobile quick actions always come first.')
                            ->schema([Grid::make(2)->schema([
                                Select::make('key')->label('Section')->options(self::SECTIONS)->required()->native(false)->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Toggle::make('enabled')->label('Visible')->default(true)->inline(false),
                            ])])
                            ->reorderable()->itemLabel(fn (array $state): ?string => self::SECTIONS[$state['key'] ?? ''] ?? null)->addActionLabel('Add section')->maxItems(count(self::SECTIONS)),
                    ]),

                    Tab::make('Quick actions & problems')->icon('heroicon-o-squares-2x2')->schema([
                        Repeater::make("$g.quick_actions")->label('Quick actions (mobile shortcuts under the hero)')->schema([
                            TextInput::make('label')->required(),
                            Select::make('icon')->options($icons)->required()->native(false),
                            TextInput::make('url')->label('Link')->helperText('Leave blank when using an action.'),
                            Select::make('action')->label('Action')->options(['call' => 'Call', 'whatsapp' => 'WhatsApp'])->native(false),
                            Select::make('tone')->options(['primary' => 'Blue', 'accent' => 'Orange', 'danger' => 'Red (emergency)', 'whatsapp' => 'Green (WhatsApp)'])->native(false),
                        ])->columns(5)->maxItems(4)->minItems(2),
                        $heading('problems', 'What’s the problem?'),
                        Repeater::make("$g.problems")->label('Problem tiles')->helperText('Each tile opens the mapped service page. Leave the service blank to send the visitor straight to the booking flow with the problem pre-filled.')->schema([
                            TextInput::make('label')->required(),
                            Select::make('icon')->options($icons)->required()->native(false),
                            Select::make('service')->label('Service')->options($services)->searchable()->native(false),
                        ])->columns(3)->reorderable()->maxItems(12),
                    ]),

                    Tab::make('Services & emergency')->icon('heroicon-o-wrench-screwdriver')->schema([
                        $heading('popular', 'Popular services', true, 12, 4)->description('Services flagged "Popular" appear first, then the rest in their sort order. Manage services under Content → Services.'),
                        Section::make('Emergency block')->columns(1)->schema([
                            TextInput::make("$g.emergency_heading")->label('Heading'),
                            Textarea::make("$g.emergency_text")->label('Text')->rows(2),
                            TagsInput::make("$g.emergency_points")->label('Points'),
                        ]),
                    ]),

                    Tab::make('How & why')->icon('heroicon-o-light-bulb')->schema([
                        TextInput::make("$g.how_heading")->label('How it works — heading'),
                        Repeater::make("$g.how_steps")->label('Steps')->schema([
                            TextInput::make('title')->required(), Select::make('icon')->options($icons)->native(false), TextInput::make('text')->columnSpan(2),
                        ])->columns(2)->reorderable()->maxItems(5)->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                        TextInput::make("$g.why_heading")->label('Why choose us — heading'),
                        Repeater::make("$g.why_items")->label('Points')->schema([
                            TextInput::make('title')->required(), Select::make('icon')->options($icons)->native(false), TextInput::make('text')->columnSpan(2),
                        ])->columns(2)->reorderable()->maxItems(8)->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                    ]),

                    Tab::make('Areas, reviews & work')->icon('heroicon-o-map-pin')->schema([
                        $heading('areas', 'Service areas')->description('Areas come from Content → Service areas.'),
                        $heading('reviews', 'Customer reviews', false, 12)->description('Reviews come from Content → Testimonials.'),
                        $heading('projects', 'Recent work', true, 12)->description('Projects come from Content → Projects.'),
                    ]),

                    Tab::make('FAQ, blog & CTA')->icon('heroicon-o-megaphone')->schema([
                        $heading('faq', 'FAQs', false, 12)->description('FAQs come from Content → FAQs (Plumbing Services tab).'),
                        $heading('blog', 'Tips & guides', false),
                        Section::make('Final call to action')->columns(2)->schema([
                            TextInput::make("$g.cta_heading")->label('Heading'),
                            Fields::image("$g.cta_image", 'Image (1200×800)', 'plumbing-services'),
                            Textarea::make("$g.cta_text")->label('Text')->rows(2)->columnSpan(2),
                        ]),
                    ]),
                ]),
            ]);
    }
}
