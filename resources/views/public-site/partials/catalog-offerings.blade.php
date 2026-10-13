@php
  $totalListed = $catalog->flatten(1)->count();
  $showSearch = $totalListed > 8;
@endphp

@if($catalog->isEmpty())
  <p class="text-center text-muted mb-0 py-4">
    In-stock products from your Mauzo Link inventory will appear here automatically.
  </p>
@else
  @if($showSearch)
    <div class="offerings-toolbar mb-4">
      <div class="row g-2 align-items-center justify-content-center">
        <div class="col-md-6 col-lg-5">
          <div class="offerings-search-wrap">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control offerings-search" placeholder="Search products…" autocomplete="off" aria-label="Search products">
          </div>
        </div>
        <div class="col-auto">
          <span class="offerings-count-badge">{{ $totalListed }} in stock</span>
        </div>
      </div>
    </div>
  @endif

  <div class="offerings-catalog" data-offerings-root>
    @foreach($catalog as $categoryName => $products)
      <div class="offerings-category mb-5" data-category-block>
        <div class="offerings-category-head">
          <h3 class="offerings-category-title">{{ $categoryName }}</h3>
          <span class="offerings-category-count">{{ $products->count() }}</span>
        </div>

        <div class="table-responsive offerings-table-wrap shadow-sm">
          <table class="table offerings-table mb-0">
            <thead>
              <tr>
                <th scope="col">Product</th>
                <th scope="col" class="offerings-col-prices">Price</th>
              </tr>
            </thead>
            <tbody>
              @foreach($products as $product)
                @php
                  $searchBlob = strtolower($product['name'].' '.($product['brand'] ?? '').' '.$categoryName);
                  $hasAnyPrice = collect($product['packaging_rows'] ?? [])->contains(fn ($r) => ! empty($r['price_label']));
                @endphp
                <tr class="offerings-row" data-offer-search="{{ $searchBlob }}">
                  <td class="offerings-product-cell">
                    <div class="offerings-product-name">{{ $product['name'] }}</div>
                    @if(!empty($product['brand']))
                      <div class="offerings-product-meta">{{ $product['brand'] }}</div>
                    @endif
                    <span class="offerings-stock-pill"><i class="bi bi-check2-circle"></i> In stock</span>
                  </td>
                  <td class="offerings-prices-cell">
                    @foreach($product['packaging_rows'] as $row)
                      <div class="offerings-price-line">
                        <span class="offerings-unit">{{ $row['label'] }}</span>
                        @if(!empty($row['price_label']))
                          <span class="offerings-amount">{{ $row['price_label'] }}</span>
                        @else
                          <span class="offerings-amount offerings-amount-muted">—</span>
                        @endif
                      </div>
                    @endforeach
                    @if(! $hasAnyPrice)
                      <div class="offerings-price-note small text-muted">Ask in store for price</div>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endforeach

    <p class="text-center text-muted small d-none offerings-no-results mb-0">No products match your search.</p>
  </div>
@endif

@once
  @push('styles')
  <style>
    .offerings-toolbar { max-width: 720px; margin-left: auto; margin-right: auto; }
    .offerings-search-wrap { position: relative; }
    .offerings-search-wrap .bi-search {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      color: #888; pointer-events: none;
    }
    .offerings-search-wrap .form-control {
      padding-left: 2.5rem; border-radius: 999px; border: 1px solid rgba(0,0,0,.08);
      box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }
    .offerings-count-badge {
      display: inline-block; font-size: .85rem; font-weight: 600;
      padding: .45rem .9rem; border-radius: 999px;
      background: color-mix(in srgb, var(--accent-color) 10%, #fff);
      color: var(--accent-color);
    }
    .offerings-category-head {
      display: flex; align-items: baseline; justify-content: space-between; gap: 1rem;
      margin-bottom: 1rem; padding-bottom: .5rem;
      border-bottom: 2px solid color-mix(in srgb, var(--accent-color) 35%, transparent);
    }
    .offerings-category-title {
      font-size: 1.25rem; font-weight: 700; margin: 0; color: #222;
    }
    .offerings-category-count {
      font-size: .8rem; font-weight: 600; color: #888;
    }
    .offerings-table-wrap {
      border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,.06);
      background: #fff;
    }
    .offerings-table thead th {
      background: color-mix(in srgb, var(--accent-color) 8%, #f8f9fa);
      font-size: .75rem; text-transform: uppercase; letter-spacing: .04em;
      color: #555; border-bottom: 1px solid rgba(0,0,0,.06); padding: .85rem 1.25rem;
    }
    .offerings-table tbody td {
      padding: 1rem 1.25rem; vertical-align: middle; border-color: rgba(0,0,0,.05);
    }
    .offerings-table tbody tr:hover { background: rgba(0,0,0,.015); }
    .offerings-product-name { font-weight: 700; color: #1a1a1a; line-height: 1.3; }
    .offerings-product-meta { font-size: .85rem; color: #777; margin-top: .15rem; }
    .offerings-stock-pill {
      display: inline-flex; align-items: center; gap: .25rem;
      font-size: .7rem; font-weight: 600; margin-top: .45rem;
      padding: .2rem .55rem; border-radius: 999px;
      background: rgba(25, 135, 84, .1); color: #198754;
    }
    .offerings-col-prices { width: 42%; min-width: 200px; }
    .offerings-price-line {
      display: flex; justify-content: space-between; align-items: baseline; gap: 1rem;
      padding: .2rem 0; font-size: .92rem;
    }
    .offerings-unit { color: #666; }
    .offerings-amount { font-weight: 700; color: var(--accent-color); white-space: nowrap; }
    .offerings-amount-muted { color: #bbb; font-weight: 600; }
    .offerings-price-note { margin-top: .35rem; }
    @media (max-width: 575px) {
      .offerings-table thead { display: none; }
      .offerings-table tbody tr {
        display: block; border-bottom: 1px solid rgba(0,0,0,.06); padding: .25rem 0;
      }
      .offerings-table tbody td {
        display: block; border: none; padding: .65rem 1rem;
      }
      .offerings-prices-cell { padding-top: 0 !important; }
      .offerings-prices-cell::before {
        content: "Price"; display: block; font-size: .7rem; text-transform: uppercase;
        letter-spacing: .04em; color: #999; margin-bottom: .35rem;
      }
    }
  </style>
  @endpush

  @push('scripts')
  <script>
    (function () {
      var input = document.querySelector('.offerings-search');
      if (!input) return;
      var rows = document.querySelectorAll('[data-offer-search]');
      var blocks = document.querySelectorAll('[data-category-block]');
      var emptyMsg = document.querySelector('.offerings-no-results');

      input.addEventListener('input', function () {
        var q = (input.value || '').trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
          var match = !q || (row.getAttribute('data-offer-search') || '').indexOf(q) !== -1;
          row.style.display = match ? '' : 'none';
          if (match) visible++;
        });
        blocks.forEach(function (block) {
          var any = block.querySelector('.offerings-row:not([style*="display: none"])');
          block.style.display = any ? '' : 'none';
        });
        if (emptyMsg) emptyMsg.classList.toggle('d-none', visible > 0 || !q);
      });
    })();
  </script>
  @endpush
@endonce
