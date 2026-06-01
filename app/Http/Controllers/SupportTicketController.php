<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportTicketController extends Controller
{
    public function index()
    {
        $this->authorizeAny(['manage_support']);

        $tickets = Ticket::where('business_id', Auth::user()->business_id)->latest()->get();

        return view('tickets.index', compact('tickets'));
    }

    public function create()
    {
        $this->authorizeAny(['manage_support']);

        return view('tickets.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['manage_support']);

        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        Ticket::create([
            'business_id' => Auth::user()->business_id,
            'user_id' => Auth::id(),
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'open',
        ]);

        return redirect()->route('tickets.index')->with('success', 'Support ticket created successfully. The Software Owner will respond soon.');
    }

    public function show(Ticket $ticket)
    {
        $this->authorizeAny(['manage_support']);

        if ($ticket->business_id != Auth::user()->business_id) {
            abort(403);
        }

        return view('tickets.show', compact('ticket'));
    }
}
