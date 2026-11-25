<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockUserRequest;
use App\Http\Requests\Admin\DeleteUserRequest;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function __construct(
        private UserAccountService $userAccountService,
    ) {}

    public function block(BlockUserRequest $request, User $user): RedirectResponse
    {
        $this->userAccountService->block($user, $request->validated('reason'), $request->user());

        return back()->with('success', "{$user->full_name} wurde blockiert.");
    }

    public function unblock(BlockUserRequest $request, User $user): RedirectResponse
    {
        $this->userAccountService->unblock($user, $request->user());

        return back()->with('success', "{$user->full_name} wurde entsperrt.");
    }

    public function destroy(DeleteUserRequest $request, User $user): RedirectResponse
    {
        $this->userAccountService->delete($user, $request->user());

        return back()->with('success', "{$user->full_name} und alle zugehörigen Studios wurden gelöscht.");
    }
}
