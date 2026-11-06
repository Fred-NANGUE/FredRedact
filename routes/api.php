<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatConversationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportSectionController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\CheckTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/google', [AuthController::class, 'google'])->middleware('throttle:10,1');
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::apiResource('internships', InternshipController::class);
    Route::post('/internships/{internship}/reports', [ReportController::class, 'createFromInternship']);
    Route::apiResource('reports', ReportController::class);
    Route::get('/reports/{report}/sections', [ReportSectionController::class, 'index']);
    Route::post('/reports/{report}/sections', [ReportSectionController::class, 'store']);
    Route::put('/reports/{report}/sections/{section}', [ReportSectionController::class, 'update']);
    Route::delete('/reports/{report}/sections/{section}', [ReportSectionController::class, 'destroy']);
    Route::get('/reports/{report}/sections/{section}/versions', [ReportSectionController::class, 'versions']);
    Route::post('/reports/{report}/sections/{section}/versions/{version}/restore', [ReportSectionController::class, 'restore']);

    Route::middleware(CheckTokens::class)->prefix('ai')->group(function () {
        Route::post('/chat', [AIController::class, 'chat']);
        Route::post('/plan', [AIController::class, 'plan']);
        Route::post('/generate', [AIController::class, 'generate']);
        Route::post('/humanize', [AIController::class, 'humanize']);
        Route::post('/research', [AIController::class, 'research']);
    });

    Route::get('/ai/conversations', [ChatConversationController::class, 'index']);
    Route::post('/ai/conversations', [ChatConversationController::class, 'store']);
    Route::put('/ai/conversations/{conversation}', [ChatConversationController::class, 'update']);
    Route::delete('/ai/conversations/{conversation}', [ChatConversationController::class, 'destroy']);
    Route::get('/ai/documents', [ChatConversationController::class, 'documents']);
    Route::get('/ai/chat', [AIController::class, 'history']);
    Route::get('/reports/{report}/webographies', [AIController::class, 'webographies']);
    Route::delete('/reports/{report}/webographies/{webography}', [AIController::class, 'deleteWebography']);

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
    Route::post('/reports/{report}/export', [ExportController::class, 'export']);

    Route::middleware(AdminOnly::class)->prefix('admin')->group(function () {
        Route::apiResource('users', AdminUserController::class)->only(['index', 'show', 'update', 'destroy']);
    });
});

require __DIR__.'/billing.php';
