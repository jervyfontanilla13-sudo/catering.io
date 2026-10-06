<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminGalleryController;
use App\Http\Controllers\AdminHelpController;
use App\Http\Controllers\AdminPackageController;
use App\Http\Controllers\AdminReservationController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ManageWebsiteAuthenticationController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReservationPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/services', [PublicController::class, 'services'])->name('services');
Route::get('/packages', [PublicController::class, 'packages'])->name('packages');
Route::get('/packages/{package:slug}', [PublicController::class, 'packageShow'])->name('packages.show');
Route::get('/gallery', [PublicController::class, 'gallery'])->name('gallery');
Route::get('/gallery-images/{path}', [PublicController::class, 'galleryImage'])->where('path', '.*')->name('gallery.image');
Route::get('/package-images/{path}', [PublicController::class, 'packageImage'])->where('path', '.*')->name('package.image');
Route::get('/reservation', [PublicController::class, 'reservation'])->name('reservation');
Route::get('/reservation/status', [PublicController::class, 'reservationStatus'])->name('reservation.status');
Route::get('/reservation/availability', [ReservationController::class, 'availability'])->name('reservation.availability');
Route::post('/reservation', [ReservationController::class, 'store'])->middleware('throttle:5,10')->name('reservation.store');
Route::get('/inquiry', [PublicController::class, 'inquiry'])->name('inquiry');
Route::post('/inquiry', [InquiryController::class, 'store'])->middleware('throttle:5,10')->name('inquiry.store');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::get('/support', [PublicController::class, 'support'])->name('support');
Route::get('/help', fn () => redirect()->route('support'))->name('help');
Route::get('/manual', fn () => redirect()->route('support'))->name('manual');

Route::get('/admin/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.post');
Route::get('/admin/setup', [AuthController::class, 'showPrimaryAdminSetup'])->name('admin.setup');
Route::post('/admin/setup', [AuthController::class, 'createPrimaryAdmin'])->middleware('throttle:3,10')->name('admin.setup.store');
Route::get('/admin/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->middleware('guest')->name('password.request');
Route::post('/admin/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware(['guest', 'throttle:3,10'])->name('password.email');
Route::get('/admin/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->middleware('guest')->name('password.reset');
Route::post('/admin/reset-password', [AuthController::class, 'resetPassword'])->middleware(['guest', 'throttle:5,10'])->name('password.update');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::middleware(['ensure.admin', 'capture.activity'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/reservations', [AdminController::class, 'reservations'])->name('admin.reservations');
    Route::get('/reservations/create', [AdminReservationController::class, 'create'])->name('admin.reservations.create');
    Route::post('/reservations', [AdminReservationController::class, 'store'])->name('admin.reservations.store');
    Route::get('/reservations/export', [AdminController::class, 'exportReservationsCsv'])->name('admin.reservations.export');
    Route::get('/reservations/{reservation}', [AdminController::class, 'showReservation'])->name('admin.reservations.show');
    Route::get('/reservations/{reservation}/service-contract/{contract}/preview', [AdminController::class, 'previewReservationContract'])->whereNumber('contract')->name('admin.reservations.contract.preview');
    Route::get('/reservations/{reservation}/service-contract/{contract}/download', [AdminController::class, 'downloadReservationContract'])->whereNumber('contract')->name('admin.reservations.contract.download');
    Route::patch('/reservations/{reservation}', [AdminController::class, 'updateReservation'])->name('admin.reservations.update');
    Route::post('/reservations/{reservation}/accept', [AdminController::class, 'acceptReservation'])->name('admin.reservations.accept');
    Route::post('/reservations/{reservation}/complete', [AdminController::class, 'completeReservation'])->name('admin.reservations.complete');
    Route::post('/reservations/{reservation}/cancel', [AdminController::class, 'cancelReservation'])->name('admin.reservations.cancel');
    Route::post('/reservations/{reservation}/service-contract', [AdminController::class, 'uploadReservationContract'])->name('admin.reservations.contract');
    Route::delete('/reservations/{reservation}/service-contract/{contract}', [AdminController::class, 'deleteReservationContract'])->name('admin.reservations.contract.delete');
    Route::scopeBindings()->group(function () {
        Route::get('/reservations/{reservation}/payments', [ReservationPaymentController::class, 'index'])->name('admin.reservations.payments');
        Route::get('/reservations/{reservation}/payments/print', [ReservationPaymentController::class, 'print'])->name('admin.reservations.payments.print');
        Route::post('/reservations/{reservation}/payments', [ReservationPaymentController::class, 'store'])
            ->withoutMiddleware('capture.activity')
            ->name('admin.reservations.payments.store');
        Route::get('/reservations/{reservation}/payments/{payment}/receipt', [ReservationPaymentController::class, 'receipt'])->name('admin.reservations.payments.receipt');
        Route::post('/reservations/{reservation}/refunds', [ReservationPaymentController::class, 'storeRefund'])
            ->withoutMiddleware('capture.activity')
            ->name('admin.reservations.refunds.store');
        Route::patch('/reservations/{reservation}/payment-details', [ReservationPaymentController::class, 'updateDetails'])->name('admin.reservations.payments.details');
        Route::put('/reservations/{reservation}/payments/{payment}', [ReservationPaymentController::class, 'update'])
            ->withoutMiddleware('capture.activity')
            ->middleware('confirm.admin-password')
            ->name('admin.reservations.payments.update');
    });
    Route::get('/inquiries', [AdminController::class, 'inquiries'])->name('admin.inquiries');
    Route::get('/inquiries/{inquiry}', [AdminController::class, 'showInquiry'])->name('admin.inquiries.show');
    Route::post('/inquiries/{inquiry}/reply', [AdminController::class, 'replyToInquiry'])->name('admin.inquiries.reply');
    Route::delete('/inquiries/{inquiry}', [AdminController::class, 'destroyInquiry'])->name('admin.inquiries.destroy');
    // Available to every authenticated admin (full or limited) — documentation, not a sensitive operation.
    Route::get('/support', [AdminHelpController::class, 'support'])->name('admin.support');
    Route::get('/help', fn () => redirect()->route('admin.support'))->name('admin.help');
    Route::get('/manual', fn () => redirect()->route('admin.support'))->name('admin.manual');
    Route::middleware('ensure.full-admin')->group(function () {
        Route::post('/manage-website/reauthenticate', [ManageWebsiteAuthenticationController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('admin.manage-website.reauthenticate');
        Route::middleware('ensure.manage-website')->group(function () {
            Route::resource('packages', AdminPackageController::class)
                ->except('show')
                ->names('admin.packages');
            Route::resource('services', AdminServiceController::class)
                ->except('show')
                ->names('admin.services');
            Route::patch('/services/{service}/toggle', [AdminServiceController::class, 'toggle'])
                ->name('admin.services.toggle');
            Route::resource('gallery', AdminGalleryController::class)
                ->except(['show', 'create', 'edit'])
                ->names('admin.gallery');
        });
        Route::get('/team-admins', [AdminUserController::class, 'index'])->name('admin.users');
        Route::post('/team-admins', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('/team-admins/{user}/name', [AdminUserController::class, 'updateName'])->name('admin.users.update-name');
        Route::patch('/team-admins/{user}/status', [AdminUserController::class, 'updateStatus'])
            ->middleware('confirm.admin-password')
            ->name('admin.users.status');
        Route::put('/team-admins/{user}/reset-password', [AdminUserController::class, 'resetPassword'])
            ->middleware('confirm.admin-password')
            ->name('admin.users.reset');
        Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports');
        Route::get('/reports/export/{period}', [ReportController::class, 'export'])->whereIn('period', ['daily', 'weekly', 'monthly', 'yearly'])->name('admin.reports.export');
        Route::get('/reports/export/{period}/excel', [ReportController::class, 'exportExcel'])->whereIn('period', ['daily', 'weekly', 'monthly', 'yearly'])->name('admin.reports.export.excel');
        Route::get('/analytics', [AdminController::class, 'analytics'])->name('admin.analytics');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs');
        // New backups exclude administrator accounts; archived users are never restored.
        // Full-admin access protects the sensitive application data that backups contain.
        Route::get('/backups', [BackupController::class, 'index'])->name('admin.backups');
        Route::post('/backups/check-database', [BackupController::class, 'checkDatabase'])->name('admin.backups.check-database');
        Route::post('/backups/create', [BackupController::class, 'createBackup'])->name('admin.backups.create');
        Route::post('/backups/upload', [BackupController::class, 'uploadBackup'])->name('admin.backups.upload');
        Route::post('/backups/validate', [BackupController::class, 'validateBackup'])->name('admin.backups.validate');
        Route::post('/backups/restore', [BackupController::class, 'restoreBackup'])->name('admin.backups.restore');
        Route::post('/backups/download', [BackupController::class, 'downloadBackup'])->name('admin.backups.download');
        Route::delete('/backups', [BackupController::class, 'deleteBackup'])->name('admin.backups.delete');
    });
});
