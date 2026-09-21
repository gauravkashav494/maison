{{-- Horizontal category item (mobile header rail, services page) --}}
@props(['service'])
<a href="{{ $service->url }}" {{ $attributes->class(['cat-item', 'cat-item-danger' => $service->is_emergency]) }}>
    <span class="svc-ico"><x-ico :name="$service->icon ?: 'wrench'" :size="24" :stroke="1.7" /></span>
    <span class="line-clamp-2">{{ Str::contains($service->name, ' Plumbing') ? Str::before($service->name, ' Plumbing') : $service->name }}</span>
</a>