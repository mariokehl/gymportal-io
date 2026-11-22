<?php

use App\Http\Controllers\Api\Agent\AgentReadController;
use Illuminate\Support\Facades\Route;

/*
| Agent API
|
| For AI agents and other automations acting for one gym. Authentication is a
| Bearer token (`php artisan agent:token create`), the gym comes from the
| token. Each route names the ability it needs. Loaded inside the v1 group of
| routes/api.php, so every path below lives under /api/v1/agent and every
| route name starts with v1.agent.
*/
Route::get('health', [AgentReadController::class, 'health'])->middleware('agent.auth')->name('health');

Route::get('plans', [AgentReadController::class, 'plans'])->middleware('agent.auth:plans.read')->name('plans');
Route::get('occupancy', [AgentReadController::class, 'occupancy'])->middleware('agent.auth:stats.read')->name('occupancy');
Route::get('stats', [AgentReadController::class, 'stats'])->middleware('agent.auth:stats.read')->name('stats');
Route::get('insights/inactive-members', [AgentReadController::class, 'inactiveMembers'])->middleware('agent.auth:stats.read')->name('insights.inactive-members');

Route::get('members', [AgentReadController::class, 'members'])->middleware('agent.auth:members.read')->name('members.index');
Route::get('members/lookup', [AgentReadController::class, 'lookup'])->middleware('agent.auth:members.read')->name('members.lookup');

Route::prefix('members/{memberId}')->where(['memberId' => '[0-9]+'])->name('members.')->group(function () {
    Route::get('/', [AgentReadController::class, 'showMember'])->middleware('agent.auth:members.read')->name('show');
    Route::get('contract', [AgentReadController::class, 'contract'])->middleware('agent.auth:contracts.read')->name('contract');
    Route::get('access', [AgentReadController::class, 'access'])->middleware('agent.auth:access.read')->name('access');
    Route::get('check-ins', [AgentReadController::class, 'checkIns'])->middleware('agent.auth:access.read')->name('check-ins');
    Route::get('payments', [AgentReadController::class, 'payments'])->middleware('agent.auth:payments.read')->name('payments');
});
