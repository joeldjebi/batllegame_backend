<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every account of the platform: back-office members and mobile users.
 */
class UserController extends Controller
{
    public const array TYPES = ['backoffice' => 'Back-office', 'mobile' => 'Application mobile', 'admin' => 'Super-admin'];

    public function index(Request $request): View
    {
        $type = $request->query('type');

        return view('admin.users.index', [
            'users' => User::query()
                ->with(['country', 'roles'])
                ->withCount(['organizerMemberships', 'participations', 'judgeAssignments', 'publicVotes'])
                ->when($type === 'backoffice', fn ($q) => $q->whereHas('organizerMemberships'))
                ->when($type === 'mobile', fn ($q) => $q->whereDoesntHave('organizerMemberships')->whereDoesntHave('roles'))
                ->when($type === 'admin', fn ($q) => $q->role(PlatformRole::Admin->value))
                ->when($request->query('q'), fn ($q, $search) => $q->where(fn ($q) => $q
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%")))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'type' => $type,
            'total' => User::query()->count(),
        ]);
    }

    public function show(User $user): View
    {
        $user->load([
            'country',
            'roles',
            'organizerMemberships.organizer',
            'participations' => fn ($q) => $q->with('competition.organizer')->latest(),
            'judgeAssignments' => fn ($q) => $q->with('competition.organizer')->latest(),
        ]);

        return view('admin.users.show', [
            'user' => $user,
            'votes' => $user->publicVotes()->count(),
            'recentVotes' => $user->publicVotes()->with(['participant', 'competition'])->latest()->limit(8)->get(),
            'devices' => $user->publicVotes()->whereNotNull('device_id')->distinct('device_id')->count('device_id'),
            'tokens' => $user->tokens()->count(),
        ]);
    }
}
