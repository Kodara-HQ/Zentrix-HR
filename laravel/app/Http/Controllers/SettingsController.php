<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\NotificationSetting;
use App\Models\PayrollRun;
use App\Models\ShiftPattern;
use App\Models\StatutoryConfig;
use App\Models\User;
use App\Models\WorkflowConfig;
use App\Models\WorkingCalendar;
use App\Support\Access;
use App\Support\Audit;
use App\Support\SettingsLists;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * System settings for HR, and account settings for everyone else.
 * Ported from server/src/routes/settings.ts and the React Settings page.
 */
class SettingsController extends Controller
{
    /** Approval chains the leave workflow understands (see LeaveService::steps). */
    public const WORKFLOW_OPTIONS = [
        'MANAGER,HR' => 'Manager, then HR',
        'HR' => 'HR only',
        'MANAGER' => 'Manager only',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        if (! Access::isHr($user)) {
            return view('settings.account', ['user' => $user]);
        }

        $lists = SettingsLists::all();
        $tabs = ['company' => 'Company', 'hours' => 'Working hours']
            + array_map(fn ($l) => $l['label'], $lists)
            + ['workflows' => 'Workflows', 'notifications' => 'Notifications', 'tax' => 'Tax rates']
            + ($user->role === Role::SUPER_ADMIN ? ['audit' => 'Audit log', 'backup' => 'Backup'] : []);
        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'company';

        $data = ['tabs' => $tabs, 'tab' => $tab];
        if (isset($lists[$tab])) {
            $list = $lists[$tab];
            $data['list'] = $list;
            $data['rows'] = $list['model']::orderBy($list['order'])->get();
        }

        return view('settings.index', $data + match ($tab) {
            'company' => ['company' => Company::first()],
            'hours' => ['calendar' => WorkingCalendar::where('is_active', true)->first()],
            'workflows' => ['workflows' => WorkflowConfig::orderBy('label')->get()],
            'notifications' => ['notices' => NotificationSetting::orderBy('label')->get(), 'mailReady' => config('mail.default') !== 'log'],
            'tax' => ['configs' => StatutoryConfig::orderByDesc('effective_from')->get()],
            'audit' => ['logs' => AuditLog::latest()->paginate(30)->withQueryString(), 'actors' => User::pluck('first_name', 'id')],
            'backup' => ['backups' => collect(Storage::files('backups'))->sortDesc()->take(10)->values()],
            default => [],
        });
    }

    public function saveCompany(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:191'],
            'legal_name' => ['required', 'string', 'min:2', 'max:191'],
            'tin' => ['nullable', 'string', 'max:40'],
            'ssnit_employer_no' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:191'],
            'country' => ['required', 'string', 'max:191'],
        ]);
        $company = Company::first() ?? new Company(['next_employee_no' => 1, 'currency' => 'GHS']);
        $company->fill($data)->save();
        Audit::log('UPDATE', 'Company', $company->id, 'Company profile updated');

        return back()->with('status', 'Company profile saved.');
    }

    public function saveHours(Request $request)
    {
        $data = $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:0,6'],
            'overtime_weekday' => ['required', 'numeric', 'min:1', 'max:5'],
            'overtime_weekend' => ['required', 'numeric', 'min:1', 'max:5'],
        ], ['end_time.after' => 'Closing time must be after the reporting time.']);

        [$sh, $sm] = array_map('intval', explode(':', $data['start_time']));
        [$eh, $em] = array_map('intval', explode(':', $data['end_time']));
        $hoursPerDay = round((($eh * 60 + $em) - ($sh * 60 + $sm)) / 60, 2);
        $workDays = array_map('intval', $data['work_days']);

        $calendar = WorkingCalendar::where('is_active', true)->first();
        // Shifts that simply mirrored the old office hours follow the new ones (as in the Node version).
        if ($calendar) {
            ShiftPattern::where('start_time', $calendar->start_time)->where('end_time', $calendar->end_time)
                ->update(['start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'grace_minutes' => $data['grace_minutes']]);
        }
        $calendar ??= new WorkingCalendar(['name' => 'Head office calendar', 'is_active' => true]);
        $calendar->fill([...$data, 'work_days' => $workDays, 'hours_per_day' => $hoursPerDay, 'hours_per_month' => round($hoursPerDay * 22, 2)])->save();
        Audit::log('UPDATE', 'WorkingCalendar', $calendar->id, "Working hours set to {$data['start_time']}–{$data['end_time']}");

        return back()->with('status', 'Working hours saved. New attendance is matched to these times.');
    }

    public function storeItem(Request $request, string $list)
    {
        $config = SettingsLists::get($list);
        $item = new $config['model'];
        $this->fillAndSave($request, $config, $item, $list);
        Audit::log('CREATE', class_basename($item), $item->id, "Added {$config['label']}: ".($item->name ?? $item->id));

        return redirect()->route('settings.index', ['tab' => $list])->with('status', 'Added.');
    }

    public function updateItem(Request $request, string $list, string $id)
    {
        $config = SettingsLists::get($list);
        $item = $config['model']::findOrFail($id);
        $this->fillAndSave($request, $config, $item, $list);
        Audit::log('UPDATE', class_basename($item), $item->id, "Updated {$config['label']}: ".($item->name ?? $item->id));

        return redirect()->route('settings.index', ['tab' => $list])->with('status', 'Saved.');
    }

    public function destroyItem(string $list, string $id)
    {
        $config = SettingsLists::get($list);
        $item = $config['model']::findOrFail($id);
        try {
            $item->delete();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1451) { // MySQL: row is referenced by a foreign key
                throw ValidationException::withMessages(['delete' => '“'.($item->name ?? 'This item').'” is still used by employees or other records, so it cannot be removed. Change those records first.']);
            }
            throw $e;
        }
        Audit::log('DELETE', class_basename($item), $item->id, "Removed {$config['label']}: ".($item->name ?? $item->id));

        return redirect()->route('settings.index', ['tab' => $list])->with('status', 'Removed.');
    }

    public function saveWorkflow(Request $request, WorkflowConfig $workflow)
    {
        $data = $request->validate(['steps' => ['required', Rule::in(array_keys(self::WORKFLOW_OPTIONS))]]);
        $workflow->update(['steps' => explode(',', $data['steps'])]);
        Audit::log('UPDATE', 'WorkflowConfig', $workflow->id, "{$workflow->label}: ".self::WORKFLOW_OPTIONS[$data['steps']]);

        return back()->with('status', "{$workflow->label} updated.");
    }

    public function saveNotifications(Request $request)
    {
        $inApp = (array) $request->input('in_app', []);
        $email = (array) $request->input('email', []);
        foreach (NotificationSetting::all() as $setting) {
            $setting->update(['in_app' => isset($inApp[$setting->id]), 'email' => isset($email[$setting->id])]);
        }
        Audit::log('UPDATE', 'NotificationSetting', null, 'Notification settings updated');

        return back()->with('status', 'Notification settings saved.');
    }

    /** JSON snapshot of the main records, saved under storage/app/private/backups and offered for download. */
    public function backup(Request $request)
    {
        $snapshot = [
            'exported_at' => now()->toIso8601String(),
            'company' => Company::all(),
            'departments' => Department::all(),
            'employees' => Employee::all(),
            'users' => User::all(['id', 'email', 'username', 'role', 'first_name', 'last_name', 'is_active']),
            'contracts' => \App\Models\Contract::all(),
            'leave_requests' => LeaveRequest::all(),
            'payroll_runs' => PayrollRun::with('payslips')->get(),
        ];
        $file = 'backups/zentrix-hr-'.now()->format('Y-m-d-His').'.json';
        Storage::put($file, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Audit::log('CREATE', 'Backup', null, 'Backup written: '.basename($file));

        return back()->with('status', 'Backup saved: '.basename($file).' ('.count($snapshot['employees']).' employees).');
    }

    public function downloadBackup(string $file)
    {
        $path = 'backups/'.basename($file);
        abort_unless(Storage::exists($path), 404);

        return Storage::download($path);
    }

    private function fillAndSave(Request $request, array $config, $item, string $list): void
    {
        $rules = [];
        $messages = [];
        foreach ($config['fields'] as $name => $field) {
            [$label, $type] = $field;
            $parts = array_filter(explode('|', $field[2] ?? 'nullable'));
            $rule = array_map(fn ($p) => $p === 'unique' ? Rule::unique($item->getTable(), $name)->ignore($item->id) : $p, $parts);
            if (! in_array('required', $parts, true)) {
                array_unshift($rule, 'nullable');
            }
            $rule[] = match ($type) {
                'number' => 'numeric', 'date' => 'date', 'time' => 'date_format:H:i', 'bool' => 'boolean', 'days' => 'array',
                'select' => Rule::in(array_keys(($field[3])())), default => 'string',
            };
            if ($type === 'text') {
                $rule[] = 'max:191';
            }
            $rules[$name] = $rule;
            $messages["{$name}.unique"] = "Another item already uses that {$label}.";
            $messages["{$name}.required"] = "{$label} is required.";
        }
        $data = $request->validate($rules, $messages);

        foreach ($config['fields'] as $name => [, $type]) {
            if ($type === 'bool') {
                $data[$name] = $request->boolean($name);
            }
            if ($type === 'days') {
                $data[$name] = array_map('intval', $data[$name] ?? []);
            }
            if (($data[$name] ?? null) === '') {
                $data[$name] = null;
            }
        }

        try {
            $item->fill($data)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'That already exists.']);
        }
    }
}
