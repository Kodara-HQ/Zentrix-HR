<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'hr'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Modules still being moved from the Node/React version show a holding page until their controller lands.
    // Employees reach attendance, leave, payroll, notifications and settings through ESS, so those stay open to every role.
    $ess = ['/attendance', '/leave', '/payroll', '/notifications', '/settings'];
    foreach (Navigation::modules() as $module) {
        $name = trim($module['path'], '/');
        $route = Route::view($module['path'], 'pending', ['module' => $module['label']])->name($name.'.index');
        if (! in_array($module['path'], $ess, true)) {
            $route->middleware('role:'.implode(',', $module['roles']));
        }
    }
    Route::view('/employees/{employee}', 'pending', ['module' => 'Employee profile'])->name('employees.show');
});
