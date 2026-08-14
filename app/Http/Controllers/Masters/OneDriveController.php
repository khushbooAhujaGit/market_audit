<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Services\OneDrivePersonalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OneDriveController extends Controller
{
    /**
     * Sends the user to Microsoft's own login/consent screen. We never see their
     * password — Microsoft redirects back to callback() with a one-time code once
     * they've signed in and approved the Files.ReadWrite permission.
     */
    public function connect(Request $request, OneDrivePersonalService $oneDrive)
    {
        $state = Str::random(40);
        session(['onedrive_oauth_state' => $state]);

        return redirect($oneDrive->getAuthorizeUrl($state));
    }

    public function callback(Request $request, OneDrivePersonalService $oneDrive)
    {
        $expectedState = session()->pull('onedrive_oauth_state');
        if (!$request->filled('code') || !$request->filled('state') || $request->state !== $expectedState) {
            return redirect()->route('infiltration.index')
                ->withErrors(['onedrive' => 'OneDrive connection failed or was cancelled. Please try again.']);
        }

        try {
            $oneDrive->handleCallback($request->code, Auth::user());
        } catch (\Throwable $e) {
            return redirect()->route('infiltration.index')
                ->withErrors(['onedrive' => 'Could not connect OneDrive: ' . $e->getMessage()]);
        }

        return redirect()->route('infiltration.index')->with('message', 'OneDrive connected successfully.');
    }

    public function disconnect(OneDrivePersonalService $oneDrive)
    {
        $oneDrive->disconnect(Auth::user());
        return redirect()->route('infiltration.index')->with('message', 'OneDrive disconnected.');
    }
}
