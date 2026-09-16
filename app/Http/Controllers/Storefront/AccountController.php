<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AccountController extends Controller
{
    // ---- Authentication ----------------------------------------------------

    public function loginForm(): View
    {
        return view('account.login', ['seo' => Seo::simple('Sign in', null, noindex: true)]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('account.index'));
    }

    public function registerForm(): View
    {
        return view('account.register', ['seo' => Seo::simple('Create an account', null, noindex: true)]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.index')->with('status', 'Welcome to Maison Élan.');
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

    public function forgot(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        // Always respond the same way so addresses cannot be enumerated.
        return back()->with('status', 'If an account exists for that email, a reset link is on its way.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('account.reset', ['token' => $token, 'email' => $request->query('email'), 'seo' => Seo::simple('Reset password', null, noindex: true)]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
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

        return view('account.index', [
            'user' => $user,
            'orders' => $user->orders()->with('items')->limit(3)->get(),
            'address' => $user->addresses()->first(),
            'seo' => Seo::simple('My account', null, noindex: true),
        ]);
    }

    public function orders(Request $request): View
    {
        return view('account.orders', [
            'orders' => $request->user()->orders()->with('items')->paginate(10),
            'seo' => Seo::simple('My orders', null, noindex: true),
        ]);
    }

    public function order(Request $request, string $number): View
    {
        $order = $request->user()->orders()->with('items')->where('number', $number)->firstOrFail();

        return view('account.order', ['order' => $order, 'seo' => Seo::simple("Order {$order->number}", null, noindex: true)]);
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

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $this->validateAddress($request);
        $user = $request->user();
        if (! empty($data['is_default']) || $user->addresses()->count() === 0) {
            $user->addresses()->update(['is_default' => false]);
            $data['is_default'] = true;
        }
        $user->addresses()->create($data);

        return back()->with('status', 'Address saved.');
    }

    public function updateAddress(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $data = $this->validateAddress($request);
        if (! empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }
        $address->update($data);

        return back()->with('status', 'Address updated.');
    }

    public function destroyAddress(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $address->delete();

        return back()->with('status', 'Address removed.');
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:190'],
            'line2' => ['nullable', 'string', 'max:190'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:80'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    // ---- Profile -----------------------------------------------------------

    public function profile(Request $request): View
    {
        return view('account.profile', ['user' => $request->user(), 'seo' => Seo::simple('Account information', null, noindex: true)]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $user->update($data);

        return back()->with('status', 'Your details have been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Your password has been changed.');
    }
}
