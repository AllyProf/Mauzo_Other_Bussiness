<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $codeType === 'qr' ? 'QR' : ($codeType === 'barcode' ? 'Barcode' : 'QR & Barcode') }} labels — {{ $pageTitle ?? $item?->name }}</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; margin: 16px; color: #111; }
    .toolbar {
      margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;
      padding: 12px; border: 1px solid #e5e5e5; border-radius: 6px; background: #fafafa;
    }
    .toolbar .field { display: flex; flex-direction: column; gap: 4px; }
    .toolbar label { font-size: 12px; font-weight: 700; color: #444; }
    .toolbar input[type="number"], .toolbar select {
      min-width: 88px; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px;
    }
    .toolbar .checks { display: flex; gap: 12px; align-items: center; padding-bottom: 4px; flex-wrap: wrap; }
    .toolbar .checks label { font-weight: 500; display: flex; gap: 4px; align-items: center; }
    .toolbar a, .toolbar button {
      display: inline-block; padding: 8px 14px; border: 1px solid #940000; background: #940000; color: #fff;
      text-decoration: none; border-radius: 4px; cursor: pointer; font-size: 14px; height: 36px;
    }
    .toolbar a.secondary, .toolbar button.secondary { background: #fff; color: #940000; }
    .hint { color: #555; font-size: 13px; margin-bottom: 12px; }
    .sheet { display: flex; flex-wrap: wrap; gap: 10px; }
    .label {
      width: {{ (int) $labelWidth }}px;
      border: 1px dashed #999;
      padding: 10px;
      text-align: center;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .label .name { font-weight: 700; font-size: 13px; margin-bottom: 2px; word-break: break-word; }
    .label .pkg { font-size: 11px; color: #444; margin-bottom: 6px; }
    .label img.barcode-img {
      max-width: 100%;
      height: {{ (int) $height }}px;
      object-fit: contain;
    }
    .label img.qr-img {
      width: {{ (int) $qrSize }}px;
      height: {{ (int) $qrSize }}px;
      object-fit: contain;
    }
    .label .code { font-family: Consolas, monospace; font-size: 11px; margin-top: 4px; letter-spacing: 0.5px; word-break: break-all; }
    .label .price { font-size: 12px; font-weight: 700; margin-top: 4px; }
    @media print {
      .toolbar, .hint, .no-print { display: none !important; }
      body { margin: 0; }
      .label { border: 1px solid #000; }
    }
  </style>
</head>
<body>
  <form method="GET" action="{{ $formAction ?? route('items.barcodes.print', $item) }}" class="toolbar no-print">
    <input type="hidden" name="applied" value="1">
    @foreach(request()->only(['items', 'all', 'category_id', 'category_scope']) as $key => $value)
      @if($value !== null && $value !== '')
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
      @endif
    @endforeach
    <div class="field">
      <label for="code_type">Label type</label>
      <select id="code_type" name="code_type">
        <option value="qr" @selected($codeType === 'qr')>QR code</option>
        <option value="barcode" @selected($codeType === 'barcode')>Barcode</option>
        <option value="both" @selected($codeType === 'both')>QR + Barcode</option>
      </select>
    </div>
    <div class="field">
      <label for="qr_size">QR size (px)</label>
      <input id="qr_size" type="number" name="qr_size" min="80" max="280" value="{{ $qrSize }}">
    </div>
    <div class="field">
      <label for="width_factor">Bar thickness</label>
      <select id="width_factor" name="width_factor">
        @foreach([1 => 'Thin', 2 => 'Normal', 3 => 'Medium', 4 => 'Thick', 5 => 'Extra thick'] as $value => $label)
          <option value="{{ $value }}" @selected((int) $widthFactor === $value)>{{ $label }} ({{ $value }})</option>
        @endforeach
      </select>
    </div>
    <div class="field">
      <label for="height">Barcode height (px)</label>
      <input id="height" type="number" name="height" min="30" max="150" value="{{ $height }}">
    </div>
    <div class="field">
      <label for="label_width">Label width (px)</label>
      <input id="label_width" type="number" name="label_width" min="140" max="400" value="{{ $labelWidth }}">
    </div>
    <div class="field">
      <label for="copies">Copies each</label>
      <input id="copies" type="number" name="copies" min="1" max="48" value="{{ $copies }}">
    </div>
    <div class="checks">
      <label><input type="checkbox" name="show_name" value="1" @checked($showName)> Name</label>
      <label><input type="checkbox" name="show_price" value="1" @checked($showPrice)> Price</label>
      <label><input type="checkbox" name="show_code" value="1" @checked($showCode)> Code text</label>
    </div>
    <button type="submit" class="secondary">Apply</button>
    <button type="button" onclick="window.print()">Print labels</button>
    <a class="secondary" href="{{ route('items.barcodes.index') }}">QR list</a>
    @if($item)
    <a class="secondary" href="{{ route('items.show', $item) }}">Item details</a>
    @endif
  </form>

  @if(session('success'))
    <p class="hint"><strong>{{ session('success') }}</strong></p>
  @endif

  <p class="hint no-print">
    Choose QR, barcode, or both. Click <strong>Apply</strong>, then <strong>Print labels</strong>.
    Each selling packaging has its own scannable code for mobile POS.
  </p>

  <div class="sheet">
    @foreach($labels as $label)
      @for($i = 0; $i < $copies; $i++)
        <div class="label">
          @if($showName)
            <div class="name">{{ $label['item_name'] }}</div>
            <div class="pkg">{{ $label['packaging_name'] }}</div>
          @endif
          @if($label['qr_png'])
            <img class="qr-img" src="data:image/png;base64,{{ $label['qr_png'] }}" alt="{{ $label['barcode'] }}">
          @endif
          @if($label['barcode_png'])
            <img class="barcode-img" src="data:image/png;base64,{{ $label['barcode_png'] }}" alt="{{ $label['barcode'] }}">
          @endif
          @if($showCode)
            <div class="code">{{ $label['barcode'] }}</div>
          @endif
          @if($showPrice && ($label['selling_price'] ?? 0) > 0)
            <div class="price">{{ money($label['selling_price']) }}</div>
          @endif
        </div>
      @endfor
    @endforeach
  </div>

  @if($labels->isEmpty())
    <p>No selling packagings found for this item.</p>
  @endif
</body>
</html>
