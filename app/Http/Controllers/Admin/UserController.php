<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeCredentialsMail;
use App\Models\User;
use App\Models\UserSession;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderByRole($request, null, 'All Users');
    }

    public function admins(Request $request)
    {
        return $this->renderByRole($request, 'admin', 'Admin Users');
    }

    public function managers(Request $request)
    {
        return $this->renderByRole($request, 'manager', 'Managers');
    }

    public function telecallers(Request $request)
    {
        return $this->renderByRole($request, 'telecaller', 'Telecallers');
    }


    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email:rfc,dns|unique:users,email',
            'phone'    => ['required', 'digits:10'],
            'role'     => 'required|in:manager,telecaller',
            'password' => ['required', 'min:8', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/', 'regex:/[@$!%*#?&^_\-]/'],
        ], [
            'phone.required'      => 'Phone number is required.',
            'phone.digits'        => 'Phone number must be exactly 10 digits.',
            'password.min'        => 'Password must be at least 8 characters.',
            'password.regex'      => 'Password must contain at least 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character (@$!%*#?&^_-).',
        ]);

        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => '+91' . $request->phone,
            'role'     => $request->role,
            'status'   => 1,
            'password' => Hash::make($request->password),
        ]);

        try {
            Mail::to($request->email)->send(new WelcomeCredentialsMail(
                userName: $request->name,
                userEmail: $request->email,
                plainPassword: $request->password,
                role: $request->role,
                loginUrl: route('login'),
            ));
        } catch (\Throwable $e) {
            Log::error('Welcome email failed for ' . $request->email . ': ' . $e->getMessage());
        }

        return redirect()->route('admin.users')
            ->with('success', 'User Created Successfully. Login credentials have been sent to ' . $request->email . '.');
    }
    public function edit($id)
    {
        $decryptedId = decrypt($id);

        $user = User::findOrFail($decryptedId);

        return view('admin.users.edit', compact('user', 'id'));
    }
    public function update(Request $request, $id)
    {
        $decryptedId = decrypt($id);

        $user = User::findOrFail($decryptedId);

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role'  => 'required|in:manager,telecaller',
            'status' => 'required|in:0,1',
        ]);

        $old = $user->only(['name', 'email', 'phone', 'role', 'status']);

        $user->name   = $request->name;
        $user->email  = $request->email;
        $user->phone  = $request->phone;
        $user->role   = $request->role;
        $user->status = $request->status;

        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        $user->save();

        AuditLogService::log('user.updated', 'User', $user->id, $old, $user->only(['name', 'email', 'phone', 'role', 'status']));

        return redirect()->route('admin.users')
            ->with('success', 'User Updated Successfully');
    }
    public function toggleStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail((int) $request->id);

        $oldStatus = $user->status;
        $user->status = !$user->status;
        if ((int) $user->status === 0 && Schema::hasColumn('users', 'is_online')) {
            $user->is_online = false;
        }
        $user->save();

        AuditLogService::log('user.status_changed', 'User', $user->id, ['status' => $oldStatus], ['status' => $user->status]);

        return response()->json([
            'status' => (bool) $user->status
        ]);
    }

    public function forceLogout(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail((int) $request->id);

        if (Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at')) {
            $user->is_online = false;
            $user->last_seen_at = now();
        }
        $user->save();

        UserSession::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->latest('id')
            ->get()
            ->each(function ($session) {
                $logoutTime = Carbon::now('Asia/Kolkata');
                try {
                    $loginAt  = Carbon::createFromFormat('Y-m-d H:i:s', $session->login_at, 'Asia/Kolkata');
                    $duration = max(0, (int) $loginAt->diffInMinutes($logoutTime));
                } catch (\Throwable) {
                    $duration = 0;
                }
                $session->update([
                    'logout_at'        => $logoutTime,
                    'duration_minutes' => $duration,
                ]);
            });

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        AuditLogService::log('user.force_logout', 'User', $user->id);

        return response()->json(['ok' => true]);
    }

    public function unlockAccount(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail((int) $request->id);
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        AuditLogService::log('user.account_unlocked', 'User', $user->id);

        return response()->json(['ok' => true]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:users,id',
            'password' => 'required|string|min:6|max:64',
        ]);

        $user = User::findOrFail((int) $request->id);
        $user->password = Hash::make((string) $request->password);
        $user->save();

        return response()->json(['ok' => true]);
    }

    public function presenceSnapshot(Request $request)
    {
        if (!Schema::hasColumn('users', 'is_online') || !Schema::hasColumn('users', 'last_seen_at')) {
            return response()->json(['presence' => []]);
        }

        $presence = User::whereIn('role', ['manager', 'telecaller'])
            ->get(['id', 'is_online', 'last_seen_at'])
            ->mapWithKeys(function ($user) {
                $isOnline = (bool) $user->is_online
                    && $user->last_seen_at
                    && $user->last_seen_at->gte(now()->subSeconds(60));
                return [$user->id => $isOnline ? 'online' : 'offline'];
            });

        return response()->json(['presence' => $presence]);
    }

    private function renderByRole(Request $request, ?string $role, string $title)
    {
        $query = User::query();

        if ($role) {
            $query->where('role', $role);
        }

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        if ($request->status !== null && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('id', 'desc')->paginate(12)->withQueryString();

        $withPresence = Schema::hasColumn('users', 'is_online') && Schema::hasColumn('users', 'last_seen_at');
        $users->getCollection()->transform(function ($user) use ($withPresence) {
            $isOnline = false;
            if ($withPresence) {
                $isOnline = (bool) $user->is_online;
                if ($user->last_seen_at && $user->last_seen_at < now()->subSeconds(60)) {
                    $isOnline = false;
                }
            }
            $user->presence_state = $isOnline ? 'online' : 'offline';
            return $user;
        });

        $counts = [
            'admins' => User::where('role', 'admin')->count(),
            'managers' => User::where('role', 'manager')->count(),
            'telecallers' => User::where('role', 'telecaller')->count(),
            'active' => User::where('status', 1)->count(),
        ];

        return view('admin.users.index', [
            'users' => $users,
            'title' => $title,
            'scope' => $role ?: 'all',
            'counts' => $counts,
        ]);
    }
}
