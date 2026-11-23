<?php

namespace App\Filament\Support\Pages;

use App\Filament\Support\RecordToast;
use App\Support\Activity\ActivityLog;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord as BaseEditRecord;

/**
 * Every Edit page extends this, so the "updated" toast names the record and what changed
 * ('Product "Linen Shirt" updated' / 'Changed: price and stock.'), or says nothing changed.
 */
abstract class EditRecord extends BaseEditRecord
{
    /** Where this save's activity lines start (ActivityLog::mark()). */
    private int $activityMark = 0;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->activityMark = app(ActivityLog::class)->mark();

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function getSavedNotification(): ?Notification
    {
        return RecordToast::saved($this->getRecord(), app(ActivityLog::class)->since($this->activityMark));
    }
}
