@forelse($logs as $log)
@php
  $locationLabel = $log->ipLocationLabel();
@endphp
<tr data-log-id="{{ $log->id }}">
  <td class="text-nowrap small">{{ $log->created_at->format('M d, Y') }}<br><span class="text-muted">{{ $log->created_at->format('H:i:s') }}</span></td>
  <td class="small">{{ $log->business->name ?? 'Platform' }}</td>
  <td class="small">
    {{ $log->user->name ?? 'System' }}
    @if($log->user)
    <br><span class="text-muted">{{ $log->user->displayRoleName() }}</span>
    @endif
  </td>
  <td><span class="badge {{ $log->badgeClass() }}">{{ $log->actionLabel() }}</span></td>
  <td class="small" style="max-width: 280px;">
    <span class="d-block text-truncate" title="{{ $log->description }}">{{ $log->descriptionExcerpt(70) }}</span>
    <button type="button" class="btn btn-link btn-sm p-0 audit-log-view-btn" data-log-id="{{ $log->id }}">View more</button>
  </td>
  <td class="small" style="min-width: 140px;">
    <code class="d-block">{{ $log->ip_address ?? '—' }}</code>
    <span class="text-muted d-block" title="{{ $locationLabel }}"><i class="fa fa-map-marker"></i> {{ Str::limit($locationLabel, 42) }}</span>
  </td>
</tr>
@empty
<tr id="auditLogsEmptyRow">
  <td colspan="6" class="text-center text-muted py-4">No activity found for the selected filters.</td>
</tr>
@endforelse
