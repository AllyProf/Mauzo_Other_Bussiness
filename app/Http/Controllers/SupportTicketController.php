<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    public function quickStore(Request $request)
    {
        $user = $this->businessUser();

        $validated = $request->validate([
            'message' => 'required|string|min:10|max:2000',
        ]);

        $message = trim($validated['message']);
        $subject = Str::limit($message, 80);
        if (strlen($subject) < 5) {
            $subject = 'Support request from '.$user->name;
        }

        $ticket = Ticket::create([
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'subject' => $subject,
            'message' => $message."\n\n---\nSubmitted by: {$user->name}\nPage: ".$request->headers->get('referer', url()->previous()),
            'status' => 'open',
        ]);

        $this->notifyAdminsOfNewTicket($ticket);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Your support request was sent. Check My Support in the sidebar for replies.',
                'ticket_id' => $ticket->id,
                'ticket_url' => route('tickets.show_tenant', $ticket->id),
            ]);
        }

        return back()->with('success', 'Your support request was sent. Check My Support in the sidebar for replies.');
    }

    public function index()
    {
        $user = $this->businessUser();
        $tickets = $this->ticketQueryFor($user)->latest()->get();

        return view('tickets.index', compact('tickets'));
    }

    public function create()
    {
        $this->businessUser();

        return view('tickets.create');
    }

    public function store(Request $request)
    {
        $user = $this->businessUser();

        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $ticket = Ticket::create([
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'open',
        ]);

        $this->notifyAdminsOfNewTicket($ticket);

        return redirect()->route('tickets.index')->with('success', 'Support ticket created successfully. The Software Owner will respond soon.');
    }

    public function show(Ticket $ticket)
    {
        $user = $this->businessUser();
        $this->authorizeTicket($ticket, $user);

        return view('tickets.show', compact('ticket'));
    }

    private function businessUser(): User
    {
        $user = Auth::user();

        if (! $user || in_array($user->role, ['super_admin', 'platform_staff'], true) || ! $user->business_id) {
            abort(403);
        }

        return $user;
    }

    private function notifyAdminsOfNewTicket(Ticket $ticket): void
    {
        try {
            app(\App\Services\PlatformMailService::class)->notifyAdminNewTicket($ticket);
        } catch (\Throwable) {
            // Non-blocking
        }

        try {
            app(\App\Services\PlatformSmsService::class)->notifyAdminNewTicket($ticket);
        } catch (\Throwable) {
            // Non-blocking
        }
    }

    private function ticketQueryFor(User $user)
    {
        $query = Ticket::query()->where('business_id', $user->business_id);

        if ($user->role !== 'owner') {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function authorizeTicket(Ticket $ticket, User $user): void
    {
        if ((int) $ticket->business_id !== (int) $user->business_id) {
            abort(403);
        }

        if ($user->role !== 'owner' && (int) $ticket->user_id !== (int) $user->id) {
            abort(403);
        }
    }
}
