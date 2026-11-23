<?php

namespace App\Support;

use App\Clients\ClientAccess;
use App\Clients\ClientModules;
use App\Models\ClientUser;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Review;
use App\Models\ServiceRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alerts in the client panel's notification bell, for what a shopper does that the store's team
 * should look at: a new order, an order the customer cancelled, a review waiting for approval,
 * a contact message, a booking or quote request.
 *
 * Each alert goes to the client's active staff who may open that module (the Client Admin, and
 * Admin Staff whose role can view it), never to another client's team. Alerts are written
 * straight to the notifications table (no queue worker needed), and a failure is logged rather
 * than allowed to stop the checkout or form that raised it.
 */
class StaffAlerts
{
    public static function newOrder(Order $order): void
    {
        self::send($order, Notification::make()
            ->title('New order '.$order->number)
            ->body(implode(' · ', array_filter([
                $order->shipping_name,
                money($order->total),
                Order::PAYMENT_METHODS[$order->payment_method] ?? null,
                $order->isSalesOrder() ? 'Placed by sales' : null,
            ])))
            ->icon('heroicon-o-shopping-bag')
            ->iconColor('success')
            ->actions([self::open('View order', route('filament.client.resources.orders.view', ['record' => $order->getKey()], absolute: false))]));
    }

    public static function orderCancelledByCustomer(Order $order): void
    {
        $reason = Str::after((string) $order->cancellation_reason, 'Cancelled by customer');

        self::send($order, Notification::make()
            ->title('Order '.$order->number.' cancelled by the customer')
            ->body(trim(ltrim($reason, ': ')) ?: 'No reason given. Its items are back in stock.')
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->actions([self::open('View order', route('filament.client.resources.orders.view', ['record' => $order->getKey()], absolute: false))]));
    }

    /** Only a review that waits for approval: one published at once needs nobody. */
    public static function reviewSubmitted(Review $review): void
    {
        if ($review->is_approved) {
            return;
        }

        self::send($review, Notification::make()
            ->title('Review waiting for approval')
            ->body($review->rating.'★ on '.($review->product?->name ?? 'a product').' by '.$review->name)
            ->icon('heroicon-o-star')
            ->iconColor('warning')
            ->actions([self::open('Moderate reviews', route('filament.client.resources.reviews.index', absolute: false))]));
    }

    public static function contactMessage(ContactMessage $message): void
    {
        self::send($message, Notification::make()
            ->title('New message from '.$message->name)
            ->body(Str::limit((string) ($message->subject ?: $message->message), 90))
            ->icon('heroicon-o-envelope')
            ->iconColor('info')
            ->actions([self::open('Read messages', route('filament.client.resources.contact-messages.index', absolute: false))]));
    }

    public static function serviceRequest(ServiceRequest $request): void
    {
        self::send($request, Notification::make()
            ->title('New '.Str::lower($request->type_label).' '.$request->reference)
            ->body(implode(' · ', array_filter([$request->service_name, $request->name, $request->preferred_date?->format('j M')])))
            ->icon($request->type === 'emergency' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-wrench-screwdriver')
            ->iconColor($request->type === 'emergency' ? 'danger' : 'info')
            ->actions([self::open('View requests', route('filament.client.resources.service-requests.index', absolute: false))]));
    }

    /**
     * The client's active staff who may view the record's module: the people this alert is for.
     *
     * @return list<ClientUser>
     */
    public static function recipientsFor(Model $record): array
    {
        $module = ClientModules::forModel($record::class);
        if (! $module || ! ($record->client_id ?? null)) {
            return [];
        }

        return ClientUser::with(['client', 'clientRole'])
            ->where('client_id', $record->client_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (ClientUser $user) => ClientAccess::allows($user, $module, 'view'))
            ->values()
            ->all();
    }

    private static function send(Model $record, Notification $notification): void
    {
        try {
            // notifyNow: Filament's database notification is queued by default; the bell should
            // show it at once, without waiting for a queue worker.
            $message = $notification->toDatabase();
            foreach (self::recipientsFor($record) as $user) {
                $user->notifyNow($message);
            }
        } catch (Throwable $e) {
            Log::warning('Staff alert could not be saved.', ['record' => $record::class.'#'.$record->getKey(), 'error' => $e->getMessage()]);
        }
    }

    /** The alert's button: opens the record and marks the alert read. A relative link, so it works on whichever address staff use. */
    private static function open(string $label, string $url): Action
    {
        return Action::make('open')->label($label)->button()->url($url)->markAsRead();
    }
}
