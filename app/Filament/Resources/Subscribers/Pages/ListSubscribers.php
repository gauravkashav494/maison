<?php

namespace App\Filament\Resources\Subscribers\Pages;

use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Models\Subscriber;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListSubscribers extends ListRecords
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->tooltip('Downloads the rows the current search and filters show, with each person’s unsubscribe link.')
                ->action(fn () => $this->exportCsv()),
            CreateAction::make(),
        ];
    }

    /**
     * Streams what the table shows (search, filters and sort applied) as a CSV that imports into
     * any email tool. Filter Status → Subscribed first to leave out people who opted out.
     */
    protected function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery();
        $name = 'subscribers-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM, so Excel reads non-ASCII emails correctly
            fputcsv($out, ['Email', 'Source', 'Status', 'Subscribed at', 'Unsubscribed at', 'Unsubscribe link']);

            $storeUrls = [];
            foreach ($query->cursor() as $s) {
                /** @var Subscriber $s */
                $storeUrl = $storeUrls[$s->client_id ?? 0] ??= Subscriber::storeUrlFor($s->client_id);
                fputcsv($out, array_map(self::safeCell(...), [
                    $s->email,
                    $s->source,
                    $s->isUnsubscribed() ? 'Unsubscribed' : 'Subscribed',
                    $s->created_at?->format('Y-m-d H:i'),
                    $s->unsubscribed_at?->format('Y-m-d H:i'),
                    $s->unsubscribeUrl($storeUrl),
                ]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Spreadsheets run a cell starting with = + - @ as a formula; a source typed by a visitor must not. */
    private static function safeCell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
