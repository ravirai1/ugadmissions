<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Department;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Super admin: staff accounts by role and department. Super admins themselves are created only from the command line.
 */
class StaffController extends Controller
{
    /** Roles that work on one department's applicants. */
    public const DEPARTMENT_ROLES = [Roles::DEPARTMENT, Roles::INTERVIEW_PANEL];

    public function index(Request $request)
    {
        $q = Admin::with('department')->orderBy('role')->orderBy('name');
        if ($role = $request->query('role')) {
            $q->where('role', $role);
        }
        if ($dept = $request->query('department')) {
            $q->where('department_id', $dept);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('email', $term));
        }

        return view('admin.staff.index', [
            'staff' => $q->paginate(30)->withQueryString(),
            'roles' => Roles::labels(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.staff.form', ['staff' => new Admin(['is_active' => true]), 'roles' => $this->assignable(), 'departments' => Department::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $password = $this->tempPassword();

        $staff = Admin::create($data + [
            'password' => $password,
            'must_change_password' => true,
            'created_by' => $request->user('admin')->id,
        ]);
        AuditLog::record('staff.create', $staff, ['role' => $staff->role, 'department_id' => $staff->department_id]);

        return redirect()->route('admin.staff.index')
            ->with('status', "Account created for {$staff->email}.")
            ->with('temp_password', ['email' => $staff->email, 'password' => $password]);
    }

    public function edit(Admin $staff)
    {
        abort_if($staff->isSuperAdmin(), 403, 'Super admin accounts are managed from the command line.');

        return view('admin.staff.form', ['staff' => $staff, 'roles' => $this->assignable(), 'departments' => Department::orderBy('name')->get()]);
    }

    public function update(Request $request, Admin $staff)
    {
        abort_if($staff->isSuperAdmin(), 403, 'Super admin accounts are managed from the command line.');
        $before = $staff->only('role', 'department_id', 'is_active');
        $staff->update($this->validated($request, $staff));
        AuditLog::record('staff.update', $staff, ['before' => $before, 'after' => $staff->only('role', 'department_id', 'is_active')]);

        return redirect()->route('admin.staff.index')->with('status', "Saved {$staff->email}.");
    }

    public function resetPassword(Request $request, Admin $staff)
    {
        abort_if($staff->isSuperAdmin(), 403);
        $password = $this->tempPassword();
        $staff->forceFill(['password' => $password, 'must_change_password' => true, 'otp_hash' => null, 'remember_token' => null])->save();
        AuditLog::record('staff.reset_password', $staff);

        return back()->with('status', "Password reset for {$staff->email}.")
            ->with('temp_password', ['email' => $staff->email, 'password' => $password]);
    }

    public function toggle(Request $request, Admin $staff)
    {
        abort_if($staff->isSuperAdmin() || $staff->is($request->user('admin')), 403);
        $staff->forceFill(['is_active' => ! $staff->is_active, 'remember_token' => null])->save();
        AuditLog::record($staff->is_active ? 'staff.activate' : 'staff.deactivate', $staff);

        return back()->with('status', ($staff->is_active ? 'Activated ' : 'Deactivated ').$staff->email.'.');
    }

    /* ---- departments ---- */

    public function departments()
    {
        return view('admin.staff.departments', ['departments' => Department::withCount(['admins', 'courses'])->orderBy('name')->get()]);
    }

    public function storeDepartment(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:departments,name'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:departments,code'],
        ]);
        $d = Department::create($data + ['is_active' => true]);
        AuditLog::record('department.create', $d);

        return back()->with('status', "Department {$d->name} added.");
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('departments', 'name')->ignore($department->id)],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('departments', 'code')->ignore($department->id)],
            'is_active' => ['boolean'],
        ]);
        $department->update($data + ['is_active' => $request->boolean('is_active')]);
        AuditLog::record('department.update', $department);

        return back()->with('status', "Department {$department->name} saved.");
    }

    /* ---- helpers ---- */

    private function assignable(): array
    {
        return array_intersect_key(Roles::labels(), array_flip(Roles::assignable()));
    }

    private function validated(Request $request, ?Admin $staff = null): array
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('admins', 'email')->ignore($staff?->id)],
            'mobile' => ['nullable', 'digits:10'],
            'role' => ['required', Rule::in(Roles::assignable())],
            'department_id' => [Rule::requiredIf(in_array($request->input('role'), self::DEPARTMENT_ROLES, true)), 'nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
        ], ['department_id.required' => 'Choose the department for this role.']);

        $data['email'] = Str::lower($data['email']);
        if ($staff && $staff->is($request->user('admin'))) {
            unset($data['role'], $data['is_active']); // cannot change own role or lock yourself out
        }

        return $data;
    }

    /** Readable temporary password that satisfies the strong-password rule; the user must change it at first login. */
    private function tempPassword(): string
    {
        return 'Ssc@'.random_int(1000, 9999).Str::upper(Str::random(3)).Str::lower(Str::random(3)).random_int(10, 99);
    }
}
