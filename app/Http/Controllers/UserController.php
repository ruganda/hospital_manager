<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Admin-only management of every login account. Doctors & patients are created from their own modules. */
class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->latest()->limit($this->perPage())->get();

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['is_active' => true]), 'roles' => Role::staff()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active');
        $data['email_verified_at'] = now();

        User::create($data);

        return redirect()->route('users.index')->with('success', 'User account created.');
    }

    public function show(User $user): RedirectResponse
    {
        return match ($user->role) {
            Role::Doctor => redirect()->route('doctors.show', $user->doctor),
            Role::Patient => redirect()->route('patients.show', $user->patient),
            default => redirect()->route('users.edit', $user),
        };
    }

    public function edit(User $user): View|RedirectResponse
    {
        if (in_array($user->role, [Role::Doctor, Role::Patient], true)) {
            return $this->show($user);
        }

        return view('users.form', ['user' => $user, 'roles' => Role::staff()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate($this->rules($user));
        $data['is_active'] = $request->boolean('is_active');

        if ($user->is($request->user())) {
            // never lock yourself out
            $data['is_active'] = true;
            $data['role'] = Role::Admin->value;
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User account updated.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot deactivate your own account.');
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->name.' is now '.($user->is_active ? 'active' : 'inactive').'.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
        }
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_map(fn ($r) => $r->value, Role::staff()))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }
}
