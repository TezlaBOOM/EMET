<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Lista użytkowników (CRUD)
     */
    public function index(): View
    {
        $users = User::with('roles')->latest()->paginate(15);
        $roles = Role::all();

        return view('module-users::index', compact('users', 'roles'));
    }

    /**
     * Zapis nowego użytkownika
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'theme' => ['nullable', 'string', 'in:light,dark,system'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'theme' => $validated['theme'] ?? 'system',
            'setup_completed' => true,
        ]);

        $user->assignRole($validated['role']);

        \App\Models\AuditLog::record('user.created', 'User', (string) $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $validated['role'],
        ]);

        return back()->with('success', __('users.user_created'));
    }

    /**
     * Aktualizacja użytkownika
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'theme' => ['nullable', 'string', 'in:light,dark,system'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        if (isset($validated['theme'])) {
            $user->theme = $validated['theme'];
        }
        $user->save();

        $user->syncRoles([$validated['role']]);

        \App\Models\AuditLog::record('user.updated', 'User', (string) $user->id, [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return back()->with('success', __('users.user_updated'));
    }

    /**
     * Usunięcie użytkownika
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', __('users.cannot_delete_self'));
        }

        $id = (string) $user->id;
        $name = $user->name;
        $email = $user->email;

        $user->delete();

        \App\Models\AuditLog::record('user.deleted', 'User', $id, [
            'name' => $name,
            'email' => $email,
        ]);

        return back()->with('success', __('users.user_deleted'));
    }

    /**
     * Podgląd ról i uprawnień
     */
    public function roles(): View
    {
        $roles = Role::with('permissions')->get();

        return view('module-users::roles', compact('roles'));
    }

    /**
     * Widok własnego profilu użytkownika
     */
    public function profile(): View
    {
        $user = Auth::user();

        return view('module-users::profile', compact('user'));
    }

    /**
     * Aktualizacja własnego profilu (imię, hasło opcjonalne, motyw)
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'theme' => ['required', 'string', 'in:light,dark,system'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->theme = $validated['theme'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('success', __('users.saved_success'));
    }

    /**
     * Widok ustawień bezpieczeństwa
     */
    public function security(): View
    {
        return view('module-users::security');
    }

    /**
     * Panel wersji i aktualizacji systemu
     */
    public function system(): View
    {
        $versionFile = base_path('VERSION');
        $currentVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';

        $backups = [];
        $backupDir = storage_path('backups');
        if (is_dir($backupDir)) {
            $dirs = glob($backupDir . '/*', GLOB_ONLYDIR);
            if ($dirs) {
                rsort($dirs);
                foreach ($dirs as $d) {
                    $backups[] = [
                        'name' => basename($d),
                        'path' => $d,
                        'created_at' => date('Y-m-d H:i:s', filemtime($d)),
                    ];
                }
            }
        }

        return view('module-users::system', [
            'currentVersion' => $currentVersion,
            'channel' => config('compat.channels.stable', 'stable'),
            'backups' => $backups,
        ]);
    }

    /**
     * Uruchomienie testu zgodności compat-check z poziomu panelu
     */
    public function runCompatCheck(): RedirectResponse
    {
        $scriptPath = base_path('scripts/compat-check.sh');
        $output = [];
        $exitCode = 0;

        if (file_exists($scriptPath)) {
            exec("bash {$scriptPath} 2>&1", $output, $exitCode);
        }

        $message = implode("\n", $output);

        if ($exitCode === 0) {
            return back()->with('success', __('users.compat_check_success') . "\n" . $message);
        }

        return back()->with('error', __('users.compat_check_failed') . "\n" . $message);
    }
}
