<?php

use App\Clients\ClientDatabase;
use App\Clients\ClientProvisioner;
use App\Models\Client;
use Database\Seeders\StoreLocationsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Store locations become client content.
 *
 * They were one platform-wide list (the fashion store's boutiques) that every client's "Our
 * stores" page showed. Each existing client (themes and deleted clients included) now gets its
 * own locations, stamped with its id and template: a fashion client keeps a copy of that list,
 * any other client gets its own store's sample locations (StoreLocationsSeeder), so a grocery
 * no longer lists clothing boutiques. From now on each client edits only its own.
 *
 * Shared database: the copies live here, in `stores`, like any other client content.
 * Isolated databases: each copy is also written into the client's own database (the table is
 * created there from database/migrations/client/0001_01_01_000004_create_stores.php). The
 * copies left here are not read any more; they stay as a backup of what was moved.
 *
 * Clients with an explicit module list get "stores" added, so they can manage what they show.
 */
return new class extends Migration
{
    private const CLIENT_MIGRATION = '0001_01_01_000004_create_stores';

    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('template', 40)->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->softDeletes()->index();
        });

        $legacy = DB::table('stores')->whereNull('client_id')->orderBy('sort_order')->get();
        $clients = DB::table('clients')
            ->leftJoin('storefronts', 'storefronts.id', '=', 'clients.storefront_id')
            ->get(['clients.id', 'storefronts.template']);

        $now = now();
        foreach ($clients as $client) {
            $rows = $client->template === 'fashion' && $legacy->isNotEmpty()
                ? $legacy->map(fn (object $row) => $this->columns($row))
                : collect(StoreLocationsSeeder::for($client->template))
                    ->map(fn (array $row, int $i) => $row + ['sort_order' => $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

            foreach ($rows as $row) {
                DB::table('stores')->insert(['client_id' => $client->id, 'template' => $client->template] + $row);
            }
        }
        DB::table('stores')->whereNull('client_id')->delete();

        if (app(ClientDatabase::class)->isolated()) {
            $this->copyIntoClientDatabases();
        }

        foreach (DB::table('clients')->whereNotNull('modules')->get(['id', 'modules']) as $client) {
            $modules = json_decode($client->modules, true);
            if (is_array($modules) && ! in_array('stores', $modules, true)) {
                DB::table('clients')->where('id', $client->id)->update(['modules' => json_encode([...$modules, 'stores'])]);
            }
        }
    }

    private function copyIntoClientDatabases(): void
    {
        $databases = app(ClientDatabase::class);
        $provisioner = app(ClientProvisioner::class);
        $platform = DB::getDefaultConnection();

        foreach (Client::withThemes()->withTrashed()->get() as $client) {
            try {
                // Not built yet: "Finish setup" creates the table later, empty.
                if (! $client->templateObject() || ! $provisioner->databaseExists($client)) {
                    continue;
                }

                $connection = $databases->register($client);
                $target = DB::connection($connection);

                if (! $target->getSchemaBuilder()->hasTable('stores')) {
                    DB::setDefaultConnection($connection);
                    try {
                        (require database_path('migrations/client/'.self::CLIENT_MIGRATION.'.php'))->up();
                    } finally {
                        DB::setDefaultConnection($platform);
                    }
                }

                // Recorded as run, so the client does not show "Repair database" just for this table.
                if ($target->getSchemaBuilder()->hasTable('migrations') && ! $target->table('migrations')->where('migration', self::CLIENT_MIGRATION)->exists()) {
                    $target->table('migrations')->insert(['migration' => self::CLIENT_MIGRATION, 'batch' => (int) $target->table('migrations')->max('batch') + 1]);
                }

                if ($target->table('stores')->exists()) {
                    continue; // already copied
                }

                foreach (DB::connection($platform)->table('stores')->where('client_id', $client->id)->orderBy('sort_order')->get() as $row) {
                    $target->table('stores')->insert(['client_id' => $client->id, 'template' => $row->template] + $this->columns($row));
                }
            } catch (Throwable $e) {
                // One unreachable client must not block the deploy; its copy stays in the main database.
                Log::warning('Store locations not copied into client database', ['client' => $client->slug, 'error' => $e->getMessage()]);
            }
        }
    }

    /** A store row without its id and owner, ready to insert as a copy. */
    private function columns(object $row): array
    {
        return collect((array) $row)->except(['id', 'client_id', 'template', 'deleted_at'])->all();
    }

    /**
     * Back to one platform-wide list: the first fashion client's copy becomes the shared list
     * again. The tables inside client databases are left alone.
     */
    public function down(): void
    {
        $keep = DB::table('stores')->where('template', 'fashion')->min('client_id') ?? DB::table('stores')->min('client_id');
        DB::table('stores')->where(fn ($q) => $q->where('client_id', '!=', $keep)->orWhereNotNull('deleted_at'))->delete();
        DB::table('stores')->update(['client_id' => null]);

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['template']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['deleted_at']);
            $table->dropColumn(['template', 'client_id', 'deleted_at']);
        });
    }
};
