<?php

namespace App\Http\Controllers;

use App\Models\SupportRequest;
use App\Services\OutlookMailerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Support page: anyone can send a message (no login). It is saved for the team to read on
 * Support Requests (Super Admin), and the sender gets a thank-you mail. Nothing is mailed to the team.
 */
class SupportRequestController extends Controller
{
    public function show()
    {
        return view('support.index');
    }

    public function store(Request $request)
    {
        // "website" is a hidden field people never see: only bots fill it in
        if (filled($request->input('website'))) {
            return redirect()->route('support.show')->with('support_sent', ['name' => '', 'email' => '', 'id' => null]);
        }

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:150'],
            'phone'   => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'phone.regex' => 'Enter a phone number using digits only (a leading + is fine).',
            'message.min' => 'Please describe the problem in a few words (at least 10 characters).',
        ]);

        $supportRequest = SupportRequest::create($data + [
            'status'     => 'open',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        // Thank-you mail once the page has been sent, so a slow mail server never delays the sender
        $id = $supportRequest->id;
        dispatch(function () use ($id) {
            $supportRequest = SupportRequest::find($id);
            if (!$supportRequest) return;
            try {
                (new OutlookMailerService())->sendMail(
                    $supportRequest->email,
                    'We received your message (Ref #' . $supportRequest->id . ')',
                    view('support.thank_you_mail', ['supportRequest' => $supportRequest])->render()
                );
                $supportRequest->update(['thank_you_sent_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning("Support request {$supportRequest->id}: thank-you mail not sent: " . $e->getMessage());
            }
        })->afterResponse();

        return redirect()->route('support.show')->with('support_sent', [
            'name' => $supportRequest->name, 'email' => $supportRequest->email, 'id' => $supportRequest->id,
        ]);
    }

    /** Super Admin: the messages, open first. */
    public function index(Request $request)
    {
        $status = in_array($request->status, ['open', 'resolved'], true) ? $request->status : null;
        $search = trim((string) $request->input('search', ''));

        $requests = SupportRequest::with('resolver')
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $q->where(fn($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)->orWhere('message', 'like', $like));
            })
            ->orderByRaw("FIELD(status, 'open', 'resolved')")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = SupportRequest::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');

        return view('masters.support.requests', compact('requests', 'counts', 'status', 'search'));
    }

    /** Super Admin: mark a message resolved, or open it again. */
    public function updateStatus(Request $request, $supportRequest)
    {
        $request->validate(['status' => ['required', 'in:open,resolved']]);
        $item = SupportRequest::findOrFail((int) $supportRequest);
        $item->update($request->status === 'resolved'
            ? ['status' => 'resolved', 'resolved_by' => Auth::id(), 'resolved_at' => now()]
            : ['status' => 'open', 'resolved_by' => null, 'resolved_at' => null]);

        return redirect()->back()->with('message', $request->status === 'resolved'
            ? "Request #{$item->id} marked as resolved."
            : "Request #{$item->id} opened again.");
    }
}
