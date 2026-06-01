<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with(['business', 'user'])->latest()->get();
        return view('admin.tickets.index', compact('tickets'));
    }

    public function show(Ticket $ticket)
    {
        return view('admin.tickets.show', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $request->validate([
            'admin_reply' => 'required|string',
            'status' => 'required|in:open,pending,resolved,closed'
        ]);

        $ticket->update([
            'admin_reply' => $request->admin_reply,
            'status' => $request->status
        ]);

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket updated and reply sent.');
    }
}
