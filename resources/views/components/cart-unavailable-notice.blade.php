@props(['message' => null, 'inDrawer' => false, 'margin' => '0 0 1.25rem'])
{{--
    Top of the cart drawer, cart page and checkout page:

    1. Items that can't be ordered: no longer sold (product hidden or deleted, chosen variant
       switched off) or sold out. They stay in the cart, greyed with "No longer available" or
       "Out of stock" in red and out of the totals, until the shopper removes them; checkout will not place the order while any are left. Read from the
       cart store (`unavailable`), so it follows every change.
    2. A box with stock changes from checkout ("Only 2 of X are left…"), which removed or lowered
       lines. Stays until closed. Filled from the page ($message, said once by the server) and by
       the cart store (`removed_notice`); with this box on the page the store shows those here
       instead of in a toast.

    In the cart drawer (inDrawer) the box only repeats what the toast said: the drawer is on every
    page but mostly closed, so it must not stop the toast. $margin fits it to each drawer's padding.
    Inline styles so it fits every template.
--}}
<div x-data
     x-init="@unless($inDrawer) $store.cart.noticeBox = true; @endunless @if($message) $store.cart.removed_notice = @js($message); @endif"
     x-show="$store.cart.removed_notice"
     @if(! $message) x-cloak @endif
     @unless($inDrawer) role="alert" @endunless
     style="display:flex;align-items:flex-start;gap:.75rem;margin:{{ $margin }};padding:.875rem 1rem;border:1px solid #f0c36d;border-left-width:4px;border-radius:.5rem;background:#fff8e6;color:#5c3d00;font-size:.9rem;line-height:1.45">
    <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" style="width:1.25rem;height:1.25rem;flex:none;margin-top:.05rem;color:#b7791f"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
    <p style="margin:0;flex:1" x-text="$store.cart.removed_notice">{{ $message }}</p>
    <button type="button" @click="$store.cart.removed_notice = null" aria-label="Close message"
            style="flex:none;background:none;border:0;padding:0 .25rem;font-size:1.25rem;line-height:1;color:inherit;cursor:pointer">&times;</button>
</div>

@php($atCheckout = request()->routeIs('checkout'))
<div x-data x-show="$store.cart.unavailable?.length" x-cloak
     style="margin:{{ $margin }};border:1px solid #f4c7c3;border-radius:.5rem;overflow:hidden;background:#fff;font-size:.875rem;line-height:1.4">
    <p style="margin:0;padding:.7rem 1rem;background:#fef3f2;color:#b42318;font-weight:600"
       x-text="$store.cart.unavailable?.length === 1 ? {{ Js::from($atCheckout ? 'This item can’t be ordered right now. Remove it to place your order.' : 'This item can’t be ordered right now. Remove it to check out.') }} : {{ Js::from($atCheckout ? 'These items can’t be ordered right now. Remove them to place your order.' : 'These items can’t be ordered right now. Remove them to check out.') }}"></p>
    <template x-for="line in ($store.cart.unavailable || [])" :key="line.key">
        <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border-top:1px solid #f4c7c3" aria-disabled="true">
            <img x-show="line.image" :src="line.image" alt="" style="width:3rem;height:3rem;flex:none;object-fit:cover;border-radius:.375rem;filter:grayscale(1);opacity:.5">
            <div style="flex:1;min-width:0">
                <p style="margin:0;color:#6b7280;font-weight:600;text-decoration:line-through;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="line.name"></p>
                <p x-show="line.options_label" style="margin:.1rem 0 0;color:#9ca3af;font-size:.8rem" x-text="line.options_label"></p>
                <p style="margin:.2rem 0 0;color:#b42318;font-size:.8rem;font-weight:600"><span x-text="line.reason_label">No longer available</span> <span style="font-weight:400" x-text="'· Qty ' + line.qty"></span></p>
            </div>
            <button type="button" @click="$store.cart.remove(line.key)" :aria-label="'Remove ' + line.name"
                    style="flex:none;background:none;border:0;padding:.25rem;color:#b42318;font-weight:600;font-size:.8rem;text-decoration:underline;cursor:pointer">Remove</button>
        </div>
    </template>
</div>
