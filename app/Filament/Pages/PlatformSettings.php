<?php

namespace App\Filament\Pages;

use App\Filament\Auth\Login;
use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The platform's own settings, for the super admin only: its name and the look of the
 * super-admin sign-in page. Each client's brand, contact, SEO and checkout live in that
 * client's Site settings (client panel), never here.
 */
class PlatformSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Platform settings';

    protected static ?string $slug = 'platform-settings';

    public static function canAccess(): bool
    {
        return (bool) filament()->auth()->user()?->isSuperAdmin();
    }

    protected function settingGroups(): array
    {
        return ['platform'];
    }

    /** First visit: start from the values the old platform-wide Site settings held. */
    protected function defaultsFor(string $group): array
    {
        return [
            'name' => config('app.name'),
            'admin_login_style' => Setting::get('site.admin_login_style', 'classic'),
            'login_image' => null, // empty = the default workspace image (Login::PLATFORM_IMAGE)
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Platform')->schema([
                    TextInput::make('platform.name')->label('Platform name')->required()->maxLength(60)
                        ->helperText('Shown in the super-admin panel header, sidebar footer and browser tab.'),
                ]),
                Section::make('Super-admin sign-in page')->columns(2)->schema([
                    Select::make('platform.admin_login_style')->label('Style')->options(Login::STYLES)->default('classic')->native(false)->required(),
                    Fields::image('platform.login_image', 'Background image (leave empty for the default workspace photo)', 'platform'),
                ]),
            ]);
    }
}
