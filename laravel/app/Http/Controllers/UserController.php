<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Sign-in accounts, super administrators only. Ported from the /settings/users routes and the Users page. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));

        return view('users.index', [
            'users' => User::with('employee')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
                ->orderByDesc('is_active')->orderBy('last_name')->orderBy('first_name')
                ->paginate(25)->withQueryString(),
            'employees' => Employee::whereNotIn('status', ['INACTIVE', 'RESIGNED', 'RETIRED', 'TERMINATED'])->orderBy('first_name')->get()
                ->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all(),
            'linked' => User::whereNotNull('employee_id')->pluck('employee_id', 'id')->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null) + $request->validate(
            ['password' => ['required', 'string', 'min:12']],
            ['password.min' => 'Passwords must be at least 12 characters.'],
        );

        $user = User::create([
            ...$this->attributes($data),
            'password_hash' => Hash::make($data['password']),
            'is_active' => true,
            'must_change_password' => true,
        ]);
        Audit::log('CREATE', 'User', $user->id, 'Created user '.($user->username ?: $user->email));

        return back()->with('status', 'Account created. Give the initial password to the person directly; they must change it at first sign-in.');
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if ($user->role === Role::SUPER_ADMIN && $data['role'] !== Role::SUPER_ADMIN->value) {
            $this->keepOneSuperAdmin($user);
        }
        $user->update($this->attributes($data));
        Audit::log('UPDATE', 'User', $user->id, 'Updated user '.($user->username ?: $user->email));

        return back()->with('status', 'Account updated.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:12']], ['password.min' => 'Passwords must be at least 12 characters.']);
        $user->update(['password_hash' => Hash::make($data['password']), 'must_change_password' => true]);
        Audit::log('UPDATE', 'User', $user->id, 'Reset the password of '.($user->username ?: $user->email));

        return back()->with('status', 'Password reset. '.Format::fullName($user).' must choose a new one at the next sign-in.');
    }

    public function toggle(Request $request, User $user)
    {
        if ($user->is_active) {
            abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate the account you are signed in with.');
            if ($user->role === Role::SUPER_ADMIN) {
                $this->keepOneSuperAdmin($user);
            }
        }
        $user->update(['is_active' => ! $user->is_active]);
        Audit::log('UPDATE', 'User', $user->id, ($user->is_active ? 'Activated ' : 'Deactivated ').($user->username ?: $user->email));

        return back()->with('status', Format::fullName($user).($user->is_active ? ' can sign in again.' : ' can no longer sign in.'));
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete the account you are signed in with.');
        if ($user->role === Role::SUPER_ADMIN) {
            $this->keepOneSuperAdmin($user);
        }
        $name = Format::fullName($user);
        $user->delete();
        Audit::log('DELETE', 'User', $user->id, "Deleted user {$name}");

        return back()->with('status', "{$name}'s sign-in was deleted. Their employee record, if any, stays on file.");
    }

    private function keepOneSuperAdmin(User $user): void
    {
        $others = User::where('role', Role::SUPER_ADMIN)->where('is_active', true)->where('id', '!=', $user->id)->exists();
        if (! $others) {
            throw ValidationException::withMessages(['role' => 'Keep at least one active super administrator.']);
        }
    }

    private function validated(Request $request, ?User $user): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:191'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::enum(Role::class)],
            'employee_id' => ['nullable', 'exists:employees,id', Rule::unique('users', 'employee_id')->ignore($user?->id)],
        ], [
            'username.unique' => 'That username is already in use.',
            'username.regex' => 'Use letters, numbers, dots, dashes or underscores in the username.',
            'email.unique' => 'Another account already uses that email.',
            'employee_id.unique' => 'That employee already has a sign-in.',
        ]);
    }

    /** Splits the full name, and copies department and location from the linked employee so manager scopes work. */
    private function attributes(array $data): array
    {
        $parts = preg_split('/\s+/', trim($data['full_name']));
        $employee = ! empty($data['employee_id']) ? Employee::find($data['employee_id']) : null;

        return [
            'first_name' => $parts[0],
            'last_name' => count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $parts[0],
            'username' => trim($data['username']),
            'email' => ! empty($data['email']) ? strtolower(trim($data['email'])) : null,
            'role' => $data['role'],
            'employee_id' => $employee?->id,
            'department_id' => $employee?->department_id,
            'location_id' => $employee?->location_id,
        ];
    }
}
