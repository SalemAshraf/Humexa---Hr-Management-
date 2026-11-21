<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        abort_unless(tenancy()->initialized,404);
        $data=$request->validate(['email'=>'required|email','password'=>'required|string','device_name'=>'required|string|max:120']);
        $user=User::query()->where('tenant_id',tenant('id'))->where('account_type',User::ACCOUNT_TYPE_TENANT)->where('email',$data['email'])->first();
        if(!$user || !Hash::check($data['password'],$user->password)) throw ValidationException::withMessages(['credentials'=>'The supplied credentials are invalid.']);
        abort_if($user->disabled_at !== null,403,'This account is deactivated.');
        abort_if($user->must_change_password,403,'Sign in on the web once to choose your own password first.');
        abort_unless(Employee::query()->where('tenant_id',tenant('id'))->where('user_id',$user->id)->exists(),403,'No employee profile is linked to this account.');
        $token=$user->createToken($data['device_name'],['attendance:mobile'])->plainTextToken;
        return response()->json(['data'=>['token'=>$token,'token_type'=>'Bearer','user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email]]]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message'=>'Token revoked.']);
    }
}