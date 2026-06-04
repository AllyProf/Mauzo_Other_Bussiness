<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\Ticket;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index()
    {
        $this->ensurePlatformAdmin('tickets');

        $tickets = Ticket::with(['business', 'user'])->latest()->get();

        return view('admin.tickets.index', compact('tickets'));
    }

    public function show(Ticket $ticket)
    {
        $this->ensurePlatformAdmin('tickets');

        if (! $ticket->admin_read_at) {
            $ticket->update(['admin_read_at' => now()]);
        }

        $ticket->load(['business', 'user']);

        return view('admin.tickets.show', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $this->ensurePlatformAdmin('tickets');

        $request->validate([
            'admin_reply' => 'required|string',
            'status' => 'required|in:open,pending,resolved,closed',
        ]);

        $ticket->update([
            'admin_reply' => $request->admin_reply,
            'status' => $request->status,
            'admin_read_at' => now(),
        ]);

        try {
            app(\App\Services\PlatformSmsService::class)->notifyBusinessTicketReply($ticket->fresh(['business']));
            app(\App\Services\PlatformMailService::class)->notifyBusinessTicketReply($ticket->fresh(['business']));
        } catch (\Throwable) {
            // Non-blocking
        }

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket updated and reply sent.');
    }
}
