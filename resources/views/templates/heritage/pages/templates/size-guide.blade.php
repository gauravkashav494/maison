@extends('layouts.app')

@section('content')
{{-- Generic table-based guide (shared page type). Renders any tables defined in the admin. --}}
@php $tables = $page->data['tables'] ?? []; @endphp
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="h-container py-6">
    <div class="mx-auto max-w-4xl space-y-4" x-data="{ tab: 0 }">
        @if($page->body)<div class="card prose-h p-5 text-sm">{!! $page->body !!}</div>@endif
        @if($tables)
            <div class="no-scrollbar flex gap-2 overflow-x-auto">
                @foreach($tables as $i => $t)<button type="button" @click="tab = {{ $i }}" class="chip" :class="tab === {{ $i }} && 'chip-active'">{{ $t['name'] ?? 'Table '.($i + 1) }}</button>@endforeach
            </div>
            @foreach($tables as $i => $t)
                <div x-show="tab === {{ $i }}" class="card overflow-x-auto p-2">
                    <table class="w-full text-sm">
                        <thead><tr class="bg-cream text-left text-xs uppercase tracking-wider text-muted">@foreach($t['columns'] ?? [] as $col)<th class="px-3 py-2 font-bold">{{ $col }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-line-soft">@foreach($t['rows'] ?? [] as $row)<tr>@foreach((array) $row as $cell)<td class="px-3 py-2">{{ is_array($cell) ? implode(' ', $cell) : $cell }}</td>@endforeach</tr>@endforeach</tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>
</section>
@endsection
