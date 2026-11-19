<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /** Append to the status timeline whenever the status changes. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Order $record */
        $note = $this->data['status_note'] ?? null;
        $newStatus = $data['status'] ?? $record->status;
        unset($data['status']);

        // The status goes first and only moves forward (Order::allowedTransitions()): going back a
        // step or reopening a delivered or cancelled order is refused, and then nothing is saved.
        if ($newStatus !== $record->status && ! $record->transitionTo($newStatus, $note ?: null)) {
            Notification::make()->danger()
                ->title('Status not changed')
                ->body('This order is '.strtolower($record->statusLabel()).' and cannot move to '.strtolower(Order::statusOptions()[$newStatus] ?? $newStatus).'. An order only moves forward.')
                ->persistent()
                ->send();
            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
        $record->update($data);

        return $record;
    }
}
