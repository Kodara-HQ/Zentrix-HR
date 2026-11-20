<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanySetup;
use App\Support\Audit;
use App\Support\Tenant;
use App\Support\Theme;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Companies, for the system administrator only (HrPortal keeps everyone else out of system.* routes). */
class SystemController extends Controller
{
    public function index()
    {
        return view('system.companies', [
            'companies' => Company::withCount(['employees', 'users'])->orderBy('name')->get(),
            'acting' => session(Tenant::SESSION_KEY),
        ]);
    }

    public function store(Request $request, CompanySetup $setup)
    {
        $company = $this->validatedCompany($request, null);
        $admin = $request->validate([
            'admin_first_name' => ['required', 'string', 'min:1', 'max:191'],
            'admin_last_name' => ['required', 'string', 'min:1', 'max:191'],
            'admin_username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'admin_email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:12'],
        ], [
            'admin_username.unique' => 'That username is already in use.',
            'admin_username.regex' => 'Use letters, numbers, dots, dashes or underscores in the username.',
            'admin_email.unique' => 'Another account already uses that email.',
            'admin_password.min' => 'Passwords must be at least 12 characters.',
        ]);

        $record = $setup->create($company, [
            'first_name' => $admin['admin_first_name'], 'last_name' => $admin['admin_last_name'],
            'username' => $admin['admin_username'], 'email' => $admin['admin_email'] ?? null, 'password' => $admin['admin_password'],
        ]);
        Audit::log('CREATE', 'Company', $record->id, "Set up company {$record->name}");

        return redirect()->route('system.companies.index')
            ->with('status', "{$record->name} is set up. Give {$admin['admin_username']} the initial password directly; they must change it at first sign-in.");
    }

    public function update(Request $request, Company $company)
    {
        $data = $this->validatedCompany($request, $company);
        $company->update($data + ['is_active' => $request->boolean('is_active')]);
        Audit::log('UPDATE', 'Company', $company->id, "Updated company {$company->name}");

        return back()->with('status', "{$company->name} saved.".($company->is_active ? '' : ' Its staff can no longer sign in.'));
    }

    /** Work inside a company as its super administrator. */
    public function open(Request $request, Company $company)
    {
        $request->session()->put(Tenant::SESSION_KEY, $company->id);
        Audit::log('LOGIN', 'Company', $company->id, "System administrator opened {$company->name}");

        return redirect()->route('dashboard');
    }

    public function leave(Request $request)
    {
        $request->session()->forget(Tenant::SESSION_KEY);

        return redirect()->route('system.companies.index');
    }

    private function validatedCompany(Request $request, ?Company $company): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:191', Rule::unique('companies', 'name')->ignore($company?->id)],
            'legal_name' => ['required', 'string', 'min:2', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:191'],
            'country' => ['required', 'string', 'max:191'],
            'currency' => ['required', 'string', 'size:3'],
            'theme_primary' => ['nullable', 'string', Theme::rule('The main colour')],
            'theme_accent' => ['nullable', 'string', Theme::rule('The accent colour')],
        ], ['name.unique' => 'Another company already has that name.']);
    }
}
