<?php

namespace App\Http\Controllers\Storefront;

use App\Clients\ClientContext;
use App\Exceptions\CouponUnavailableException;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\OtpCodeRequest;
use App\Http\Requests\Storefront\OtpLoginRequest;
use App\Models\Customer;
use App\Services\Cart;
use App\Services\PlaceOrder;
use App\Support\CustomerOtp;
use App\Support\Seo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * The WhatsApp code step shared by three flows:
 *
 *  - register: the sign-up form was valid; the account is made once the phone is proven.
 *  - checkout: a shopper without an account placed an order; once the phone is proven they get
 *    an account from the checkout details (or the existing account for that number), are
 *    signed in, and the order is placed.
 *  - login:    sign in with a code instead of a password.
 *  - reset:    forgot password; once the phone is proven the shopper chooses a new password.
 *
 * What each flow needs is kept with the pending code (CustomerOtp), never taken from this form.
 */
class OtpController extends Controller
{
    public function __construct(private readonly CustomerOtp $otp) {}

    private function clientId(): ?int
    {
        return app(ClientContext::class)->id();
    }

    // ---- Sign in with a code -------------------------------------------------

    public function loginForm(): View
    {
        return view('account.login-otp', ['seo' => Seo::simple('Sign in with WhatsApp', null, noindex: true)]);
    }

    public function sendLogin(OtpLoginRequest $request): RedirectResponse
    {
        $clientId = $this->clientId();
        abort_unless($clientId !== null, 404);

        $phone = $request->validated('phone');
        $customer = $this->accountFor($clientId, $phone);
        if (! $customer || ! $customer->is_active) {
            return back()->withInput()->withErrors(['phone' => 'We could not find an account with this number. Create one instead.']);
        }

        if ($error = $this->otp->start(CustomerOtp::LOGIN, $phone, ['customer_id' => $customer->id])) {
            return back()->withInput()->withErrors(['phone' => $error]);
        }

        return redirect()->route('otp.verify');
    }

    // ---- Enter the code ------------------------------------------------------

    public function form(): View|RedirectResponse
    {
        $pending = $this->otp->pending();
        if (! $pending) {
            return redirect()->route('login')->withErrors(['phone' => 'Your code has expired. Please start again.']);
        }

        [$heading, $intro, $button, $back] = match ($pending['purpose']) {
            CustomerOtp::REGISTER => ['Verify your number', 'to finish creating your account.', 'Verify & create account', route('register')],
            CustomerOtp::CHECKOUT => ['Confirm your order', 'to confirm your number. We will create your account and place your order.', 'Verify & place order', route('checkout')],
            CustomerOtp::RESET => ['Reset your password', 'to choose a new password.', 'Verify & continue', route('password.request')],
            default => ['Enter your code', 'to sign in.', 'Verify & sign in', route('login.otp')],
        };

        return view('account.verify-otp', [
            'heading' => $heading,
            'intro' => 'We sent a '.config('services.otp.length', 6).'-digit code on WhatsApp to '.CustomerOtp::mask($pending['phone']).'. Enter it '.$intro,
            'button' => $button,
            'backUrl' => $back,
            'resendIn' => $this->otp->resendIn(),
            // Live countdown to when this code stops working.
            'expiresIn' => max(0, (int) $pending['expires_at'] - now()->getTimestamp()),
            'codeLength' => (int) config('services.otp.length', 6),
            'seo' => Seo::simple($heading, null, noindex: true),
        ]);
    }

    public function resend(): RedirectResponse
    {
        if ($error = $this->otp->resend()) {
            return $this->otp->pending()
                ? back()->withErrors(['code' => $error])
                : redirect()->route('login')->withErrors(['phone' => $error]);
        }

        return back()->with('status', 'A new code is on its way on WhatsApp.');
    }

    public function verify(OtpCodeRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $clientId = $this->clientId();
        abort_unless($clientId !== null, 404);

        $result = $this->otp->verify($request->validated('code'));
        if (is_string($result)) {
            return $this->otp->pending()
                ? back()->withErrors(['code' => $result])
                : redirect()->route('login')->withErrors(['phone' => $result]);
        }

        return match ($result['purpose']) {
            CustomerOtp::REGISTER => $this->completeRegistration($request, $clientId, $result['phone'], $result['payload']),
            CustomerOtp::CHECKOUT => $this->completeCheckout($request, $placeOrder, $clientId, $result['phone'], $result['payload']),
            CustomerOtp::RESET => $this->completeReset($clientId, $result['phone'], $result['payload']),
            default => $this->completeLogin($request, $clientId, $result['phone'], $result['payload']),
        };
    }

    // ---- What a proven number unlocks -----------------------------------------

    private function completeRegistration(Request $request, int $clientId, string $phone, array $data): RedirectResponse
    {
        // Someone may have made an account with this number while the code was on its way.
        if ($this->accountFor($clientId, $phone)) {
            return redirect()->route('login')->withErrors(['phone' => 'An account with this phone number already exists. Try signing in instead.']);
        }

        $customer = $this->createAccount($clientId, $phone, $data['name'], $data['email'] ?? null, Crypt::decryptString($data['password']));
        $this->signIn($request, $customer);

        return redirect()->route('account.index')->with('status', 'Welcome to '.setting('site.name', config('app.name')).'.');
    }

    private function completeCheckout(Request $request, PlaceOrder $placeOrder, int $clientId, string $phone, array $data): RedirectResponse
    {
        // The number is proven, so it is this shopper's account when there is one; otherwise a
        // new one is made from what they typed at checkout. They sign in with a code later.
        $customer = $this->accountFor($clientId, $phone);
        if ($customer && ! $customer->is_active) {
            return redirect()->route('checkout')->withErrors(['phone' => 'The account for this number is disabled. Please contact the store.']);
        }
        $customer ??= $this->createAccount($clientId, $phone, $data['shipping_name'], $data['email'] ?? null);
        $this->signIn($request, $customer);

        if (empty(app(Cart::class)->toArray()['items'])) {
            return redirect()->route('cart')->with('status', 'You are signed in, but your bag is empty.');
        }

        // A new account (or one without an address yet) keeps this address for next time.
        try {
            $order = $placeOrder($data, $customer, saveAddress: $customer->addresses()->doesntExist());
        } catch (InsufficientStockException|CouponUnavailableException) {
            // Sold out (or the coupon used up) while the code was on its way: back to checkout,
            // signed in, with the cart updated and a note saying what changed.
            return redirect()->route('checkout')->withInput($data);
        }
        session(['last_order_number' => $order->number]);

        return redirect()->route('orders.confirmation', $order->number);
    }

    private function completeLogin(Request $request, int $clientId, string $phone, array $data): RedirectResponse
    {
        $customer = $this->accountFor($clientId, $phone);
        if (! $customer || ! $customer->is_active || $customer->id !== ($data['customer_id'] ?? null)) {
            return redirect()->route('login.otp')->withErrors(['phone' => 'We could not sign you in. Please try again.']);
        }
        $this->signIn($request, $customer);

        return redirect()->intended(route('account.index'));
    }

    /** The number is proven: this visitor may now choose a new password for that account. */
    private function completeReset(int $clientId, string $phone, array $data): RedirectResponse
    {
        $customer = $this->accountFor($clientId, $phone);
        if (! $customer || ! $customer->is_active || $customer->id !== ($data['customer_id'] ?? null)) {
            return redirect()->route('password.request')->withErrors(['phone' => 'We could not reset your password. Please try again.']);
        }
        AccountController::allowNewPassword($customer);

        return redirect()->route('password.new');
    }

    // ---- Helpers ---------------------------------------------------------------

    /** This client's account for the number (a password or a verified phone), if any. */
    private function accountFor(int $clientId, string $phone): ?Customer
    {
        return Customer::where('client_id', $clientId)->where('phone', $phone)->accounts()->orderBy('id')->first();
    }

    /**
     * A new account with a verified phone. The email is kept only when no other customer of
     * this client has it (a guest record keeps its address, claimed through the emailed link).
     */
    private function createAccount(int $clientId, string $phone, string $name, ?string $email, ?string $password = null): Customer
    {
        $email = filled($email) ? strtolower(trim($email)) : null;
        if ($email && Customer::withTrashed()->where('client_id', $clientId)->whereRaw('lower(email) = ?', [$email])->exists()) {
            $email = null;
        }

        $attributes = ['client_id' => $clientId, 'name' => trim($name), 'phone' => $phone, 'is_active' => true];
        try {
            $customer = Customer::create($attributes + ['email' => $email, 'password' => $password]);
        } catch (UniqueConstraintViolationException) {
            // The address was taken a moment ago: the account is still made, without it.
            $customer = Customer::create($attributes + ['email' => null, 'password' => $password]);
        }
        $customer->forceFill(['phone_verified_at' => now()])->save();

        return $customer;
    }

    private function signIn(Request $request, Customer $customer): void
    {
        if (! $customer->phone_verified_at) {
            $customer->forceFill(['phone_verified_at' => now()])->save();
        }
        Auth::login($customer);
        $request->session()->regenerate();
    }
}
