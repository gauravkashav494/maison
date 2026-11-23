<?php

namespace App\Support\Activity;

use App\Clients\ClientContext;
use App\Contracts\AdminIdentity;
use App\Models\Client;
use App\Models\PlatformActivity;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Writes the activity log: who did what, in one readable sentence (ActivityDescriber).
 *
 * Only people signed into a panel are logged. Storefront requests (a shopper placing an order
 * takes stock off a product) and console work (seeders, imports, scheduled jobs) are not, so
 * the log only ever answers "which of our staff did this?".
 *
 * A bulk action (Filament's BulkAction, see AppServiceProvider) runs inside group(): the lines
 * it produces are held back and written as one ("moved 12 products to the trash: …"). Logging
 * never breaks the work being logged; a failure is reported and the change goes ahead.
 */
class ActivityLog
{
    /** @var array<int, array<string, mixed>>|null lines held back during a bulk action */
    private ?array $held = null;

    /** @var array<int, array<string, mixed>> the lines of the last bulk action written out, for its toast */
    private array $lastGroup = [];

    /** @var array<int, array<string, mixed>> every line written (or held) in this request, for the toasts */
    private array $recent = [];

    public function __construct(private readonly ActivityDescriber $describe) {}

    public function describer(): ActivityDescriber
    {
        return $this->describe;
    }

    /** The signed-in panel account behind the current change, if any. */
    public function actor(): ?AdminIdentity
    {
        if (app(ClientContext::class)->isPublic()) {
            return null;
        }

        if (Filament::isServing() && Filament::getCurrentPanel()) {
            $user = Filament::auth()->user();
            if ($user instanceof AdminIdentity) {
                return $user;
            }
        }

        $user = auth('admin')->user() ?? auth('client')->user();

        return $user instanceof AdminIdentity ? $user : null;
    }

    // ---- Model events (LogsActivity) --------------------------------------------------------

    public function created(Model $model): void
    {
        $this->forModel($model, 'created', fn () => [$this->describe->created($model), null, []]);
    }

    public function deleted(Model $model): void
    {
        $permanently = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();

        $this->forModel($model, $permanently ? 'deleted permanently' : 'deleted', fn () => [$this->describe->deleted($model, $permanently), null, []]);
    }

    public function restored(Model $model): void
    {
        $this->forModel($model, 'restored', fn () => [$this->describe->restored($model), null, []]);
    }

    public function updated(Model $model): void
    {
        $fields = $this->describe->meaningfulChanges($model);
        if ($fields === []) {
            return;
        }

        $this->forModel($model, 'updated', function (AdminIdentity $actor) use ($model, $fields) {
            $self = $actor instanceof Model && $actor->is($model);
            [$sentence, $changes] = $this->describe->updated($model, $fields, $self);

            return [$sentence, $changes, $fields];
        });
    }

    // ---- Anything else ----------------------------------------------------------------------

    /**
     * A line for something that is not a plain save: a sign-in, a downloaded invoice, settings.
     *
     * @param  array<int, array<string, mixed>>  $changes
     */
    public function record(string $action, string $description, ?Model $subject = null, array $changes = [], ?string $area = null, ?AdminIdentity $actor = null, ?string $subjectName = null, ?string $subjectType = null): void
    {
        $actor ??= $this->actor();
        if (! $actor) {
            return;
        }

        $this->write([
            'action' => $action,
            'subject_type' => $subjectType ?? ($subject ? class_basename($subject) : 'Account'),
            'subject_id' => $subject?->getKey(),
            'subject' => $subjectName ?? ($subject ? $this->describe->name($subject) : ($actor->name ?? '')),
            'client_id' => $this->clientIdFor($subject, $action, $actor),
            'area' => $area ?? ($subject ? $this->describe->area($subject) : 'Account'),
            'description' => $description,
            'changes' => $changes ?: null,
            'fields' => array_column($changes, 'field') ?: null,
        ] + $this->actorColumns($actor));
    }

    // ---- Bulk actions -----------------------------------------------------------------------

    public function startGroup(): void
    {
        $this->flush();
        $this->held = [];
        $this->lastGroup = [];
    }

    /** Writes what a bulk action held back: one summary line when it all reads as one thing. */
    public function flush(): void
    {
        $held = $this->held;
        $this->held = null;
        $this->lastGroup = $held ?? $this->lastGroup;

        if (! $held) {
            return;
        }

        $kinds = array_unique(array_map(fn (array $e) => $e['action'].'|'.$e['subject_class'], $held));

        if (count($held) === 1 || count($kinds) > 1) {
            array_walk($held, fn (array $e) => $this->write($e, fresh: false));

            return;
        }

        $first = $held[0];
        $this->write([
            'subject_id' => null,
            'subject' => count($held).' '.$this->describe->pluralType($first['subject_class'], count($held)),
            'description' => $this->describe->bulk($held, $first['subject_class']),
            'changes' => array_map(fn (array $e) => ['field' => 'record', 'label' => $e['subject'], 'old' => null, 'new' => $e['description']], $held),
            'fields' => null,
            'client_id' => count(array_unique(array_column($held, 'client_id'))) === 1 ? $first['client_id'] : null,
        ] + $first, fresh: false);
    }

    // ---- For the toasts ------------------------------------------------------------------------

    /** Where the lines written from now on start, for since(). */
    public function mark(): int
    {
        return count($this->recent);
    }

    /**
     * The lines written (or held for a bulk action) after mark(); model lines carry `subject_class`
     * and the `model` itself.
     *
     * @return array<int, array<string, mixed>>
     */
    public function since(int $mark = 0): array
    {
        return array_slice($this->recent, $mark);
    }

    /**
     * The lines of the running bulk action, or of the last one once it has written them out
     * (Filament sends a bulk action's toast after its after() hook has flushed).
     *
     * @return array<int, array<string, mixed>>
     */
    public function bulkLines(): array
    {
        return $this->held ?? $this->lastGroup;
    }

    // ---- Internals --------------------------------------------------------------------------

    /** @param  callable(AdminIdentity): array{0: string, 1: ?array, 2: array}  $describe */
    private function forModel(Model $model, string $action, callable $describe): void
    {
        $actor = $this->actor();
        if (! $actor) {
            return;
        }

        try {
            [$sentence, $changes, $fields] = $describe($actor);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        $this->write([
            'action' => $action,
            'subject_type' => class_basename($model),
            'subject_class' => $model::class,
            'subject_id' => $model->getKey(),
            'subject' => $this->describe->name($model),
            'client_id' => $this->clientIdFor($model, $action, $actor),
            'area' => $this->describe->area($model),
            'description' => $sentence,
            'changes' => $changes ?: null,
            'fields' => $fields ?: null,
        ] + $this->actorColumns($actor), model: $model);
    }

    /** @param  bool  $fresh  false when a bulk action's held lines are written out (already in $recent) */
    private function write(array $entry, bool $fresh = true, ?Model $model = null): void
    {
        if ($fresh) {
            // The model too: a toast still names a record that is gone once deleted.
            $this->recent[] = $entry + ['model' => $model];
        }

        if ($this->held !== null && isset($entry['subject_class'])) {
            $this->held[] = $entry;

            return;
        }

        unset($entry['subject_class']);

        try {
            PlatformActivity::create($entry + ['ip' => request()?->ip()]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function actorColumns(AdminIdentity $actor): array
    {
        return [
            'actor' => $actor->name ?? $actor->email ?? 'Unknown',
            'actor_type' => $actor->isSuperAdmin() ? 'Super admin' : ($actor->isPlatformAccount() ? 'Platform staff' : 'Client staff'),
            'actor_model' => $actor instanceof Model ? class_basename($actor) : null,
            'actor_id' => $actor instanceof Model ? $actor->getKey() : null,
        ];
    }

    private function clientIdFor(?Model $subject, string $action, AdminIdentity $actor): ?int
    {
        if ($subject instanceof Client) {
            // A deleted client's lines must not point at a row that is gone.
            return str_starts_with($action, 'deleted') ? null : $subject->id;
        }

        if ($subject) {
            return $subject->getAttribute('client_id') ?? $actor->clientId();
        }

        // Settings and sign-ins: the client being worked in.
        return $actor->clientId() ?? app(ClientContext::class)->id();
    }
}
