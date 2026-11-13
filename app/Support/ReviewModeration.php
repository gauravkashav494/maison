<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;

/**
 * Decides what happens to a review a shopper submits on the storefront.
 *
 *  - A verified purchase is a signed-in customer with a delivered order containing the product.
 *    It is published straight away when the store allows it (Site settings → Reviews, on by
 *    default) and nothing in it looks like spam.
 *  - Anything that could be spam or an attempt to take the conversation elsewhere (a link, an
 *    email address or a phone number) is held for approval, even from a verified buyer.
 *  - Everything else waits for approval, with the reason recorded so the team sees why.
 *
 * Holding never depends on the rating: a 1★ review from a verified buyer is published exactly
 * like a 5★ one (holding back negative reviews is not allowed under consumer-review rules).
 */
class ReviewModeration
{
    public const HOLD_NOT_VERIFIED = 'Not a verified purchase';

    public const HOLD_GUEST = 'Posted without signing in';

    public const HOLD_LINK = 'Contains a link';

    public const HOLD_EMAIL = 'Contains an email address';

    public const HOLD_PHONE = 'Contains a phone number';

    /**
     * @param  array{name?:string, title?:?string, body?:string}  $data
     * @return array{is_verified_purchase:bool, is_approved:bool, held_reason:?string}
     */
    public function assess(Product $product, ?Customer $customer, array $data): array
    {
        $verified = $customer !== null && $this->hasReceived($customer, $product);
        $flag = $this->flag($data);
        $autoPublish = self::autoPublishesVerified();

        $approved = $autoPublish && $verified && $flag === null;

        return [
            'is_verified_purchase' => $verified,
            'is_approved' => $approved,
            // With auto-publishing off every review waits, so there is no particular reason to give.
            'held_reason' => match (true) {
                $approved => null,
                $flag !== null => $flag,
                ! $autoPublish => null,
                $customer === null => self::HOLD_GUEST,
                default => self::HOLD_NOT_VERIFIED,
            },
        ];
    }

    /** The store's setting; on unless the store turned it off. */
    public static function autoPublishesVerified(): bool
    {
        return (bool) Setting::get('reviews.auto_publish_verified', true);
    }

    /** Has this customer had this product delivered to them? */
    public function hasReceived(Customer $customer, Product $product): bool
    {
        return Order::query()
            ->where('customer_id', $customer->getKey())
            ->where('status', Order::STATUS_DELIVERED)
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->getKey()))
            ->exists();
    }

    /**
     * The first thing in the reviewer's own words that needs a person to look at it, or null.
     * The reviewer's contact email field is not checked: only what would be shown publicly.
     *
     * @param  array{name?:string, title?:?string, body?:string}  $data
     */
    public function flag(array $data): ?string
    {
        $text = implode("\n", array_filter([$data['name'] ?? null, $data['title'] ?? null, $data['body'] ?? null]));

        return match (true) {
            (bool) preg_match('/[a-z0-9._%+-]+@[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}/i', $text) => self::HOLD_EMAIL,
            (bool) preg_match('~\b(?:https?://|www\.)\S+|\b[a-z0-9-]{2,}\.(?:com|in|net|org|co|io|info|biz|xyz|shop|store|online|site|link|ly|me|app)\b(?:/\S*)?~i', $text) => self::HOLD_LINK,
            // Ten or more digits, allowing spaces, dashes, dots, brackets and a leading +.
            (bool) preg_match('/\+?\d(?:[\s\-.()]*\d){9,}/', $text) => self::HOLD_PHONE,
            default => null,
        };
    }
}
