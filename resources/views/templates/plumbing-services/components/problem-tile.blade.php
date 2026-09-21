{{-- Problem tile: opens the mapped service or the booking flow --}}
@props(['problem'])
@php $url = route('services.problem', str($problem['label'] ?? '')->slug()); @endphp
<a href="{{ $url }}" {{ $attributes->class('problem-tile') }}>
    <span class="svc-ico {{ ($problem['icon'] ?? '') === 'alert' ? 'svc-ico-danger' : '' }}"><x-ico :name="$problem['icon'] ?? 'help'" :size="20" /></span>
    <span class="min-w-0 flex-1 leading-tight">{{ $problem['label'] }}</span>
    <x-ico name="chevron-right" :size="16" class="hidden text-mist sm:block" />
</a>