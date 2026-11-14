<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

use Illuminate\Support\Facades\Route;
use SpaceCatNinja\AiImageEditor\Http\Controllers\EditorConfigController;
use SpaceCatNinja\AiImageEditor\Http\Controllers\SessionsController;

/*
 * The editor's JSON endpoints. The control panel route group already requires a logged-in user with control panel
 * access, and the controller checks the edit and generate permissions per session.
 */
Route::prefix('ai-image-editor')->name('ai-image-editor.')->group(function(): void {
    Route::get('editor-config', EditorConfigController::class)->name('editor-config');

    Route::controller(SessionsController::class)->prefix('sessions')->name('sessions.')->group(function(): void {
        Route::post('create', 'create')->name('create');
        Route::post('turn', 'turn')->name('turn');
        Route::get('get', 'get')->name('get');
        Route::post('revert', 'revert')->name('revert');
        Route::post('finalize', 'finalize')->name('finalize');
        Route::post('confirm', 'confirm')->name('confirm');
        Route::post('discard', 'discard')->name('discard');
        Route::get('turn-image', 'turnImage')->name('turn-image');
    });
});
