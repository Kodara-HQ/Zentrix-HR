<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\EngagementActivity;
use App\Models\Recognition;
use App\Models\StaffFeedback;
use App\Models\Suggestion;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\WelfareRequest;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Welfare requests and engagement (recognition, suggestions, feedback, activities, pulse surveys). Ported from server/src/routes/workplace.ts. */
class WelfareController extends Controller
{
    public function __construct(private Notifier $notifier) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $hr = Access::isHr($user);
        $tabs = ['requests' => 'Requests', 'surveys' => 'Surveys', 'recognition' => 'Recognition', 'suggestions' => 'Suggestions', 'activities' => 'Activities'] + ($hr ? ['feedback' => 'Anonymous feedback'] : []);
        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'requests';
        $visible = Access::scopeEmployees(Employee::query(), $user)->select('id');

        return view('welfare.index', [
            'tabs' => $tabs, 'tab' => $tab, 'hr' => $hr, 'user' => $user,
            'requests' => WelfareRequest::with('employee')->when(! $hr, fn ($q) => $q->whereIn('employee_id', $visible))->latest()->get(),
            'surveys' => Survey::withCount('responses')->latest()->get(),
            'answered' => $user->employee_id ? SurveyResponse::where('employee_id', $user->employee_id)->pluck('survey_id')->all() : [],
            'recognitions' => Recognition::with('employee')->orderByDesc('awarded_on')->take(50)->get(),
            'suggestions' => Suggestion::when(! $hr, fn ($q) => $q->where('employee_id', $user->employee_id ?? '-'))->latest()->get(),
            'activities' => EngagementActivity::orderByDesc('activity_date')->get(),
            'feedback' => $hr ? StaffFeedback::latest()->get() : collect(),
            'people' => Employee::whereIn('status', ['ACTIVE', 'PROBATION', 'NOTICE'])->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all(),
        ]);
    }

    public function storeRequest(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'type' => ['required', Rule::in(['Bereavement', 'Medical', 'Wedding', 'Childbirth', 'Emergency loan', 'Other'])],
            'description' => ['required', 'string', 'min:5', 'max:3000'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $employeeId = Access::isHr($user) ? ($data['employee_id'] ?? $user->employee_id) : $user->employee_id;
        abort_unless($employeeId, 422, 'This sign-in is not linked to an employee.');
        WelfareRequest::create([...$data, 'employee_id' => $employeeId, 'status' => 'PENDING']);
        $this->notifier->notify($this->hrIds(), 'Welfare request', $data['type'].' — '.Format::fullName(Employee::find($employeeId)), 'WELFARE', '/welfare');

        return back()->with('status', 'Request sent to HR.');
    }

    public function decideRequest(Request $request, WelfareRequest $welfare)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['PENDING', 'APPROVED', 'REJECTED', 'FULFILLED'])], 'resolution' => ['nullable', 'string', 'max:3000']]);
        $welfare->update($data);
        $this->notifier->notify([User::where('employee_id', $welfare->employee_id)->value('id')], 'Welfare request '.strtolower($data['status']), $data['resolution'] ?: $welfare->type, 'WELFARE', '/welfare');

        return back()->with('status', 'Request updated.');
    }

    public function storeRecognition(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'title' => ['required', 'string', 'min:2', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'awarded_on' => ['required', 'date', 'before_or_equal:today'],
        ]);
        Recognition::create([...$data, 'awarded_by_id' => $request->user()->id]);
        $this->notifier->notify([User::where('employee_id', $data['employee_id'])->value('id')], 'Recognition', $data['title'], 'RECOGNITION', '/welfare?tab=recognition');

        return back()->with('status', 'Recognition recorded.');
    }

    public function storeSuggestion(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:5', 'max:3000']]);
        Suggestion::create([...$data, 'employee_id' => $request->user()->employee_id, 'status' => 'NEW']);

        return back()->with('status', 'Thank you. Your suggestion has been sent to HR.');
    }

    public function respondSuggestion(Request $request, Suggestion $suggestion)
    {
        $suggestion->update($request->validate(['status' => ['required', Rule::in(['NEW', 'UNDER_REVIEW', 'ADOPTED', 'DECLINED'])], 'response' => ['nullable', 'string', 'max:3000']]));

        return back()->with('status', 'Response saved.');
    }

    /** Anonymous feedback stores no employee at all. */
    public function storeFeedback(Request $request)
    {
        StaffFeedback::create([...$request->validate(['category' => ['required', 'string', 'min:2', 'max:191'], 'message' => ['required', 'string', 'min:5', 'max:3000']]), 'anonymous' => true, 'employee_id' => null]);

        return back()->with('status', 'Feedback sent anonymously.');
    }

    public function storeActivity(Request $request)
    {
        EngagementActivity::create($request->validate([
            'title' => ['required', 'string', 'min:2', 'max:191'],
            'activity_date' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]));

        return back()->with('status', 'Activity added.');
    }

    public function destroyActivity(EngagementActivity $activity)
    {
        $activity->delete();

        return back()->with('status', 'Activity removed.');
    }

    /** A pulse survey: each line is a statement rated 1–5; every response may add a comment. */
    public function storeSurvey(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'questions' => ['required', 'string'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $questions = collect(preg_split('/\R/', $data['questions']))->map(fn ($q) => trim($q))->filter()->values()
            ->map(fn ($q) => ['id' => (string) Str::ulid(), 'prompt' => $q, 'kind' => 'rating'])->all();
        abort_if(count($questions) === 0, 422, 'Add at least one question.');
        Survey::create([...$data, 'questions' => $questions, 'opens_on' => today(), 'is_open' => true]);

        return back()->with('status', 'Survey published.');
    }

    public function respondSurvey(Request $request, Survey $survey)
    {
        $employeeId = $request->user()->employee_id;
        abort_unless($employeeId, 422, 'Only staff with an employee record can respond.');
        abort_unless($survey->is_open && (! $survey->closes_on || $survey->closes_on->gte(today())), 422, 'This survey is closed.');
        $rules = ['comment' => ['nullable', 'string', 'max:2000']];
        foreach ($survey->questions as $q) {
            $rules["answers.{$q['id']}"] = ['required', 'integer', 'between:1,5'];
        }
        $data = $request->validate($rules, ['answers.*.required' => 'Answer every statement.']);
        SurveyResponse::updateOrCreate(['survey_id' => $survey->id, 'employee_id' => $employeeId], ['answers' => [...$data['answers'], 'comment' => $data['comment'] ?? null]]);

        return back()->with('status', 'Thank you for your response.');
    }

    public function closeSurvey(Survey $survey)
    {
        $survey->update(['is_open' => false]);

        return back()->with('status', 'Survey closed.');
    }

    private function hrIds(): array
    {
        return User::where('is_active', true)->whereIn('role', [Role::SUPER_ADMIN, Role::HR_ADMIN, Role::HR_MANAGER])->pluck('id')->all();
    }
}
