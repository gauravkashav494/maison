<?php

namespace App\Http\Controllers\Storefront;

use App\Clients\ClientContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AddressRequest;
use App\Http\Requests\Storefront\ForgotPasswordRequest;
use App\Http\Requests\Storefront\LoginRequest;
use App\Http\Requests\Storefront\NewPasswordRequest;
use App\Http\Requests\Storefront\PasswordUpdateRequest;
use App\Http\Requests\Storefront\ProfileRequest;
use App\Http\Requests\Storefront\RegisterRequest;
use App\Http\Requests\Storefront\ResetPasswordRequest;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Support\CustomerAccount;
use App\Support\CustomerOtp;
use App\Support\PhoneNumber;
use App\Support\Seo;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Storefront accounts.
 *
 * A shopper belongs to the client whose storefront they are on: that client is added to
 * every credential check, every lookup and every row written, so the same email address can
 * be a customer of two clients without either account reaching the other's orders,
 * addresses or profile. The client is the one resolved for the request (host or entry link),
 * never a value from the form.
 */
class AccountController extends Controller
{
    /** Session key: the account this visitor may choose a new password for (after a reset code). */
    private const NEW_PASSWORD_KEY = 'password_reset.allowed';

    /** How long after the code the new password may be chosen. */
    private const NEW_PASSWORD_MINUTES = 10;

    /** The client this storefront belongs to. */
    private function clientId(): ?int
    {
        return app(ClientContext::class)->id();
    }

    // ---- Authentication ----------------------------------------------------

    public function loginForm(): View
    {
        return view('account.login', ['seo' => Seo::simple('Sign in', null, noindex: true)]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['phone', 'password']);
        $clientId = $this->clientId();

        // New customer records use the display form (+91 95017 32031), while older records
        // and storefront forms may use national digits. Match both saved forms of this phone.
        $phone = $credentials['phone'];
        $formattedPhone = PhoneNumber::format('IN', $phone);
        $phoneForms = array_values(array_unique(array_filter([
            $phone,
            $formattedPhone,
            $formattedPhone ? preg_replace('/\s+/', '', $formattedPhone) : null,
            '91'.$phone,
            '0'.$phone,
            substr($phone, 0, 5).' '.substr($phone, 5),
        ])));
        $credentials['phone'] = fn ($query) => $query->whereIn('phone', $phoneForms);

        // The client is part of the credentials, so an account of another client never matches.
        // Guest records (from checkout, no password) can share a phone, so only real accounts count.
        $credentials += ['client_id' => $clientId, 'is_active' => true, 'account' => fn ($query) => $query->whereNotNull('password')];
        if (! $clientId || ! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('phone'))->withErrors(['phone' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('account.index'));
    }

    public function registerForm(): View
    {
        return view('account.register', ['seo' => Seo::simple('Create an account', null, noindex: true)]);
    }

    public function register(RegisterRequest $request, CustomerOtp $otp): RedirectResponse
    {
        $clientId = $this->clientId();
        abort_unless($clientId !== null, 404);

        $data = $request->validated();

        // Someone has already ordered as a guest with this address. Their orders are only shown
        // once the address is proven to be theirs, so they set the password from an emailed link.
        $guest = filled($data['email'] ?? null) ? Customer::withTrashed()->where('client_id', $clientId)->guests()
            ->whereRaw('lower(email) = ?', [strtolower($data['email'])])->first() : null;
        if ($guest) {
            Password::broker('customers')->sendResetLink(['email' => $guest->email, 'client_id' => $clientId, 'is_active' => true]);

            return redirect()->route('login')->with('status', 'You have ordered with us before. We have emailed you a link to set your password and see your orders.');
        }

        // While codes are off the account is made at once, with the phone left unverified
        // (phone_verified_at empty). The form has already checked no account has this number.
        if (! CustomerOtp::enabled()) {
            $customer = CustomerAccount::create($clientId, $data['phone'], $data['name'], $data['email'] ?? null, $data['password'], verified: false);
            Auth::login($customer);
            $request->session()->regenerate();

            return redirect()->route('account.index')->with('status', 'Welcome to '.setting('site.name', config('app.name')).'.');
        }

        // The account is made once the phone number is proven with a WhatsApp code (OtpController).
        // The password waits with the code, encrypted, as it is still needed in plain form then.
        $error = $otp->start(CustomerOtp::REGISTER, $data['phone'], [
            'name' => $data['name'],
            'email' => filled($data['email'] ?? null) ? $data['email'] : null,
            'password' => Crypt::encryptString($data['password']),
        ]);
        if ($error) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['phone' => $error]);
        }

        return redirect()->route('otp.verify');
    }

    public function logout(Request $request): RedirectResponse
    {
        // Sign out the shopper only. The browser has one session for every guard, so invalidating
        // it would also sign out a staff account open in the admin or client panel in another tab.
        Auth::guard('web')->logout();
        $request->session()->forget(['last_order_number', 'booking', 'customer_otp']);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function forgotForm(): View
    {
        return view('account.forgot', ['seo' => Seo::simple('Forgot password', null, noindex: true)]);
    }

    /**
     * Forgot password: a WhatsApp code to the phone number on the account. Once it is entered
     * (OtpController::completeReset) the shopper chooses a new password here. While codes are
     * off, a reset link is emailed to the account's address instead (or the shopper is told to
     * contact the store when it has none).
     */
    public function forgot(ForgotPasswordRequest $request, CustomerOtp $otp): RedirectResponse
    {
        $clientId = $this->clientId();
        abort_unless($clientId !== null, 404);

        // Same answer as "Sign in with WhatsApp", which already tells whether a number has an account.
        $phone = $request->validated('phone');
        $customer = Customer::where('client_id', $clientId)->where('phone', $phone)->accounts()->orderBy('id')->first();
        if (! $customer || ! $customer->is_active) {
            return back()->withInput()->withErrors(['phone' => 'We could not find an account with this number. Create one instead.']);
        }

        // While codes are off, the reset link goes to the account's email instead.
        if (! CustomerOtp::enabled()) {
            if (blank($customer->email)) {
                return back()->withInput()->withErrors(['phone' => 'This account has no email address to send a reset link to. Please contact the store to reset your password.']);
            }
            Password::broker('customers')->sendResetLink(['email' => $customer->email, 'client_id' => $clientId, 'is_active' => true]);

            return back()->with('status', 'We have emailed a link to reset your password to '.self::maskEmail($customer->email).'.');
        }

        if ($error = $otp->start(CustomerOtp::RESET, $phone, ['customer_id' => $customer->id])) {
            return back()->withInput()->withErrors(['phone' => $error]);
        }

        return redirect()->route('otp.verify');
    }

    /** "asha@example.com" → "a•••@example.com", to say where a reset link went without giving the address away. */
    private static function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).'•••@'.$domain;
    }

    /** After a correct reset code: this session may set a new password for the account, briefly. */
    public static function allowNewPassword(Customer $customer): void
    {
        session([self::NEW_PASSWORD_KEY => [
            'customer_id' => $customer->id,
            'client_id' => $customer->client_id,
            'expires_at' => now()->addMinutes(self::NEW_PASSWORD_MINUTES)->getTimestamp(),
        ]]);
    }

    /** The account this session proved its number for, while the permission lasts. */
    private function newPasswordAccount(): ?Customer
    {
        $grant = session(self::NEW_PASSWORD_KEY);
        if (! is_array($grant) || $this->newPasswordSecondsLeft() <= 0
            || (int) ($grant['client_id'] ?? 0) !== (int) $this->clientId()) {
            return null;
        }

        $customer = Customer::where('client_id', $grant['client_id'])->find($grant['customer_id'] ?? 0);

        return $customer?->is_active ? $customer : null;
    }

    private function newPasswordSecondsLeft(): int
    {
        return max(0, (int) (session(self::NEW_PASSWORD_KEY)['expires_at'] ?? 0) - now()->getTimestamp());
    }

    /** Back to "Forgot password", saying whether the time ran out or the number was never proven. */
    private function verifyAgain(): RedirectResponse
    {
        $message = session()->has(self::NEW_PASSWORD_KEY)
            ? 'The '.self::NEW_PASSWORD_MINUTES.' minutes to choose a new password have passed. Please verify your number again.'
            : 'Please verify your number to reset your password.';
        session()->forget(self::NEW_PASSWORD_KEY);

        return redirect()->route('password.request')->withErrors(['phone' => $message]);
    }

    public function newPasswordForm(): View|RedirectResponse
    {
        if (! $this->newPasswordAccount()) {
            return $this->verifyAgain();
        }

        return view('account.new-password', [
            // A live countdown on the page, so the shopper knows the time limit before typing.
            'secondsLeft' => $this->newPasswordSecondsLeft(),
            'seo' => Seo::simple('Choose a new password', null, noindex: true),
        ]);
    }

    public function saveNewPassword(NewPasswordRequest $request): RedirectResponse
    {
        $customer = $this->newPasswordAccount();
        if (! $customer) {
            return $this->verifyAgain();
        }

        $customer->forceFill(['password' => $request->validated('password'), 'remember_token' => Str::random(60)])->save();
        event(new PasswordReset($customer));
        $request->session()->forget(self::NEW_PASSWORD_KEY);

        // The number was just proven, so they are signed straight in.
        Auth::login($customer);
        $request->session()->regenerate();

        return redirect()->route('account.index')->with('status', 'Your password has been changed.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('account.reset', ['token' => $token, 'email' => $request->query('email'), 'seo' => Seo::simple('Reset password', null, noindex: true)]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $clientId = $this->clientId();
        abort_unless($clientId !== null, 404);

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token') + ['client_id' => $clientId],
            function (Customer $customer, string $password) {
                $customer->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($customer));
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password has been reset. Please sign in.')
            : back()->withErrors(['email' => __($status)]);
    }

    // ---- Dashboard ---------------------------------------------------------

    public function index(Request $request): View
    {
        $user = $request->user();
        $orders = $this->orderHistory($user);

        return view('account.index', [
            'user' => $user,
            'orders' => (clone $orders)->with('items')->latest()->limit(3)->get(),
            'ordersCount' => (clone $orders)->count(),
            'address' => $user->addresses()->first(),
            'seo' => Seo::simple('My account', null, noindex: true),
        ]);
    }

    public function orders(Request $request): View
    {
        return view('account.orders', [
            'orders' => $this->orderHistory($request->user())->with('items')->latest()->paginate(10),
            'user' => $request->user(),
            'seo' => Seo::simple('My orders', null, noindex: true),
        ]);
    }

    public function order(Request $request, string $number): View
    {
        $user = $request->user();
        $order = $this->orderHistory($user)->with('items')->where('number', $number)->firstOrFail();

        return view('account.order', ['order' => $order, 'user' => $user, 'seo' => Seo::simple("Order {$order->number}", null, noindex: true)]);
    }

    /** The signed-in customer’s purchases plus orders they placed for another customer. */
    private function orderHistory(Customer $customer)
    {
        return Order::query()->where(fn ($query) => $query
            ->where('customer_id', $customer->id)
            ->orWhere('salesperson_id', $customer->id));
    }

    /**
     * The wishlist page. The list itself lives on the device (localStorage), so a template that
     * draws saved items on the server (Bulk: the order list) asks again with ?ids=… and gets just
     * the list back. Only a template with an `account.wishlist-items` view answers that.
     */
    public function wishlist(Request $request): View
    {
        if ($request->has('ids') && view()->exists('account.wishlist-items')) {
            $ids = collect(explode(',', (string) $request->query('ids')))
                ->map(fn ($id) => (int) $id)->filter()->unique()->take(100)->values();
            $products = $ids->isEmpty() ? collect() : Product::with('category')->active()->whereIn('id', $ids)->get()
                ->sortBy(fn (Product $p) => $ids->search($p->id))->values();

            return view('account.wishlist-items', ['products' => $products]);
        }

        return view('account.wishlist', ['seo' => Seo::simple('Wishlist', null, noindex: true)]);
    }

    /**
     * Saves a signed-in buyer's Saved items to the account: the whole list, newest first, as the
     * storefront's wishlist store sends it after every change. Only this store's products are kept.
     */
    public function saveWishlist(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['present', 'array', 'max:200'], 'ids.*' => ['integer', 'min:1']]);
        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $known = $ids->isEmpty() ? collect() : Product::whereIn('id', $ids)->pluck('id')->flip();
        $ids = $ids->filter(fn ($id) => $known->has($id))->values()->all();

        $customer = $request->user();
        if ($customer->wishlist !== $ids) {
            $customer->forceFill(['wishlist' => $ids])->save();
        }

        return response()->json(['ids' => $ids]);
    }

    // ---- Addresses ---------------------------------------------------------

    public function addresses(Request $request): View
    {
        return view('account.addresses', ['addresses' => $request->user()->addresses, 'seo' => Seo::simple('Saved addresses', null, noindex: true)]);
    }

    public function storeAddress(AddressRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        if (! empty($data['is_default']) || $user->addresses()->count() === 0) {
            $user->addresses()->update(['is_default' => false]);
            $data['is_default'] = true;
        }
        $user->addresses()->create($data);

        return back()->with('status', 'Address saved.');
    }

    public function updateAddress(AddressRequest $request, Address $address): RedirectResponse
    {
        abort_unless((int) $address->customer_id === (int) $request->user()->id, 403);
        $data = $request->validated();
        if (! empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }
        $address->update($data);

        return back()->with('status', 'Address updated.');
    }

    public function destroyAddress(Request $request, Address $address): RedirectResponse
    {
        abort_unless((int) $address->customer_id === (int) $request->user()->id, 403);
        $address->delete();

        return back()->with('status', 'Address removed.');
    }

    // ---- Profile -----------------------------------------------------------

    public function profile(Request $request): View
    {
        return view('account.profile', ['user' => $request->user(), 'seo' => Seo::simple('Account information', null, noindex: true)]);
    }

    public function updateProfile(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $user->update($data);

        return back()->with('status', 'Your details have been updated.');
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Plain on purpose: the model's hashed cast hashes it and StoresPasswordHint keeps the readable copy.
        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Your password has been changed.');
    }
}
