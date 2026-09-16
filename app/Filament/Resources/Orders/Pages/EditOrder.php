<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
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

        $record->update($data);
        if ($newStatus !== $record->status) {
            $record->setStatus($newStatus, $note ?: null);
        }

        return $record;
    }
}
