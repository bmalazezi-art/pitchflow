<?php

use App\Http\Controllers\Admin\CityController as AdminCityController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FootballFieldController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\PublicAvailabilityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', PublicAvailabilityController::class)->name('availability')->middleware('throttle:120,1');
Route::post('/locale', LocaleController::class)->name('locale.update')->middleware('throttle:20,1');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');
    Route::get('/approval-pending', fn () => Inertia::render('Auth/ApprovalPending'))->name('approval.pending');

    Route::middleware(['verified', 'organization.approved'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::resource('fields', FootballFieldController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/calendar', [ReservationController::class, 'index'])->name('calendar');
        Route::get('/reservations', [ReservationController::class, 'list'])->name('reservations.index');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
        Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{customer}/notes', [CustomerNoteController::class, 'store'])->name('customers.notes.store');
        Route::get('/reports', ReportController::class)->name('reports');
        Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])->name('settings.organization');
        Route::put('/settings/organization', [OrganizationSettingsController::class, 'update'])->name('settings.organization.update');
        Route::get('/search', SearchController::class)->name('search')->middleware('throttle:60,1');
        Route::get('/admin/organizations', [AdminOrganizationController::class, 'index'])->name('admin.organizations');
        Route::patch('/admin/organizations/{organization}', [AdminOrganizationController::class, 'update'])->name('admin.organizations.update');
        Route::get('/admin/cities', [AdminCityController::class, 'index'])->name('admin.cities');
        Route::post('/admin/cities', [AdminCityController::class, 'store'])->name('admin.cities.store');
        Route::put('/admin/cities/{city}', [AdminCityController::class, 'update'])->name('admin.cities.update');
    });
});
