<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OfficerAnalyticsController;
use App\Http\Controllers\OfficerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicComplaintController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/* ------------------------------------------------------------------ */
/* Public                                                              */
/* ------------------------------------------------------------------ */
Route::get('/', [HomeController::class, 'home']);
Route::get('/restaurants', [RestaurantController::class, 'index']);
Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])->whereNumber('restaurant');
Route::get('/transparency', [PublicComplaintController::class, 'transparency']);
Route::get('/verify/{reference}', [PublicComplaintController::class, 'verify']);
Route::get('/verify/{reference}/qr.svg', [PublicComplaintController::class, 'qr']);

/* ------------------------------------------------------------------ */
/* Authentication (custom token sessions + mandatory e-mail OTP)       */
/* ------------------------------------------------------------------ */
Route::middleware('guest.session')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/login', [AuthController::class, 'showLogin']);
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:10,1');
    Route::get('/verify-2fa', [AuthController::class, 'showVerify']);
    Route::post('/verify-2fa', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:10,1');
    Route::post('/resend-2fa', [AuthController::class, 'resendTwoFactor'])->middleware('throttle:5,1');
});

/* ------------------------------------------------------------------ */
/* Any signed-in user                                                  */
/* ------------------------------------------------------------------ */
Route::middleware('auth.session')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/home', [HomeController::class, 'redirectHome']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);

    // Evidence files & complaint messages (access checked per complaint)
    Route::get('/evidence/{evidence}', [EvidenceController::class, 'show'])->whereNumber('evidence');
    Route::post('/complaints/{complaint}/messages', [ComplaintController::class, 'message'])->whereNumber('complaint');
    Route::get('/complaints/{complaint}/report', [ComplaintController::class, 'report'])->whereNumber('complaint');

    // Adding a restaurant (customers while reviewing/complaining; staff too)
    Route::get('/restaurants/create', [RestaurantController::class, 'create']);
    Route::post('/restaurants', [RestaurantController::class, 'store']);

    /* ---------------- Customer ---------------- */
    Route::middleware('role:customer')->group(function () {
        Route::get('/dashboard', [HomeController::class, 'customerDashboard']);

        Route::get('/reviews', [ReviewController::class, 'index']);
        Route::get('/reviews/create', [ReviewController::class, 'create']);
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::get('/reviews/{id}/edit', [ReviewController::class, 'edit'])->whereNumber('id');
        Route::put('/reviews/{id}', [ReviewController::class, 'update'])->whereNumber('id');

        Route::get('/complaints', [ComplaintController::class, 'index']);
        Route::get('/complaints/create', [ComplaintController::class, 'create']);
        Route::post('/complaints', [ComplaintController::class, 'store']);
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->whereNumber('complaint');
        Route::post('/complaints/{complaint}/evidence', [ComplaintController::class, 'addEvidence'])->whereNumber('complaint');
        Route::get('/rewards', [ComplaintController::class, 'rewards']);
    });

    // Approved reviews feed + single review (all roles)
    Route::get('/user/reviews', [ReviewController::class, 'allApproved']);
    Route::get('/reviews/{id}', [ReviewController::class, 'show'])->whereNumber('id');

    /* ---------------- Verification officer (and admin) ---------------- */
    Route::middleware('role:officer,admin')->prefix('officer')->group(function () {
        Route::get('/', [OfficerController::class, 'dashboard']);
        Route::get('/complaints/{complaint}', [OfficerController::class, 'show'])->whereNumber('complaint');
        Route::post('/complaints/{complaint}/assign', [OfficerController::class, 'assign'])->whereNumber('complaint');
        Route::post('/complaints/{complaint}/status', [OfficerController::class, 'updateStatus'])->whereNumber('complaint');
        Route::post('/complaints/{complaint}/duplicates/{duplicate}', [OfficerController::class, 'resolveDuplicate'])->whereNumber(['complaint', 'duplicate']);
        Route::get('/analytics', [OfficerAnalyticsController::class, 'index']);
        Route::get('/heatmap', [OfficerAnalyticsController::class, 'heatmap']);
    });

    /* ---------------- Admin ---------------- */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/pending-requests', [AdminController::class, 'pendingRequests']);
        Route::get('/reviews', [ReviewController::class, 'allApproved']);
        Route::post('/reviews/{id}/approve', [AdminController::class, 'approveReview'])->whereNumber('id');
        Route::post('/reviews/{id}/reject', [AdminController::class, 'rejectReview'])->whereNumber('id');
        Route::get('/review-actions', [AdminController::class, 'reviewActions']);
        Route::get('/audit', [AdminController::class, 'audit']);
        Route::get('/keys', [AdminController::class, 'keyManagement']);
        Route::post('/keys/rotate', [AdminController::class, 'rotateKeys']);

        Route::get('/officers', [AdminUserController::class, 'officers']);
        Route::post('/officers', [AdminUserController::class, 'storeOfficer']);
        Route::get('/users', [AdminUserController::class, 'customers']);
        Route::post('/users/{user}/toggle', [AdminUserController::class, 'toggleActive'])->whereNumber('user');
        Route::post('/users/{user}/revoke-sessions', [AdminUserController::class, 'revokeSessions'])->whereNumber('user');
    });
});
