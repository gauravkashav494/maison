{{--
    The saved products as the order list, sent back to account/wishlist when it asks with ?ids=…
    (AccountController::wishlist). Nothing but the list: the page puts it in place itself.
--}}
@if($products->isNotEmpty())
    <x-product-list :products="$products" :show-stock="(bool) tsetting('site.show_stock', true)" />
@endif
