<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\CityController as AdminCityController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\PlatformAnalyticsController;
use App\Http\Controllers\Admin\SupportRequestController as AdminSupportRequestController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\EmployeeInvitationController;
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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAnalyticsEventController;
use App\Http\Controllers\PublicAvailabilityController;
use App\Http\Controllers\PublicFieldListingController;
use App\Http\Controllers\PublicWaitingListController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SupportRequestController;
use App\Http\Controllers\WaitingListRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', PublicAvailabilityController::class)->name('availability')->middleware('throttle:120,1');
Route::get('/football-fields', PublicFieldListingController::class)->name('public.fields')->middleware('throttle:120,1');
Route::post('/analytics/events', [PublicAnalyticsEventController::class, 'store'])->name('analytics.events.store')->middleware('throttle:180,1');
Route::post('/waiting-list', [PublicWaitingListController::class, 'store'])->name('waiting-list.store')->middleware('throttle:10,1');
Route::get('/privacy', fn () => Inertia::render('Public/Legal', ['document' => 'privacy']))->name('privacy');
Route::get('/terms', fn () => Inertia::render('Public/Legal', ['document' => 'terms']))->name('terms');
Route::post('/locale', LocaleController::class)->name('locale.update');
Route::get('/auth/status', function () {
    $response = auth()->check()
        ? response()->noContent()
        : response()->json(['authenticated' => false], 401);

    return $response->withHeaders([
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('auth.status');
Route::get('/employee/invite/{token}', [EmployeeInvitationController::class, 'show'])
    ->middleware('throttle:20,1')->name('employee.invite.show');
Route::post('/employee/invite/{token}', [EmployeeInvitationController::class, 'store'])
    ->middleware('throttle:10,1')->name('employee.invite.accept');
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'no.cache.auth'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');
    Route::patch('/email', [EmailVerificationController::class, 'updateEmail'])
        ->middleware('throttle:6,1')->name('verification.email.update');
    Route::get('/approval-pending', fn () => Inertia::render('Auth/ApprovalPending'))->name('approval.pending');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware(['organization.approved'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::resource('fields', FootballFieldController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/employees/{employee}/status', [EmployeeController::class, 'status'])->name('employees.status');
        Route::post('/employees/{employee}/resend-invitation', [EmployeeController::class, 'resendInvitation'])->name('employees.invitation.resend');
        Route::post('/employees/{employee}/reset-password-link', [EmployeeController::class, 'createPasswordResetLink'])->name('employees.password.reset-link');
        Route::get('/calendar', [ReservationController::class, 'index'])->name('calendar');
        Route::get('/reservations', [ReservationController::class, 'list'])->name('reservations.index');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
        Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
        Route::patch('/reservations/{reservation}/paid', [ReservationController::class, 'markPaid'])->name('reservations.paid');
        Route::patch('/reservations/{reservation}/complete', [ReservationController::class, 'complete'])->name('reservations.complete');
        Route::post('/reservations/{reservation}/correction-requests', [ReservationController::class, 'requestCorrection'])->name('reservations.correction-requests.store');
        Route::patch('/reservation-correction-requests/{correctionRequest}', [ReservationController::class, 'reviewCorrection'])->name('reservation-correction-requests.review');
        Route::patch('/waiting-list/{waitingListRequest}/notified', [WaitingListRequestController::class, 'markNotified'])->name('waiting-list.notified');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{customer}/notes', [CustomerNoteController::class, 'store'])->name('customers.notes.store');
        Route::put('/customers/{customer}/notes/{note}', [CustomerNoteController::class, 'update'])->name('customers.notes.update');
        Route::get('/reports', ReportController::class)->name('reports');
        Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])->name('settings.organization');
        Route::put('/settings/organization', [OrganizationSettingsController::class, 'update'])->name('settings.organization.update');
        Route::post('/support-requests', [SupportRequestController::class, 'store'])->name('support-requests.store');
        Route::get('/search', SearchController::class)->name('search')->middleware('throttle:60,1');
        Route::get('/admin/organizations', [AdminOrganizationController::class, 'index'])->name('admin.organizations');
        Route::post('/admin/organizations', [AdminOrganizationController::class, 'store'])->name('admin.organizations.store');
        Route::post('/admin/organizations/{organization}/notes', [AdminOrganizationController::class, 'storeNote'])->name('admin.organizations.notes.store');
        Route::get('/admin/analytics', PlatformAnalyticsController::class)->name('admin.analytics');
        Route::redirect('/admin/stats', '/admin/analytics')->name('admin.stats');
        Route::get('/admin/audit-logs', AdminAuditLogController::class)->name('admin.audit-logs');
        Route::get('/admin/support-requests', [AdminSupportRequestController::class, 'index'])->name('admin.support-requests');
        Route::patch('/admin/support-requests/{supportRequest}', [AdminSupportRequestController::class, 'update'])->name('admin.support-requests.update');
        Route::patch('/admin/organizations/{organization}', [AdminOrganizationController::class, 'update'])->name('admin.organizations.update');
        Route::put('/admin/organizations/{organization}/subscription', [AdminOrganizationController::class, 'updateSubscription'])->name('admin.organizations.subscription');
        Route::get('/admin/cities', [AdminCityController::class, 'index'])->name('admin.cities');
        Route::post('/admin/cities', [AdminCityController::class, 'store'])->name('admin.cities.store');
        Route::put('/admin/cities/{city}', [AdminCityController::class, 'update'])->name('admin.cities.update');
    });
});
