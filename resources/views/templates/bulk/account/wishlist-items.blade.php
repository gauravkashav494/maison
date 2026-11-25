{{--
    What was saved, as the order list, sent back to account/wishlist when it asks with ?items=…
    (AccountController::wishlist): a size saved is that size's row, a product saved without a size
    is all its sizes. Nothing but the list: the page puts it in place itself.
--}}
@if($saved->isNotEmpty())
    <x-product-list :saved="$saved" :show-stock="(bool) tsetting('site.show_stock', true)" />
@endif
