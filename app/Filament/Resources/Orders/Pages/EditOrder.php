<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Exceptions\InsufficientStockException;
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

        // The status goes first: reopening a cancelled order is refused when its goods have been
        // sold since, and then nothing on the form is saved.
        if ($newStatus !== $record->status) {
            try {
                $record->setStatus($newStatus, $note ?: null);
            } catch (InsufficientStockException $e) {
                Notification::make()->danger()->title('Not enough stock')->body($e->getMessage())->persistent()->send();
                $this->halt(shouldRollbackDatabaseTransaction: true);
            }
        }
        $record->update($data);

        return $record;
    }
}
