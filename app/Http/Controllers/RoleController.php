<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(){
        $roles = Role::latest()->paginate(10);
        return view('role-permission.role.index', compact('roles'));

    }

    public function create(){
    return view('role-permission.role.create');

}
    public function store(Request $request){
        $request->validate([
            'name' => [
                'required',
                'string',
                'unique:roles,name'
            ],
        ]);
        Role::create([
            'name' => $request->name
        ]);
        return redirect('roles')->with('message', 'Role created successfully');

    }
    public function edit(Role $role){
        return view('role-permission.role.edit', compact('role'));
    }

    public function update(Request $request, Role $role){
        $request->validate([
            'name' => [
                'required',
                'string',
                'unique:roles,name,'. $role->id
            ],
        ]);
        $role->update([
            'name' => $request->name
        ]);

        return redirect('roles')->with('message', 'Role Updated successfully');

    }

    public function destroy(Request $request){
        $role = Role::find($request->id);
        $role->delete();
        return "Success";
    }

    public function view_addPermissionToRole($roleId){
        $role = Role::findorFail($roleId);
        $permissions = Permission::all();
        $rolePermissions = DB::table('role_has_permissions')
            ->where('role_has_permissions.role_id', $roleId)
            ->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id');
        return view('role-permission.role.addPermission', compact('role', 'permissions', 'rolePermissions'));
    }
    public function addPermissionToRole(Request $request, $roleId){

        $request->validate([
            'permission' => 'required',
        ]);
        $role = Role::findOrFail($roleId);
        $role->syncPermissions($request->permission);
       return redirect()->back()->with('message', "Permissions added to role");
    }
}
