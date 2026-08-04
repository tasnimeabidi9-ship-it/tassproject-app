<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AiActionsLogController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InternalMessageController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PowerBiController;
use App\Http\Controllers\ReplyController;
use App\Http\Controllers\ScraperController;
use App\Http\Controllers\SequenceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TargetProfileController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('internal.secret')->prefix('internal')->group(function () {
    Route::patch('/messages/{message}', [InternalMessageController::class, 'updateStatus']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/contacts/export', [ContactController::class, 'export']);
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::get('/contacts/{contact}', [ContactController::class, 'show']);

    Route::middleware('role:admin,rep')->group(function () {
        Route::post('/contacts', [ContactController::class, 'store']);
        Route::patch('/contacts/{contact}', [ContactController::class, 'update']);
        Route::patch('/contacts/{contact}/stage', [ContactController::class, 'updateStage']);
        Route::post('/contacts/import', [ContactController::class, 'import']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
    });

    Route::get('/profiles', [TargetProfileController::class, 'index']);
    Route::middleware('role:admin,rep')->group(function () {
        Route::post('/profiles', [TargetProfileController::class, 'store']);
        Route::patch('/profiles/{profile}', [TargetProfileController::class, 'update']);
        Route::delete('/profiles/{profile}', [TargetProfileController::class, 'destroy']);
    });

    Route::get('/leads/queue', [LeadController::class, 'queue']);
    Route::middleware('role:admin,rep')->group(function () {
        Route::patch('/leads/{lead}/approve', [LeadController::class, 'approve']);
        Route::patch('/leads/{lead}/reject', [LeadController::class, 'reject']);
        Route::post('/leads/bulk-approve', [LeadController::class, 'bulkApprove']);
    });

    Route::get('/scraper/status/{job}', [ScraperController::class, 'status']);
    Route::get('/scraper/jobs', [ScraperController::class, 'recentJobs']);
    Route::middleware('role:admin,rep')->group(function () {
        Route::post('/scraper/start', [ScraperController::class, 'start']);
    });

    Route::get('/sequences', [SequenceController::class, 'index']);
    Route::middleware('role:admin,rep')->group(function () {
        Route::post('/sequences', [SequenceController::class, 'store']);
        Route::patch('/sequences/{sequence}', [SequenceController::class, 'update']);
        Route::delete('/sequences/{sequence}', [SequenceController::class, 'destroy']);
    });

    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::middleware('role:admin,rep')->group(function () {
        Route::post('/campaigns', [CampaignController::class, 'store']);
        Route::post('/campaigns/{campaign}/start', [CampaignController::class, 'start']);
        Route::patch('/campaigns/{campaign}/pause', [CampaignController::class, 'pause']);
    });

    Route::get('/replies', [ReplyController::class, 'index']);
    Route::middleware('role:admin,rep')->group(function () {
        Route::post('/replies/simulate', [ReplyController::class, 'simulate']);
        Route::patch('/replies/{reply}/read', [ReplyController::class, 'markRead']);
        Route::post('/replies/{reply}/respond', [ReplyController::class, 'respond']);
    });

    Route::get('/ai/actions-log', [AiActionsLogController::class, 'index']);
    Route::middleware('role:admin,rep')->group(function () {
        Route::delete('/ai/actions/{action}/cancel', [AiActionsLogController::class, 'cancel']);
    });

    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/campaign/{campaign}', [AnalyticsController::class, 'campaign']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::middleware('role:admin,rep')->group(function () {
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::patch('/tasks/{task}', [TaskController::class, 'update']);
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    });

    Route::get('/settings/integrations', [SettingsController::class, 'integrations']);
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
    });

    Route::get('/powerbi/embed-token', [PowerBiController::class, 'embedToken']);
    Route::get('/powerbi/config', [PowerBiController::class, 'config']);
});
