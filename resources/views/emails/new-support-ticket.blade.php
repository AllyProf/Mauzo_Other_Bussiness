@php $ticket = $ticket; @endphp
<p>A new support ticket was submitted.</p>
<p><strong>Business:</strong> {{ $ticket->business?->name ?? '—' }}<br>
<strong>From:</strong> {{ $ticket->user?->name ?? '—' }}<br>
<strong>Subject:</strong> {{ $ticket->subject }}</p>
<p style="white-space:pre-wrap">{{ $ticket->message }}</p>
<p><a href="{{ url('/admin/tickets/'.$ticket->id) }}">View in admin panel</a></p>
