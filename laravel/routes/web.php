<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
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

    // Notifications (each person's own)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Modules still being moved from the Node/React version show a holding page until their controller lands.
    // Employees reach attendance, leave, payroll, notifications and settings through ESS, so those stay open to every role.
    $ported = ['/employees', '/leave', '/payroll', '/attendance', '/notifications'];
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
