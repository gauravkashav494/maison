<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Clients\ClientProvisioner;
use App\Clients\ClientSetup;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Models\ClientUser;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateClient extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = ClientResource::class;

    /** The first Client Admin, taken out of the form data before the client row is saved. */
    protected array $admin = [];

    /** Creating a client is a guided wizard; each step is checked by the server before the next. */
    public function getSteps(): array
    {
        return [
            ...ClientForm::steps(),
            Step::make('Client admin')->icon('heroicon-o-user-circle')
                ->description('Who signs in to run the store')
                ->schema([$this->adminSection()]),
        ];
    }

    /** The store's first Client Admin, with the password they will sign in with. */
    protected function adminSection(): Section
    {
        return Section::make('Client admin')
            ->description('This person can do everything in the store\'s panel, including adding their own team. Share the email and password with them; more admins can be added later when editing the client.')
            ->statePath('admin')
            ->columns(2)
            ->schema([
                TextInput::make('name')->label('Full name')->required()->maxLength(120),
                TextInput::make('email')->label('Email')->email()->required()->maxLength(190),
                TextInput::make('phone')->label('Phone (optional)')->tel()->maxLength(30),
                TextInput::make('password')->label('Password')->password()->revealable()->required()->minLength(8)
                    ->hint('Minimum 8 characters')
                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Use at least 8 characters. A mix of letters, numbers and symbols is stronger.'),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->admin = $data['admin'] ?? [];
        unset($data['admin']);

        return $data;
    }

    /** Where the admin's account stands, for the one "created" message (set by createAdmin). */
    protected ?string $adminSummary = null;

    /**
     * A new client gets its admin account straight away; its database is built in the
     * background (ClientSetup), so this request answers at once.
     */
    protected function afterCreate(): void
    {
        $this->createAdmin();

        // Only once the client row is committed, or the background process may not find it yet.
        $client = $this->getRecord();
        $withSamples = (bool) ($this->data['sample_products'] ?? false);
        DB::afterCommit(fn () => app(ClientSetup::class)->startInBackground($client, $withSamples));
    }

    /**
     * One create at a time per admin, even if the button is pressed twice or two requests arrive
     * together: the second one is refused instead of making a duplicate client.
     */
    public function create(bool $another = false): void
    {
        $lock = Cache::lock('clients:create:'.(filament()->auth()->id() ?? 'guest'), 120);
        if (! $lock->get()) {
            Notification::make()->warning()->title('Already creating this client')->body('Please wait — it will open as soon as it is ready.')->send();

            return;
        }

        try {
            parent::create($another);
        } finally {
            $lock->release();
        }
    }

    /** Straight to the new client's page, which shows the database setup until it is ready. */
    protected function getRedirectUrl(): string
    {
        return ClientResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    /** One message for the whole create: the client, its admin, and the database in progress. */
    protected function getCreatedNotification(): ?Notification
    {
        $lines = array_filter([
            $this->adminSummary,
            app(ClientProvisioner::class)->isNeeded() ? 'Its database is being set up — the client page shows when it is ready.' : null,
        ]);

        return Notification::make()->success()
            ->title($this->getRecord()->name.' created')
            ->body(implode(' ', $lines) ?: null);
    }

    private function createAdmin(): void
    {
        if (blank($this->admin['email'] ?? null)) {
            return;
        }

        $client = $this->getRecord();

        $admin = ClientUser::create([
            'client_id' => $client->id,
            'name' => $this->admin['name'],
            'email' => strtolower(trim($this->admin['email'])),
            'phone' => $this->admin['phone'] ?? null,
            'password' => $this->admin['password'],
            'role' => ClientUser::ROLE_CLIENT_ADMIN,
            'is_active' => true,
        ]);

        $this->adminSummary = 'Client admin '.$admin->email.' can sign in at '.$client->loginUrl().' with the password you set.';
    }
}
