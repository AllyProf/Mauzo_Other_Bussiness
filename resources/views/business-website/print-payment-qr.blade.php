<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shop website QR — {{ $merchantName }}</title>
  <style>
    :root {
      --brand: #940000;
      --cream: #f4efe6;
      --ink: #1a1212;
    }
    * { box-sizing: border-box; }
    html, body {
      margin: 0;
      padding: 0;
      background: #ddd;
      font-family: "Segoe UI", Arial, Helvetica, sans-serif;
      color: var(--ink);
    }
    .toolbar {
      position: sticky;
      top: 0;
      z-index: 20;
      display: flex;
      gap: 10px;
      justify-content: center;
      flex-wrap: wrap;
      align-items: center;
      padding: 12px 14px;
      background: #1a1212;
      color: #fff;
    }
    .toolbar a, .toolbar button {
      appearance: none;
      border: 0;
      border-radius: 8px;
      padding: 10px 16px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      color: #fff;
      background: #444;
    }
    .toolbar .print-btn { background: var(--brand); }
    .toolbar__tip {
      width: 100%;
      text-align: center;
      font-size: 12px;
      color: #aaa;
      margin-top: 2px;
    }
    .hint {
      max-width: 420px;
      margin: 14px auto 0;
      text-align: center;
      color: #666;
      font-size: 13px;
      padding: 0 12px;
    }
    .stage {
      padding: 22px 12px 48px;
      display: flex;
      justify-content: center;
    }

    .standee {
      width: 420px;
      max-width: 100%;
      height: 700px;
      background: var(--brand);
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 18px 40px rgba(0,0,0,.28);
      display: flex;
      flex-direction: column;
    }

    .standee__top {
      flex: 1 1 auto;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 22px 28px 28px;
      background: var(--brand);
      position: relative;
    }
    .standee__top::before {
      content: "";
      position: absolute;
      inset: 18px;
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 6px;
      pointer-events: none;
    }
    .standee__header {
      position: relative;
      z-index: 1;
      margin: 0 0 18px;
      color: #fff;
      font-size: 22px;
      font-weight: 800;
      letter-spacing: .04em;
      text-align: center;
      line-height: 1;
    }
    .standee__header span {
      color: rgba(255,255,255,.72);
      font-weight: 600;
    }
    .standee__circle-wrap {
      flex: 1 1 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      position: relative;
      z-index: 1;
    }

    .standee__circle {
      width: min(310px, 82%);
      aspect-ratio: 1;
      border-radius: 50%;
      background:
        radial-gradient(circle at 50% 42%, #fffdf8 0%, var(--cream) 72%, #ebe4d8 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 34px 28px 30px;
      text-align: center;
      position: relative;
      z-index: 1;
      box-shadow:
        0 0 0 10px rgba(255,255,255,.08),
        0 0 0 18px rgba(255,255,255,.04),
        0 18px 40px rgba(0,0,0,.28);
    }
    .standee__circle::before {
      content: "";
      position: absolute;
      inset: 10px;
      border-radius: 50%;
      border: 1px dashed rgba(148,0,0,.18);
      pointer-events: none;
    }

    .standee__shop {
      margin: 0 0 16px;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: .16em;
      text-transform: uppercase;
      color: var(--ink);
      line-height: 1.35;
      max-width: 86%;
      position: relative;
      z-index: 1;
    }
    .standee__shop::after {
      content: "";
      display: block;
      width: 36px;
      height: 2px;
      margin: 10px auto 0;
      background: var(--brand);
      border-radius: 2px;
      opacity: .7;
    }

    .standee__qr-frame {
      width: 176px;
      height: 176px;
      border-radius: 18px;
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 10px;
      position: relative;
      z-index: 1;
      box-shadow: 0 8px 20px rgba(26,18,18,.08);
    }

    .standee__qr {
      width: 156px;
      height: 156px;
      object-fit: contain;
      display: block;
      background: transparent;
    }

    .standee__bottom {
      flex: 0 0 auto;
      background: var(--cream);
      text-align: center;
      padding: 28px 20px 32px;
    }

    .standee__line1 {
      margin: 0 0 6px;
      font-size: 18px;
      font-weight: 600;
      letter-spacing: .16em;
      text-transform: uppercase;
      color: var(--ink);
    }

    .standee__line2 {
      margin: 0;
      font-size: 34px;
      font-weight: 800;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: var(--ink);
      line-height: 1.1;
    }

    @media print {
      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
      }
      @page {
        size: A4 portrait;
        margin: 12mm;
      }
      html, body { background: #fff !important; }
      .toolbar, .hint { display: none !important; }
      .stage { padding: 0 !important; display: block !important; }
      .standee {
        width: 148mm !important;
        max-width: 148mm !important;
        height: 210mm !important;
        margin: 0 auto !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        background: #940000 !important;
      }
      .standee__top { background: #940000 !important; }
      .standee__header { color: #ffffff !important; font-size: 20px !important; }
      .standee__header span { color: rgba(255,255,255,.72) !important; }
      .standee__circle {
        background: radial-gradient(circle at 50% 42%, #fffdf8 0%, #f4efe6 72%, #ebe4d8 100%) !important;
        box-shadow:
          0 0 0 3mm rgba(255,255,255,.08),
          0 0 0 5.5mm rgba(255,255,255,.04),
          0 4mm 10mm rgba(0,0,0,.25) !important;
        width: 112mm !important;
      }
      .standee__circle::before {
        border-color: rgba(148,0,0,.18) !important;
      }
      .standee__bottom { background: #f4efe6 !important; }
      .standee__qr-frame {
        background: #ffffff !important;
        box-shadow: 0 2mm 5mm rgba(26,18,18,.08) !important;
        width: 64mm !important;
        height: 64mm !important;
      }
      .standee__shop,
      .standee__line1,
      .standee__line2 { color: #1a1212 !important; }
      .standee__shop::after { background: #940000 !important; }
      .standee__qr { width: 56mm !important; height: 56mm !important; }
      .standee__shop { font-size: 12px !important; }
      .standee__line1 { font-size: 16px !important; }
      .standee__line2 { font-size: 30px !important; }
      img {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button type="button" class="print-btn" onclick="window.print()">Print standee</button>
    <a href="{{ route('business-website.index') }}">Back to Business Website</a>
    <div class="toolbar__tip">Enable “Background graphics” in print settings so the red colour prints.</div>
  </div>
  <p class="hint">QR opens: {{ $siteUrl }}</p>

  <div class="stage">
    <article class="standee" aria-label="Shop website QR standee">
      <div class="standee__top">
        <p class="standee__header">Mauzo<span>Link</span></p>
        <div class="standee__circle-wrap">
          <div class="standee__circle">
            <p class="standee__shop">{{ $merchantName }}</p>
            <div class="standee__qr-frame">
              <img class="standee__qr" src="{{ $qrUrl }}" alt="Website QR code" width="156" height="156">
            </div>
          </div>
        </div>
      </div>
      <div class="standee__bottom">
        <p class="standee__line1">{{ $title }}</p>
        <p class="standee__line2">{{ $footerText }}</p>
      </div>
    </article>
  </div>
</body>
</html>
