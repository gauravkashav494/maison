<x-filament-panels::page>
    <div class="fi-admin-card" style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.875rem">
            <thead>
                <tr style="text-align:left">
                    @foreach(['Client', 'Store', 'Plan', 'Status'] as $heading)
                        <th style="padding:.75rem 1.25rem;white-space:nowrap">{{ $heading }}</th>
                    @endforeach
                    @foreach(['Products', 'Services', 'Orders', 'Revenue', 'Customers', 'Staff'] as $heading)
                        <th style="padding:.75rem 1.25rem;text-align:right;white-space:nowrap">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr style="border-top:1px solid rgba(127,127,127,.2)">
                        <td style="padding:.75rem 1.25rem;font-weight:600"><a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('edit', ['record' => $row['client']]) }}">{{ $row['client']->name }}</a></td>
                        <td style="padding:.75rem 1.25rem">{{ $row['client']->storefront?->name ?? '—' }}</td>
                        <td style="padding:.75rem 1.25rem">{{ $row['client']->plan?->name ?? '—' }}</td>
                        <td style="padding:.75rem 1.25rem"><span class="fi-admin-pill {{ $row['client']->is_active ? 'is-green' : 'is-red' }}">{{ $row['client']->is_active ? 'Active' : 'Suspended' }}</span></td>
                        @foreach(['products', 'services', 'orders'] as $key)
                            <td style="padding:.75rem 1.25rem;text-align:right">{{ number_format($row[$key]) }}</td>
                        @endforeach
                        <td style="padding:.75rem 1.25rem;text-align:right">{{ money($row['revenue']) }}</td>
                        <td style="padding:.75rem 1.25rem;text-align:right">{{ number_format($row['customers']) }}</td>
                        <td style="padding:.75rem 1.25rem;text-align:right">{{ number_format($row['staff']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="fi-admin-empty">No clients yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p style="font-size:.8125rem;opacity:.7">Revenue excludes cancelled orders. Individual orders, customers and products stay with each client.</p>
</x-filament-panels::page>
