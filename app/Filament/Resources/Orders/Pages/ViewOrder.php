<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\OrderStatusActions;
use App\Models\Order;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One order, laid out the way store admins expect: the order number with its status and
 * payment badges, and the next fulfilment step as a button, at the top; items, payment and the
 * timeline down the main column; customer, address, delivery and notes down the side
 * (OrderInfolist).
 *
 * The status buttons are the same OrderStatusActions the order list offers, so each hides
 * itself when the order cannot make that move.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Order '.$this->getRecord()->number;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return view('filament.orders.heading-meta', ['order' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // One solid button: the next step. Before dispatch that is Dispatch; once it is on its
            // way, Delivered. (Delivering straight away is still under More → Update status.)
            OrderStatusActions::dispatch(),
            OrderStatusActions::deliver()
                ->visible(fn (Order $record) => $record->dispatched_at !== null && $record->canTransitionTo(Order::STATUS_DELIVERED)),
            OrderStatusActions::cancel()->outlined(),
            ActionGroup::make([
                OrderStatusActions::advance(),
                EditAction::make()->label('Edit details'),
            ])->tooltip('More')->color('gray')->button()->label('More'),
        ];
    }
}
