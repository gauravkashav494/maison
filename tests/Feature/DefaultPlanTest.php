<?php

namespace Tests\Feature;

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Exactly one plan is the default for new clients. */
class DefaultPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The migrations seed a Starter plan; these tests build their own.
        DB::table('plans')->delete();
    }

    private function plan(string $slug, bool $default = false, int $position = 0): Plan
    {
        return Plan::create(['name' => ucfirst($slug), 'slug' => $slug, 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => $default, 'position' => $position]);
    }

    private function defaults(): array
    {
        return Plan::withTrashed()->where('is_default', true)->pluck('slug')->all();
    }

    public function test_making_a_plan_the_default_takes_it_from_the_others(): void
    {
        $this->plan('starter', true);
        $pro = $this->plan('pro');

        $pro->update(['is_default' => true]);
        $this->assertSame(['pro'], $this->defaults());

        $this->plan('business', true);
        $this->assertSame(['business'], $this->defaults());
        $this->assertSame('business', Plan::default()->slug);
    }

    public function test_a_deleted_default_hands_over_to_the_first_remaining_plan(): void
    {
        $starter = $this->plan('starter', true);
        $this->plan('pro', position: 2);
        $this->plan('basic', position: 1);

        $starter->delete();
        $this->assertSame(['basic'], $this->defaults());

        // Restoring the old default does not bring back a second one.
        $starter->restore();
        $this->assertSame(['basic'], $this->defaults());
    }

    public function test_a_permanently_deleted_default_stays_deleted(): void
    {
        $starter = $this->plan('starter', true);
        $this->plan('pro');

        $starter->forceDelete();

        $this->assertNull(Plan::withTrashed()->find($starter->id));
        $this->assertSame(['pro'], $this->defaults());
    }

    public function test_the_form_moves_the_default_and_cannot_switch_it_off(): void
    {
        $starter = $this->plan('starter', true);
        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditPlan::class, ['record' => $starter->getRouteKey()])
            ->assertFormFieldIsDisabled('is_default');

        Livewire::test(CreatePlan::class)
            ->fillForm(['name' => 'Pro', 'slug' => 'pro', 'price_label' => '₹Y', 'is_default' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['pro'], $this->defaults());
    }
}
