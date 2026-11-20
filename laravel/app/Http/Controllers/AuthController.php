<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $key = trim($data['email']);

        // Username or work email; MySQL's default collation already ignores case.
        $user = User::where('email', $key)->orWhere('username', $key)->first();

        // password_verify accepts the $2a$/$2b$ bcrypt hashes written by the Node version as well as Laravel's $2y$.
        if (! $user || ! $user->is_active || ! password_verify($data['password'], $user->password_hash)) {
            return back()->withErrors(['email' => 'Email or password is incorrect.'])->onlyInput('email');
        }
        if ($user->company_id && ! \App\Models\Company::whereKey($user->company_id)->value('is_active')) {
            return back()->withErrors(['email' => "Your company's account is suspended. Contact the system administrator."])->onlyInput('email');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('LOGIN', 'User', $user->id, ($user->username ?: $user->email).' signed in', userId: $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function editPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ], [], ['new_password' => 'new password']);

        $user = $request->user();
        if (! password_verify($data['current_password'], $user->password_hash)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $forced = $user->must_change_password;
        $user->forceFill(['password_hash' => Hash::make($data['new_password']), 'must_change_password' => false])->save();
        Audit::log('UPDATE', 'User', $user->id, 'Password changed');

        // After a forced change go to the dashboard; from the settings page, stay there.
        return ($forced ? redirect()->route('dashboard') : back())->with('status', 'Password updated.');
    }
}
