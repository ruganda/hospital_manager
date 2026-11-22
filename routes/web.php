<?php

use App\Http\Controllers\Ajax\SlotController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DoctorScheduleController;
use App\Http\Controllers\FlutterwaveWebhookController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MobileMoneyController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

// Payment provider callbacks (no session, no CSRF; authenticated by a shared secret header)
Route::post('webhooks/flutterwave', FlutterwaveWebhookController::class)->middleware('throttle:120,1')->name('webhooks.flutterwave');

Route::middleware('auth')->group(function () {

    // ---------------------------------------------------------------- everyone
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ---------------------------------------------------------------- admin only
    Route::middleware('role:admin')->group(function () {
        Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::resource('users', UserController::class);

        Route::resource('departments', DepartmentController::class)->except(['index', 'show']);
        Route::resource('doctors', DoctorController::class)->except(['index', 'show']);
        Route::resource('schedules', DoctorScheduleController::class)->except(['index', 'show']);
    });

    // ---------------------------------------------------------------- directory (read only)
    Route::middleware('role:admin,receptionist,doctor,patient')->group(function () {
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
        Route::get('doctors', [DoctorController::class, 'index'])->name('doctors.index');
        Route::get('doctors/{doctor}', [DoctorController::class, 'show'])->name('doctors.show');
    });
    Route::get('schedules', [DoctorScheduleController::class, 'index'])
        ->middleware('role:admin,receptionist,doctor')->name('schedules.index');

    // ---------------------------------------------------------------- patients
    Route::middleware('role:admin,receptionist')->group(function () {
        Route::resource('patients', PatientController::class)->except(['index', 'show']);
    });
    Route::get('patients', [PatientController::class, 'index'])
        ->middleware('role:admin,receptionist,doctor,cashier')->name('patients.index');
    Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show'); // policy-checked

    // ---------------------------------------------------------------- appointments (policies decide)
    Route::patch('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'status'])->name('appointments.status');
    Route::resource('appointments', AppointmentController::class);

    // ---------------------------------------------------------------- consultation notes
    Route::resource('consultations', ConsultationController::class)->middleware('role:admin,doctor,patient');

    // ---------------------------------------------------------------- billing
    Route::middleware('role:admin,cashier')->group(function () {
        Route::resource('payments', PaymentController::class)->except(['show']);
    });
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::resource('invoices', InvoiceController::class)->middleware('role:admin,cashier,receptionist,patient');

    // Mobile Money (MTN / Airtel via Flutterwave): who may start one is decided by InvoicePolicy::payMobileMoney
    Route::post('invoices/{invoice}/mobile-money', [MobileMoneyController::class, 'store'])->middleware('throttle:10,1')->name('mobile-money.store');
    Route::get('mobile-money/{mobileMoney}/status', [MobileMoneyController::class, 'status'])->middleware('throttle:60,1')->name('mobile-money.status');
    Route::post('mobile-money/{mobileMoney}/refresh', [MobileMoneyController::class, 'refresh'])->middleware('throttle:20,1')->name('mobile-money.refresh');

    // ---------------------------------------------------------------- reports
    Route::middleware('role:admin,receptionist,doctor,cashier')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'export'])->name('reports.export');
    });

    // ---------------------------------------------------------------- AJAX endpoints (JSON)
    Route::prefix('ajax')->name('ajax.')->group(function () {
        Route::get('doctors/{doctor}/slots', SlotController::class)
            ->middleware('role:admin,receptionist,doctor,patient')->name('slots');
        Route::get('dashboard/charts', [DashboardController::class, 'charts'])->name('dashboard.charts');
    });
});

require __DIR__.'/auth.php';
