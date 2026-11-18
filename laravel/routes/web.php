<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\RelationsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WelfareController;
use App\Http\Controllers\ExitController;
use App\Http\Controllers\UserController;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

// Fingerprint clock (no sign-in; the device key authenticates it). Same address as the Node version.
Route::post('/api/time/biometric/punch', [AttendanceController::class, 'biometric'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->middleware('throttle:120,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'hr'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Employees
    $writers = 'role:HR_ADMIN,HR_MANAGER';
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index')
        ->middleware('role:HR_ADMIN,HR_MANAGER,DEPARTMENT_MANAGER,SUPERVISOR,PAYROLL_OFFICER');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store')->middleware($writers);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy')->middleware($writers);
    Route::post('/employees/{employee}/actions', [EmployeeController::class, 'action'])->name('employees.action')->middleware($writers);
    Route::post('/employees/{employee}/education', [EmployeeController::class, 'addEducation'])->name('employees.education.store');
    Route::delete('/employees/{employee}/education/{item}', [EmployeeController::class, 'removeEducation'])->name('employees.education.destroy')->middleware($writers);
    Route::post('/employees/{employee}/skills', [EmployeeController::class, 'addSkill'])->name('employees.skills.store');
    Route::delete('/employees/{employee}/skills/{item}', [EmployeeController::class, 'removeSkill'])->name('employees.skills.destroy')->middleware($writers);
    Route::post('/employees/{employee}/pay-items', [EmployeeController::class, 'savePayItem'])->name('employees.pay-items.store')->middleware('role:HR_ADMIN,HR_MANAGER,PAYROLL_OFFICER');
    Route::delete('/employees/{employee}/pay-items/{item}', [EmployeeController::class, 'removePayItem'])->name('employees.pay-items.destroy')->middleware('role:HR_ADMIN,HR_MANAGER,PAYROLL_OFFICER');

    // Documents
    Route::post('/files', [FileController::class, 'store'])->name('files.store');
    Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy')->middleware('role:HR_ADMIN,HR_MANAGER,RECRUITMENT_OFFICER');

    // Leave (every role applies for their own; managers and HR decide)
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::get('/leave/form', [LeaveController::class, 'form'])->name('leave.form');
    Route::post('/leave/{leave}/decision', [LeaveController::class, 'decide'])->name('leave.decide')->middleware('role:SUPERVISOR,DEPARTMENT_MANAGER,HR_ADMIN,HR_MANAGER');
    Route::post('/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');

    // Payroll (officers run it, HR approves, everyone downloads their own approved payslips)
    $officers = 'role:HR_ADMIN,HR_MANAGER,PAYROLL_OFFICER';
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('/payroll/payslips/{payslip}', [PayrollController::class, 'payslip'])->name('payroll.payslip');
    Route::get('/payroll/runs/{run}', [PayrollController::class, 'show'])->name('payroll.show')->middleware($officers);
    Route::post('/payroll/process', [PayrollController::class, 'process'])->name('payroll.process')->middleware($officers);
    Route::post('/payroll/runs/{run}/decision', [PayrollController::class, 'decide'])->name('payroll.decide')->middleware('role:HR_ADMIN,HR_MANAGER');
    Route::post('/payroll/adjustments', [PayrollController::class, 'storeAdjustment'])->name('payroll.adjustments.store')->middleware($officers);
    Route::delete('/payroll/adjustments/{adjustment}', [PayrollController::class, 'destroyAdjustment'])->name('payroll.adjustments.destroy')->middleware($officers);
    Route::post('/payroll/loans', [PayrollController::class, 'storeLoan'])->name('payroll.loans.store')->middleware($officers);

    // Attendance (everyone sees their own; managers and HR record, correct and approve)
    $desk = 'role:'.implode(',', AttendanceController::DESK);
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/manual', [AttendanceController::class, 'storeManual'])->name('attendance.manual')->middleware($desk);
    Route::post('/attendance/corrections', [AttendanceController::class, 'storeCorrection'])->name('attendance.corrections.store');
    Route::post('/attendance/corrections/{correction}/decision', [AttendanceController::class, 'decideCorrection'])->name('attendance.corrections.decide')->middleware($desk);
    Route::post('/attendance/overtime', [AttendanceController::class, 'storeOvertime'])->name('attendance.overtime.store');
    Route::post('/attendance/overtime/{overtime}/decision', [AttendanceController::class, 'decideOvertime'])->name('attendance.overtime.decide')->middleware($desk);
    Route::post('/attendance/shifts', [AttendanceController::class, 'storeShift'])->name('attendance.shifts.store')->middleware('role:HR_ADMIN,HR_MANAGER,DEPARTMENT_MANAGER');
    Route::redirect('/clock', '/attendance');

    // User management (super administrators only)
    Route::middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Settings: account page for everyone, system settings for HR (index decides), backups for super administrators
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::middleware('role:HR_ADMIN,HR_MANAGER')->group(function () {
        Route::put('/settings/company', [SettingsController::class, 'saveCompany'])->name('settings.company');
        Route::put('/settings/hours', [SettingsController::class, 'saveHours'])->name('settings.hours');
        Route::put('/settings/workflows/{workflow}', [SettingsController::class, 'saveWorkflow'])->name('settings.workflows');
        Route::put('/settings/notifications', [SettingsController::class, 'saveNotifications'])->name('settings.notifications');
        Route::post('/settings/lists/{list}', [SettingsController::class, 'storeItem'])->name('settings.items.store');
        Route::patch('/settings/lists/{list}/{id}', [SettingsController::class, 'updateItem'])->name('settings.items.update');
        Route::delete('/settings/lists/{list}/{id}', [SettingsController::class, 'destroyItem'])->name('settings.items.destroy');
    });
    Route::middleware('role:SUPER_ADMIN')->group(function () {
        Route::post('/settings/backup', [SettingsController::class, 'backup'])->name('settings.backup');
        Route::get('/settings/backup/{file}', [SettingsController::class, 'downloadBackup'])->name('settings.backup.download');
    });

    // Recruitment (page roles from the menu; recruiters run the pipeline, the HR manager approves requisitions)
    Route::middleware('role:HR_ADMIN,HR_MANAGER,RECRUITMENT_OFFICER,DEPARTMENT_MANAGER')->group(function () {
        Route::get('/recruitment', [RecruitmentController::class, 'index'])->name('recruitment.index');
        Route::post('/recruitment/requisitions', [RecruitmentController::class, 'storeRequisition'])->name('recruitment.requisitions.store');
    });
    Route::post('/recruitment/requisitions/{requisition}/decision', [RecruitmentController::class, 'decideRequisition'])->name('recruitment.requisitions.decide')->middleware('role:HR_MANAGER');
    Route::middleware('role:'.implode(',', RecruitmentController::RECRUITERS))->group(function () {
        Route::post('/recruitment/vacancies', [RecruitmentController::class, 'storeVacancy'])->name('recruitment.vacancies.store');
        Route::patch('/recruitment/vacancies/{vacancy}', [RecruitmentController::class, 'updateVacancy'])->name('recruitment.vacancies.update');
        Route::post('/recruitment/applicants', [RecruitmentController::class, 'storeApplicant'])->name('recruitment.applicants.store');
        Route::patch('/recruitment/applicants/{applicant}', [RecruitmentController::class, 'updateApplicant'])->name('recruitment.applicants.update');
        Route::post('/recruitment/applicants/{applicant}/interviews', [RecruitmentController::class, 'storeInterview'])->name('recruitment.interviews.store');
        Route::patch('/recruitment/interviews/{interview}', [RecruitmentController::class, 'scoreInterview'])->name('recruitment.interviews.score');
        Route::post('/recruitment/applicants/{applicant}/offers', [RecruitmentController::class, 'storeOffer'])->name('recruitment.offers.store');
        Route::post('/recruitment/offers/{offer}/accept', [RecruitmentController::class, 'acceptOffer'])->name('recruitment.offers.accept');
    });

    // Performance (reviewers manage their team; employees self-assess on their own reviews)
    Route::middleware('role:HR_ADMIN,HR_MANAGER,DEPARTMENT_MANAGER,SUPERVISOR,EMPLOYEE')->group(function () {
        Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
        Route::post('/performance/appraisals/{appraisal}/self', [PerformanceController::class, 'selfAssess'])->name('performance.appraisals.self');
        Route::get('/training', [TrainingController::class, 'index'])->name('training.index');
        Route::post('/training/needs', [TrainingController::class, 'storeNeed'])->name('training.needs.store');
        Route::post('/training/certificates', [TrainingController::class, 'storeCertificate'])->name('training.certificates.store');
    });
    Route::middleware('role:'.implode(',', PerformanceController::REVIEWERS))->group(function () {
        Route::post('/performance/objectives', [PerformanceController::class, 'storeObjective'])->name('performance.objectives.store');
        Route::patch('/performance/objectives/{objective}', [PerformanceController::class, 'scoreObjective'])->name('performance.objectives.score');
        Route::post('/performance/appraisals', [PerformanceController::class, 'storeAppraisal'])->name('performance.appraisals.store');
        Route::post('/performance/appraisals/{appraisal}/review', [PerformanceController::class, 'review'])->name('performance.appraisals.review');
        Route::post('/performance/appraisals/{appraisal}/plans', [PerformanceController::class, 'storePlan'])->name('performance.plans.store');
    });
    Route::middleware('role:HR_ADMIN,HR_MANAGER')->group(function () {
        Route::post('/performance/cycles', [PerformanceController::class, 'storeCycle'])->name('performance.cycles.store');
        Route::patch('/performance/cycles/{cycle}', [PerformanceController::class, 'updateCycle'])->name('performance.cycles.update');
        Route::post('/training/programs', [TrainingController::class, 'storeProgram'])->name('training.programs.store');
        Route::patch('/training/programs/{program}', [TrainingController::class, 'updateProgram'])->name('training.programs.update');
        Route::patch('/training/enrollments/{enrollment}', [TrainingController::class, 'updateEnrollment'])->name('training.enrollments.update');
        Route::patch('/training/needs/{need}', [TrainingController::class, 'updateNeed'])->name('training.needs.update');
        Route::delete('/training/certificates/{certification}', [TrainingController::class, 'destroyCertificate'])->name('training.certificates.destroy');
    });
    Route::post('/training/programs/{program}/enrol', [TrainingController::class, 'enrol'])->name('training.enrol')->middleware('role:HR_ADMIN,HR_MANAGER,DEPARTMENT_MANAGER');

    // Employee relations (HR only)
    Route::middleware('role:HR_ADMIN,HR_MANAGER')->group(function () {
        Route::get('/relations', [RelationsController::class, 'index'])->name('relations.index');
        Route::post('/relations/grievances', [RelationsController::class, 'storeGrievance'])->name('relations.grievances.store');
        Route::patch('/relations/grievances/{grievance}', [RelationsController::class, 'updateGrievance'])->name('relations.grievances.update');
        Route::post('/relations/cases', [RelationsController::class, 'storeCase'])->name('relations.cases.store');
        Route::patch('/relations/cases/{case}', [RelationsController::class, 'updateCase'])->name('relations.cases.update');
        Route::post('/relations/warnings', [RelationsController::class, 'storeWarning'])->name('relations.warnings.store');
    });

    // Welfare and engagement (everyone takes part; HR decides and publishes)
    Route::get('/welfare', [WelfareController::class, 'index'])->name('welfare.index');
    Route::post('/welfare/requests', [WelfareController::class, 'storeRequest'])->name('welfare.requests.store');
    Route::post('/welfare/suggestions', [WelfareController::class, 'storeSuggestion'])->name('welfare.suggestions.store');
    Route::post('/welfare/feedback', [WelfareController::class, 'storeFeedback'])->name('welfare.feedback.store');
    Route::post('/welfare/surveys/{survey}/respond', [WelfareController::class, 'respondSurvey'])->name('welfare.surveys.respond');
    Route::middleware('role:HR_ADMIN,HR_MANAGER')->group(function () {
        Route::patch('/welfare/requests/{welfare}', [WelfareController::class, 'decideRequest'])->name('welfare.requests.decide');
        Route::post('/welfare/recognition', [WelfareController::class, 'storeRecognition'])->name('welfare.recognition.store');
        Route::patch('/welfare/suggestions/{suggestion}', [WelfareController::class, 'respondSuggestion'])->name('welfare.suggestions.respond');
        Route::post('/welfare/activities', [WelfareController::class, 'storeActivity'])->name('welfare.activities.store');
        Route::delete('/welfare/activities/{activity}', [WelfareController::class, 'destroyActivity'])->name('welfare.activities.destroy');
        Route::post('/welfare/surveys', [WelfareController::class, 'storeSurvey'])->name('welfare.surveys.store');
        Route::post('/welfare/surveys/{survey}/close', [WelfareController::class, 'closeSurvey'])->name('welfare.surveys.close');
    });

    // Exit management (anyone can resign; HR records other exits and decides)
    Route::get('/exit', [ExitController::class, 'index'])->name('exit.index');
    Route::post('/exit', [ExitController::class, 'store'])->name('exit.store');
    Route::middleware('role:HR_ADMIN,HR_MANAGER')->group(function () {
        Route::post('/exit/{separation}/decision', [ExitController::class, 'decide'])->name('exit.decide');
        Route::patch('/exit/clearance/{item}', [ExitController::class, 'clear'])->name('exit.clearance');
    });

    // Notifications (each person's own)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Modules still being moved from the Node/React version show a holding page until their controller lands.
    // Employees reach attendance, leave, payroll, notifications and settings through ESS, so those stay open to every role.
    $ported = ['/employees', '/leave', '/payroll', '/attendance', '/notifications', '/users', '/settings', '/recruitment', '/performance', '/training', '/relations', '/welfare', '/exit'];
    $ess = ['/attendance', '/leave', '/payroll', '/notifications', '/settings'];
    foreach (Navigation::modules() as $module) {
        if (in_array($module['path'], $ported, true)) {
            continue;
        }
        $name = trim($module['path'], '/');
        $route = Route::view($module['path'], 'pending', ['module' => $module['label']])->name($name.'.index');
        if (! in_array($module['path'], $ess, true)) {
            $route->middleware('role:'.implode(',', $module['roles']));
        }
    }
});
