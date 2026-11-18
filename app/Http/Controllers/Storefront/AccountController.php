<?php

namespace App\Http\Controllers\Storefront;

use App\Clients\ClientContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AddressRequest;
use App\Http\Requests\Storefront\ForgotPasswordRequest;
use App\Http\Requests\Storefront\LoginRequest;
use App\Http\Requests\Storefront\PasswordUpdateRequest;
use App\Http\Requests\Storefront\ProfileRequest;
use App\Http\Requests\Storefront\RegisterRequest;
use App\Http\Requests\Storefront\ResetPasswordRequest;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use App\Support\CustomerOtp;
use App\Support\PhoneNumber;
use App\Support\Seo;
use Illuminate\Auth\Events\PasswordReset;
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
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function forgotForm(): View
    {
        return view('account.forgot', ['seo' => Seo::simple('Forgot password', null, noindex: true)]);
    }

    public function forgot(ForgotPasswordRequest $request): RedirectResponse
    {
        if ($clientId = $this->clientId()) {
            // Scoped to this client: a link is only ever sent to its own customer.
            Password::broker('customers')->sendResetLink($request->only('email') + ['client_id' => $clientId, 'is_active' => true]);
        }

        // Always respond the same way so addresses cannot be enumerated.
        return back()->with('status', 'If an account exists for that email, a reset link is on its way.');
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

    public function wishlist(): View
    {
        return view('account.wishlist', ['seo' => Seo::simple('Wishlist', null, noindex: true)]);
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
