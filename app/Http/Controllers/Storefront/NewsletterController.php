<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The unsubscribe link from newsletter emails. Opening it only shows a confirm page — mail
 * scanners and link previews fetch URLs, and a bare GET must never take someone off the list —
 * and the button on that page does the unsubscribing.
 *
 * Subscribers are client-scoped on the storefront, so a token only works on the host of the
 * client it belongs to.
 */
class NewsletterController extends Controller
{
    public function show(string $token): View
    {
        $subscriber = $this->find($token);

        return view('newsletter.unsubscribe', [
            'email' => $subscriber?->email,
            'done' => $subscriber?->isUnsubscribed() ?? false,
            'token' => $token,
        ]);
    }

    public function destroy(string $token): RedirectResponse
    {
        $subscriber = $this->find($token);
        if ($subscriber && ! $subscriber->isUnsubscribed()) {
            $subscriber->forceFill(['unsubscribed_at' => now()])->save();
        }

        return redirect()->route('newsletter.unsubscribe', $token);
    }

    private function find(string $token): ?Subscriber
    {
        // Admin-removed rows still honour the link: the person asked to leave, so say they have.
        return Subscriber::withTrashed()->where('unsubscribe_token', $token)->first();
    }
}
