@forelse($logs as $log)
  <div class="activity-mobile-card">
    <div class="activity-mobile-head">
      <div>
        <div class="activity-mobile-time">{{ $log->created_at->format('M d, Y H:i:s') }}</div>
        <div class="activity-mobile-user">
          {{ $log->user->name ?? 'System' }}
          @if($log->user)
            <span class="text-muted">· {{ $log->user->displayRoleName() }}</span>
          @endif
        </div>
      </div>
      <span class="badge {{ $log->badgeClass() }}">{{ $log->actionLabel() }}</span>
    </div>
    @if($log->description)
      <div class="activity-mobile-desc">{{ $log->description }}</div>
    @endif
    @if($log->ip_address)
      <div class="activity-mobile-ip"><i class="fa fa-globe"></i> <code>{{ $log->ip_address }}</code></div>
    @endif
  </div>
@empty
  <p class="text-center text-muted py-4 mb-0">No activity recorded yet.</p>
@endforelse
