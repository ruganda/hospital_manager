<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::query()
            ->withCount(['doctors', 'appointments'])
            ->orderBy('name')
            ->limit($this->perPage())
            ->get();

        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('departments.form', ['department' => new Department(['icon' => 'fa-hospital'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Department::create($request->validate($this->rules()));

        return redirect()->route('departments.index')->with('success', 'Department created.');
    }

    public function show(Department $department): View
    {
        $department->load('doctors.user', 'doctors.schedules')->loadCount('appointments');

        return view('departments.show', compact('department'));
    }

    public function edit(Department $department): View
    {
        return view('departments.form', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($request->validate($this->rules($department)));

        return redirect()->route('departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->doctors()->exists()) {
            return back()->with('error', 'This department still has doctors assigned. Move them first.');
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted.');
    }

    private function rules(?Department $department = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('departments')->ignore($department?->id)],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^fa-[a-z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
