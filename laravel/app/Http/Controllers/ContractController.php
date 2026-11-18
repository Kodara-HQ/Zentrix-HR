<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\HrLetter;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Contracts and issued HR letters across the people each manager may see. Ported from server/src/routes/workplace.ts. */
class ContractController extends Controller
{
    public const STATUSES = ['ACTIVE', 'RENEWED', 'ENDED', 'TERMINATED'];

    public function index(Request $request)
    {
        $user = $request->user();
        $visible = Access::scopeEmployees(Employee::query(), $user)->select('id');
        $filter = $request->query('show', 'all');

        return view('contracts.index', [
            'hr' => Access::isHr($user),
            'filter' => $filter,
            'contracts' => Contract::with('employee.department')->whereIn('employee_id', $visible)
                ->when($filter === 'expiring', fn ($q) => $q->where('status', 'ACTIVE')->whereBetween('end_date', [today()->toDateString(), today()->addDays(60)->toDateString()]))
                ->when($filter === 'active', fn ($q) => $q->where('status', 'ACTIVE'))
                ->orderByRaw('end_date is null')->orderBy('end_date')->get(),
            'letters' => HrLetter::with('employee')->whereIn('employee_id', $visible)
                ->when(! Access::canSeeDiscipline($user), fn ($q) => $q->whereNotIn('type', ['WARNING', 'TERMINATION']))
                ->orderByDesc('issued_on')->take(100)->get(),
            'people' => Access::scopeEmployees(Employee::query(), $user)->whereIn('status', ['ACTIVE', 'PROBATION', 'NOTICE', 'SUSPENDED'])->orderBy('first_name')->get()
                ->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', Rule::in(['Employment contract', 'Fixed-term contract', 'Casual engagement', 'Consultancy', 'Internship'])],
            'title' => ['nullable', 'string', 'max:191'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);
        $contract = Contract::create([...$data, 'title' => $data['title'] ?: "{$data['type']} — ".Format::fullName($employee), 'status' => 'ACTIVE']);
        Audit::log('CREATE', 'Contract', $contract->id, "{$contract->title}");

        return back()->with('status', 'Contract added.');
    }

    public function update(Request $request, Contract $contract)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'end_date' => ['nullable', 'date', 'after:'.$contract->start_date->toDateString()],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $contract->update($data);
        Audit::log('UPDATE', 'Contract', $contract->id, "{$contract->title}: ".Format::title($data['status']));

        return back()->with('status', 'Contract updated.');
    }
}
