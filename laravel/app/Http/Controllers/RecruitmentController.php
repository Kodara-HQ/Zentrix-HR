<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Applicant;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\ManpowerRequisition;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\EmployeeService;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Format;
use App\Support\Lookups;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Requisition → vacancy → applicant → interview → offer → employee. Ported from server/src/routes/recruitment.ts. */
class RecruitmentController extends Controller
{
    public const RECRUITERS = ['RECRUITMENT_OFFICER', 'HR_ADMIN', 'HR_MANAGER'];

    public const APPLICANT_STATUSES = ['APPLIED', 'SCREENED', 'SHORTLISTED', 'INTERVIEW', 'OFFERED', 'HIRED', 'REJECTED', 'WITHDRAWN'];

    public function __construct(private Notifier $notifier) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tabs = ['requisitions' => 'Requisitions', 'vacancies' => 'Vacancies', 'applicants' => 'Applicants', 'metrics' => 'Metrics'];
        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'requisitions';

        $vacancies = Vacancy::with('department', 'location', 'jobTitle')->withCount('applicants')->latest()->get();

        return view('recruitment.index', [
            'tabs' => $tabs,
            'tab' => $tab,
            'recruiter' => Access::hasRole($user, self::RECRUITERS),
            'approver' => Access::hasRole($user, ['HR_MANAGER']),
            'requisitions' => ManpowerRequisition::latest()->get(),
            'vacancies' => $vacancies,
            'applicants' => Applicant::with(['vacancy', 'interviews' => fn ($q) => $q->orderBy('scheduled_at'), 'offers' => fn ($q) => $q->latest()])
                ->when($request->query('vacancy_id'), fn ($q, $id) => $q->where('vacancy_id', $id))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest()->get(),
            'metrics' => $vacancies->map(function ($v) {
                $accepted = JobOffer::whereHas('applicant', fn ($q) => $q->where('vacancy_id', $v->id))->where('status', 'ACCEPTED')->min('updated_at');

                return [
                    'vacancy' => $v,
                    'cost' => (float) $v->advertising_cost + (float) $v->agency_cost,
                    'days' => $v->opened_on && $accepted ? (int) max(0, CarbonImmutable::parse($v->opened_on)->diffInDays(CarbonImmutable::parse($accepted))) : null,
                ];
            }),
            'interviewers' => User::where('is_active', true)->whereNot('role', Role::EMPLOYEE)->orderBy('first_name')->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->first_name} {$u->last_name}"])->all(),
            'names' => [
                'departments' => Lookups::departments(), 'jobTitles' => Lookups::jobTitles(), 'locations' => Lookups::locations(),
            ],
        ]);
    }

    public function storeRequisition(Request $request)
    {
        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'job_title_id' => ['required', 'exists:job_titles,id'],
            'location_id' => ['nullable', 'exists:work_locations,id'],
            'headcount' => ['required', 'integer', 'min:1', 'max:500'],
            'justification' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $requisition = ManpowerRequisition::create([...$data, 'requested_by_id' => $request->user()->id, 'status' => 'PENDING']);
        $this->notifier->notify(User::where('is_active', true)->whereIn('role', [Role::HR_MANAGER, Role::SUPER_ADMIN])->pluck('id')->all(),
            'Manpower requisition submitted', $data['justification'], 'RECRUITMENT', '/recruitment');
        Audit::log('CREATE', 'ManpowerRequisition', $requisition->id, "Requested {$data['headcount']} staff");

        return redirect()->route('recruitment.index')->with('status', 'Requisition sent to the HR manager.');
    }

    public function decideRequisition(Request $request, ManpowerRequisition $requisition)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])], 'note' => ['nullable', 'string', 'max:2000']]);
        abort_unless($requisition->status->value === 'PENDING', 422, 'This requisition has already been decided.');
        $requisition->update(['status' => $data['decision'], 'decision_note' => $data['note'] ?? null]);
        $this->notifier->notify([$requisition->requested_by_id], 'Requisition '.strtolower($data['decision']), $data['note'] ?? 'A manpower requisition was reviewed.', 'RECRUITMENT', '/recruitment');
        Audit::log('UPDATE', 'ManpowerRequisition', $requisition->id, ucfirst(strtolower($data['decision'])).' requisition');

        return back()->with('status', 'Requisition '.strtolower($data['decision']).'.');
    }

    public function storeVacancy(Request $request)
    {
        $data = $request->validate([
            'requisition_id' => ['nullable', Rule::exists('manpower_requisitions', 'id')->where('status', 'APPROVED')],
            'title' => ['required', 'string', 'min:2', 'max:191'],
            'department_id' => ['required', 'exists:departments,id'],
            'job_title_id' => ['required', 'exists:job_titles,id'],
            'location_id' => ['required', 'exists:work_locations,id'],
            'description' => ['required', 'string', 'min:5'],
            'requirements' => ['required', 'string', 'min:5'],
            'openings' => ['required', 'integer', 'min:1', 'max:500'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'gte:salary_min'],
            'advertising_cost' => ['nullable', 'numeric', 'min:0'],
            'agency_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['DRAFT', 'OPEN'])],
        ], ['requisition_id.exists' => 'Open a vacancy from an approved requisition.']);
        $vacancy = Vacancy::create([...$data, 'advertising_cost' => $data['advertising_cost'] ?? 0, 'agency_cost' => $data['agency_cost'] ?? 0,
            'opened_on' => $data['status'] === 'OPEN' ? today() : null]);
        Audit::log('CREATE', 'Vacancy', $vacancy->id, "Opened vacancy {$vacancy->title}");

        return redirect()->route('recruitment.index', ['tab' => 'vacancies'])->with('status', 'Vacancy saved.');
    }

    public function updateVacancy(Request $request, Vacancy $vacancy)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['DRAFT', 'OPEN', 'ON_HOLD', 'CLOSED', 'FILLED'])],
            'openings' => ['required', 'integer', 'min:1'],
            'advertising_cost' => ['nullable', 'numeric', 'min:0'],
            'agency_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        $extra = match ($data['status']) {
            'OPEN' => ['opened_on' => $vacancy->opened_on ?? today(), 'closed_on' => null],
            'CLOSED', 'FILLED' => ['closed_on' => today()],
            default => [],
        };
        $vacancy->update([...$data, ...$extra]);

        return back()->with('status', 'Vacancy updated.');
    }

    public function storeApplicant(Request $request)
    {
        $data = $request->validate([
            'vacancy_id' => ['required', 'exists:vacancies,id'],
            'first_name' => ['required', 'string', 'min:2', 'max:191'],
            'last_name' => ['required', 'string', 'min:2', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'min:7', 'max:40'],
            'source' => ['nullable', 'string', 'max:191'],
            'screening_score' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        Applicant::create([...$data, 'email' => strtolower($data['email']), 'status' => 'APPLIED']);

        return redirect()->route('recruitment.index', ['tab' => 'applicants'])->with('status', 'Applicant registered.');
    }

    public function updateApplicant(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_diff(self::APPLICANT_STATUSES, ['HIRED']))],
            'screening_score' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['status.in' => 'Hire an applicant by converting their accepted offer.']);
        abort_if($applicant->status->value === 'HIRED', 422, 'This applicant has already been hired.');
        $applicant->update($data);

        return back()->with('status', 'Applicant updated.');
    }

    public function storeInterview(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'interviewer_id' => ['nullable', 'exists:users,id'],
            'mode' => ['required', Rule::in(['In person', 'Phone', 'Video'])],
            'location' => ['nullable', 'string', 'max:191'],
        ]);
        $applicant->interviews()->create($data);
        if (in_array($applicant->status->value, ['APPLIED', 'SCREENED', 'SHORTLISTED'], true)) {
            $applicant->update(['status' => 'INTERVIEW']);
        }
        $this->notifier->notify([$data['interviewer_id'] ?? null], 'Interview scheduled',
            'Interview with '.Format::fullName($applicant).' on '.CarbonImmutable::parse($data['scheduled_at'])->format('j M Y H:i').'.', 'RECRUITMENT', '/recruitment?tab=applicants');

        return back()->with('status', 'Interview scheduled.');
    }

    public function scoreInterview(Request $request, Interview $interview)
    {
        $data = $request->validate([
            'score' => ['required', 'numeric', 'between:0,100'],
            'recommendation' => ['required', Rule::in(['Hire', 'Consider', 'Do not hire'])],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);
        $interview->update($data);

        return back()->with('status', 'Interview result saved.');
    }

    public function storeOffer(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'salary' => ['required', 'numeric', 'gt:0'],
            'start_date' => ['required', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_if($applicant->status->value === 'HIRED', 422, 'This applicant has already been hired.');
        $applicant->offers()->create([...$data, 'status' => 'SENT']);
        $applicant->update(['status' => 'OFFERED']);
        Audit::log('CREATE', 'JobOffer', $applicant->id, 'Offer made to '.Format::fullName($applicant));

        return back()->with('status', 'Offer recorded.');
    }

    /** The candidate accepted: create the employee (with their sign-in) and fill the vacancy. */
    public function acceptOffer(Request $request, JobOffer $offer, EmployeeService $employees)
    {
        $data = $request->validate([
            'gender' => ['required', Rule::in(Lookups::GENDERS)],
            'date_of_birth' => ['required', 'date', 'before:-15 years'],
        ], ['date_of_birth.before' => 'Check the date of birth; the new employee must be at least 15.']);
        $offer->load('applicant.vacancy');
        abort_if($offer->status->value === 'ACCEPTED', 422, 'This offer is already accepted.');
        $applicant = $offer->applicant;
        $vacancy = $applicant->vacancy;
        if (Employee::where('email', $applicant->email)->exists() || User::where('email', $applicant->email)->exists()) {
            throw ValidationException::withMessages(['date_of_birth' => "{$applicant->email} already belongs to someone on the system. Correct the applicant's email first."]);
        }
        $type = EmploymentType::where('name', 'Full-time')->first() ?? EmploymentType::first();
        abort_unless($type, 422, 'Add an employment type under System settings before hiring.');

        [$employee, $password] = DB::transaction(function () use ($employees, $applicant, $vacancy, $offer, $type, $data) {
            $start = CarbonImmutable::parse($offer->start_date);
            $result = $employees->hire([
                'first_name' => $applicant->first_name, 'last_name' => $applicant->last_name, 'email' => $applicant->email, 'phone' => $applicant->phone,
                'gender' => $data['gender'], 'date_of_birth' => $data['date_of_birth'],
                'department_id' => $vacancy->department_id, 'job_title_id' => $vacancy->job_title_id, 'location_id' => $vacancy->location_id,
                'employment_type_id' => $type->id, 'date_of_employment' => $start->toDateString(),
                'probation_end_date' => $start->addMonths(6)->toDateString(), 'basic_salary' => $offer->salary,
            ], true);
            $offer->update(['status' => 'ACCEPTED']);
            $applicant->update(['status' => 'HIRED', 'employee_id' => $result[0]->id]);
            $filled = $vacancy->filled + 1;
            $vacancy->update(['filled' => $filled] + ($filled >= $vacancy->openings ? ['status' => 'FILLED', 'closed_on' => today()] : []));

            return $result;
        });

        return redirect()->route('employees.show', $employee)->with('status', Format::fullName($employee)." is now an employee ({$employee->employee_no}). Temporary password: {$password} — it is shown only once.");
    }
}
