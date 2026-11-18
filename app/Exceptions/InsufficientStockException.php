<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a sale asks for more than is left, so nothing is changed:
 * - by PlaceOrder when, at the moment the order is placed, a cart line asks for more than is left
 *   (another shopper or another window bought it first). No order is made; the cart has already
 *   been lowered to what is left (Cart::limitToStock) so the shopper can check it again;
 * - by Order::setStatus when a cancelled order is reopened but its goods have since been sold.
 */
class InsufficientStockException extends RuntimeException
{
    /** @param  array<string|int, int>  $available  Line key (cart line key, or order item id) => how many are left for that line. */
    public function __construct(public readonly array $available, string $message = 'Some items in the cart no longer have enough stock.')
    {
        parent::__construct($message);
    }
}
