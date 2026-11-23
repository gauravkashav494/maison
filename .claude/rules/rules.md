# Project rules

Rules Claude must follow when working on this project. Each rule is its own `##` section;
add new rules as new sections.

---

## Activity log: every staff action gets a line

Anything a signed-in panel account (client staff or a platform super admin) does must show up
in the activity log as one readable sentence. That covers new features too: a new model, screen,
action, page or download is not finished until it writes its activity line. Don't wait to be
asked.

### How lines get written

- **Model saves are automatic.** `App\Models\Concerns\LogsActivity` logs created / updated /
  deleted / restored / permanently deleted. Every client content model gets it through
  `BelongsToClient`. Account and platform models `use LogsActivity` directly.
  - New model that staff can change → make sure it has `BelongsToClient` or `LogsActivity`.
- **Everything that is not a plain save is logged by hand** with
  `app(App\Support\Activity\ActivityLog::class)->record($action, $description, $subject, ...)`.
  That covers downloads, opening or printing a document, sending an email or WhatsApp message,
  exports and imports, settings pages, sign-in events and custom Filament actions.
  Existing examples:
  - `app/Filament/Resources/Orders/OrderStatusActions.php` (downloaded invoice)
  - `app/Http/Controllers/Admin/OrderInvoiceController.php` (opened invoice)
  - `app/Filament/Pages/Concerns/HasSettingsForm.php` (settings saved)
  - `app/Support/Activity/SignInActivity.php` (sign-in / sign-out / failed)
- **Bulk actions** are grouped into one line by the `BulkAction` hook in `AppServiceProvider`.
  Don't log each record of a bulk action yourself.

### Writes that skip the log (watch for these)

Model events don't fire for any of these, so either use a normal model save or call `record()`:

- `Model::query()->update(...)`, `->delete()` on a query builder, `DB::table(...)`
- `saveQuietly()`, `withoutEvents()`, `updateQuietly()`
- `increment()` / `decrement()` on a query, raw SQL, `upsert()`, `insert()`
- Pivot changes such as `attach()` / `detach()` / `sync()`. Log what changed with `record()`.

### Words, so the line reads well

- `$action`: reuse a key from `App\Filament\Support\ActivityTable::ACTIONS` (`created`, `updated`,
  `deleted`, `restored`, `deleted permanently`, `downloaded`, `viewed`, `signed in`, ...). For a
  genuinely new kind of event, add it to `ACTIONS` with its label, and give it a colour in
  `actionColor()`.
- `$description` is the sentence after the person's name, lower-case and past tense:
  `'downloaded the invoice of '.$log->describer()->reference($order)` reads as
  "Priya Sharma downloaded the invoice of order #1043".
- New model: if the class name doesn't read well, add a friendly name to
  `ActivityDescriber::TYPES` ("Coupon" → "promo code") and labels for unclear columns to
  `ActivityDescriber::LABELS`. If its menu section isn't found by
  `ActivityDescriber::area()`, add it there.
- Status columns read their words from the model's `STATUSES` constant when it has one.
- Secrets (anything matching `password|token|secret|otp|api_key|hint`) are named, never stored.
  New secret-like columns must match that pattern or be added to it.

### What is deliberately not logged

Storefront shoppers, guests, seeders, console commands and queued jobs with no signed-in panel
account are not staff actions, and `ActivityLog::actor()` returns null for them. Don't force
lines for these.

### Done means tested

Each new logged event gets a test in `tests/Feature/ActivityLogTest.php` that checks the
`PlatformActivity` row: action, sentence, and that it only shows for the right client.
