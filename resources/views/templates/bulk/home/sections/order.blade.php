{{-- The bulk-order form, front and centre: the reason this store exists --}}
<section id="order" class="p-container section scroll-mt-28">
    <x-section-head :title="$g['order_heading'] ?? 'Your order'" :text="$g['order_text'] ?? null" :href="route('bulk-order')" link="Open full page" />
    @include('partials.bulk-form')
</section>
@include('partials.bulk-order-script')
