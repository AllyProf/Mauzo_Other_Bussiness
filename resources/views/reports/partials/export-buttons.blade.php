@php
  $reportExportKey = $reportExportKey ?? (request()->routeIs('reports.*') ? str_replace('reports.', '', (string) request()->route()?->getName()) : null);
  $exportBase = $reportExportKey ? route('reports.export', ['reportKey' => $reportExportKey]) : '';
@endphp
@if($reportExportKey && $reportExportKey !== 'index' && $reportExportKey !== 'export')
<a href="{{ $exportBase }}"
   class="btn btn-sm btn-outline-danger report-export-link"
   style="border-color:#940000;color:#940000;"
   data-format="pdf">
  <i class="fa fa-file-pdf-o"></i> {{ __('reports.export.pdf') }}
</a>
<a href="{{ $exportBase }}"
   class="btn btn-sm btn-outline-success report-export-link"
   data-format="excel">
  <i class="fa fa-file-excel-o"></i> {{ __('reports.export.excel') }}
</a>
@endif
