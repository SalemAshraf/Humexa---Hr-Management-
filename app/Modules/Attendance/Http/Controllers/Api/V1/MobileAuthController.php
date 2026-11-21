<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $user->employee()->exists();
        $token=$user->createToken($data['device_name'],['attendance:mobile'])->plainTextToken;
        return response()->json(['data'=>['token'=>$token,'token_type'=>'Bearer','user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email]]]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message'=>'Token revoked.']);
    }
}