@php($wf = __('page_manual.wireframe'))
<table class="pmwf-data-table" aria-hidden="true">
  <thead>
    <tr>
      <th>{{ $wf['col_sn'] ?? 'S/N' }}</th>
      <th>{{ $wf['col_name'] ?? 'Name' }}</th>
      <th>{{ $wf['col_group'] ?? 'Group' }}</th>
      <th>{{ $wf['col_status'] ?? 'Status' }}</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>1</td><td></td><td></td><td><span class="pmwf-status pmwf-status--ok"></span></td>
    </tr>
    <tr>
      <td>2</td><td></td><td></td><td><span class="pmwf-status pmwf-status--warn"></span></td>
    </tr>
    <tr>
      <td>3</td><td></td><td></td><td><span class="pmwf-status pmwf-status--danger"></span></td>
    </tr>
    <tr>
      <td>4</td><td></td><td></td><td></td>
    </tr>
  </tbody>
</table>
