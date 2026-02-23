<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class CustomLoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10|numeric', // Validates mobile number
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }

        // Attempt login using mobile number
        $credentials = ['mobile' => $request->mobile, 'password' => $request->password];

        if (Auth::attempt($credentials, $request->has('remember_me'))) {
            // Login successful, redirect to intended page
//            dd('fghgfj');
            $request->session()->regenerate();
            $user = Auth::user();
            // 🔁 Role-based redirect
            if ($user->hasRole('Auditor')) {
                return redirect()->route('user.projects'); // 👈 auditor route
            }

            return redirect()->intended('dashboard'); // Or your desired redirect route
        }

        return back()->withErrors(['mobile' => 'Invalid mobile number or password']);
    }
}
