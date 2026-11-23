<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by PlaceOrder when, at the moment the order is placed, the cart's coupon can no longer be
 * used: its last use went to another shopper, or this customer has used it as often as allowed.
 * No order is made; the coupon has been taken off the cart (Cart::dropCoupon) and the message is
 * shown on the checkout page, so the shopper sees the new total before placing the order again.
 */
class CouponUnavailableException extends RuntimeException {}
