<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Integrations\IntegrationCallbackController;
use App\Http\Controllers\Integrations\IntegrationController;
use App\Http\Controllers\Knowledge\KnowledgePageController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::inertia('agents', 'agents/index')->name('agents.index');

        Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::post('integrations', [IntegrationController::class, 'store'])->name('integrations.store');
        Route::get('integrations/callback', IntegrationCallbackController::class)->name('integrations.callback');
        Route::delete('integrations/{integration}', [IntegrationController::class, 'destroy'])
            ->scopeBindings()
            ->name('integrations.destroy');

        Route::get('knowledge', [KnowledgePageController::class, 'index'])->name('knowledge.index');
        Route::post('knowledge', [KnowledgePageController::class, 'store'])->name('knowledge.store');
        Route::get('knowledge/{knowledge_page}', [KnowledgePageController::class, 'show'])
            ->scopeBindings()
            ->name('knowledge.show');
        Route::patch('knowledge/{knowledge_page}', [KnowledgePageController::class, 'update'])
            ->scopeBindings()
            ->name('knowledge.update');
        Route::delete('knowledge/{knowledge_page}', [KnowledgePageController::class, 'destroy'])
            ->scopeBindings()
            ->name('knowledge.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
