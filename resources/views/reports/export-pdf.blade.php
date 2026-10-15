<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $export['title'] }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #212529; }
    h1 { font-size: 16px; margin: 0 0 4px; color: #940000; }
    .meta { margin-bottom: 14px; color: #495057; line-height: 1.45; }
    h2 { font-size: 12px; margin: 16px 0 6px; color: #343a40; text-transform: uppercase; letter-spacing: 0.03em; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #ced4da; padding: 5px 7px; vertical-align: top; }
    th { background: #940000; color: #fff; font-size: 10px; text-transform: uppercase; }
    td.num, th.num { text-align: right; white-space: nowrap; }
  </style>
</head>
<body>
  <h1>{{ $export['title'] }}</h1>
  <div class="meta">
    <strong>{{ $export['business_name'] }}</strong><br>
    {{ $export['period_label'] }}<br>
    {{ __('reports.export.generated_by', ['name' => $export['generated_by'], 'at' => $export['generated_at']]) }}
  </div>

  @foreach($export['sections'] as $section)
    <h2>{{ $section['title'] }}</h2>
    <table>
      <thead>
        <tr>
          @foreach($section['headers'] as $index => $header)
            <th class="{{ $index > 0 ? 'num' : '' }}">{{ $header }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse($section['rows'] as $row)
          <tr>
            @foreach($row as $index => $cell)
              <td class="{{ $index > 0 ? 'num' : '' }}">{{ is_scalar($cell) ? $cell : '' }}</td>
            @endforeach
          </tr>
        @empty
          <tr>
            <td colspan="{{ max(count($section['headers']), 1) }}" style="text-align:center;color:#6c757d;">—</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  @endforeach
</body>
</html>
