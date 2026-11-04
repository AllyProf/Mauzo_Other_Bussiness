@php
  $totalListed = $catalog->flatten(1)->count();
  $logoUrl = $business->logoUrl() ?? null;
  $categories = $catalog->keys()->values();
@endphp

@if($catalog->isEmpty())
  <p class="menu-empty text-center mb-0 py-5">No products in stock right now.</p>
@else
  <div class="menu-board" data-menu-board>
    <nav class="menu-tabs" role="tablist" aria-label="Product categories">
      <button type="button" class="menu-tab is-active" data-menu-filter="all" role="tab" aria-selected="true">All</button>
      @foreach($categories as $categoryName)
        <button type="button"
                class="menu-tab"
                data-menu-filter="{{ \Illuminate\Support\Str::slug($categoryName) }}"
                role="tab"
                aria-selected="false">{{ $categoryName }}</button>
      @endforeach
    </nav>

    @if($totalListed > 8)
      <div class="menu-search-wrap mx-auto mb-4">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control menu-search" placeholder="Search…" autocomplete="off" aria-label="Search products">
      </div>
    @endif

    <div class="menu-grid" data-menu-grid>
      @foreach($catalog as $categoryName => $products)
        @php $catSlug = \Illuminate\Support\Str::slug($categoryName); @endphp
        @foreach($products as $product)
          @php
            $searchBlob = strtolower($product['name'].' '.($product['brand'] ?? '').' '.$categoryName);
            $rows = collect($product['packaging_rows'] ?? []);
            $primary = $rows->first(fn ($r) => ! empty($r['price_label']));
            $priceLabel = $primary['price_label'] ?? null;
            $extraPrices = $rows->filter(fn ($r) => ! empty($r['price_label']))->skip(1)->take(3);
            $descParts = array_filter([
              $product['brand'] ?? null,
              $extraPrices->map(fn ($r) => $r['label'].' '.$r['price_label'])->implode(' · ') ?: null,
            ]);
            $desc = implode(' · ', $descParts);
            $initials = collect(preg_split('/\s+/', trim((string) $product['name'])))
              ->filter()
              ->take(2)
              ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
              ->implode('');
          @endphp
          <article class="menu-item"
                   data-menu-item
                   data-category="{{ $catSlug }}"
                   data-offer-search="{{ $searchBlob }}">
            <div class="menu-item__thumb" aria-hidden="true">
              @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="">
              @else
                <span class="menu-item__initials">{{ $initials !== '' ? $initials : '•' }}</span>
              @endif
            </div>
            <div class="menu-item__body">
              <div class="menu-item__topline">
                <h3 class="menu-item__name">{{ $product['name'] }}</h3>
                <span class="menu-item__dots" aria-hidden="true"></span>
                <span class="menu-item__price">{{ $priceLabel ?: 'Ask' }}</span>
              </div>
              @if($desc !== '')
                <p class="menu-item__desc">{{ $desc }}</p>
              @elseif(! $priceLabel)
                <p class="menu-item__desc">Ask in store for price</p>
              @endif
            </div>
          </article>
        @endforeach
      @endforeach
    </div>

    <p class="text-center menu-empty d-none menu-no-results mb-0 mt-4">No products match.</p>
  </div>
@endif

@once
  @push('styles')
  <style>
    .menu-board {
      --menu-ink: #fff;
      --menu-muted: rgba(255, 255, 255, .62);
      --menu-line: rgba(255, 255, 255, .22);
      --menu-accent: var(--accent-color, #cda45e);
      color: var(--menu-ink);
    }
    .menu-tabs {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: .35rem 1.35rem;
      margin: 0 auto 2rem;
      padding: 0;
    }
    .menu-tab {
      appearance: none;
      border: 0;
      background: transparent;
      color: var(--menu-ink);
      font-family: 'Poppins', sans-serif;
      font-size: 1.05rem;
      font-weight: 500;
      padding: .25rem 0;
      cursor: pointer;
      position: relative;
      transition: color .25s ease;
    }
    .menu-tab::after {
      content: "";
      position: absolute;
      left: 0; right: 0; bottom: -4px;
      height: 2px;
      background: var(--menu-accent);
      transform: scaleX(0);
      transition: transform .25s ease;
    }
    .menu-tab:hover { color: var(--menu-accent); }
    .menu-tab.is-active { color: var(--menu-accent); }
    .menu-tab.is-active::after { transform: scaleX(1); }

    .menu-search-wrap {
      position: relative;
      max-width: 420px;
    }
    .menu-search-wrap .bi-search {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      color: var(--menu-muted); pointer-events: none;
    }
    .menu-search-wrap .form-control {
      background: rgba(255,255,255,.06);
      border: 1px solid var(--menu-line);
      color: #fff;
      border-radius: 999px;
      padding-left: 2.5rem;
    }
    .menu-search-wrap .form-control::placeholder { color: var(--menu-muted); }
    .menu-search-wrap .form-control:focus {
      background: rgba(255,255,255,.1);
      border-color: var(--menu-accent);
      box-shadow: none;
      color: #fff;
    }

    .menu-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1.75rem 2.5rem;
      align-items: start;
    }
    .menu-item {
      display: flex;
      gap: 1rem;
      align-items: flex-start;
      opacity: 1;
      transform: translateY(0);
      transition: opacity .35s ease, transform .35s ease;
    }
    .menu-item.is-hiding {
      opacity: 0;
      transform: translateY(10px);
      pointer-events: none;
    }
    .menu-item.is-hidden { display: none; }

    .menu-item__thumb {
      flex: 0 0 70px;
      width: 70px;
      height: 70px;
      border-radius: 50%;
      overflow: hidden;
      border: 3px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.08);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 0 0 1px rgba(0,0,0,.35);
    }
    .menu-item__thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .menu-item__initials {
      font-weight: 700;
      font-size: .95rem;
      color: var(--menu-accent);
      letter-spacing: .02em;
    }
    .menu-item__body { flex: 1; min-width: 0; padding-top: .15rem; }
    .menu-item__topline {
      display: flex;
      align-items: baseline;
      gap: .55rem;
      width: 100%;
    }
    .menu-item__name {
      margin: 0;
      font-size: 1.05rem;
      font-weight: 700;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 55%;
    }
    .menu-item__dots {
      flex: 1 1 auto;
      min-width: 1.5rem;
      border-bottom: 2px dotted rgba(255,255,255,.35);
      transform: translateY(-4px);
    }
    .menu-item__price {
      flex: 0 0 auto;
      font-weight: 700;
      font-size: 1.05rem;
      color: var(--menu-accent);
      white-space: nowrap;
    }
    .menu-item__desc {
      margin: .4rem 0 0;
      font-size: .88rem;
      font-style: italic;
      color: var(--menu-muted);
      line-height: 1.45;
    }
    .menu-empty { color: var(--menu-muted); }

    @media (max-width: 991px) {
      .menu-grid { grid-template-columns: 1fr; gap: 1.35rem; }
      .menu-item__name { max-width: 48%; }
    }
    @media (max-width: 575px) {
      .menu-item__thumb { flex-basis: 58px; width: 58px; height: 58px; }
      .menu-tab { font-size: .95rem; }
      .menu-item__name { font-size: .98rem; max-width: 42%; }
    }
  </style>
  @endpush

  @push('scripts')
  <script>
    (function () {
      var board = document.querySelector('[data-menu-board]');
      if (!board) return;

      var tabs = board.querySelectorAll('[data-menu-filter]');
      var items = board.querySelectorAll('[data-menu-item]');
      var search = board.querySelector('.menu-search');
      var emptyMsg = board.querySelector('.menu-no-results');
      var activeFilter = 'all';

      function matchesSearch(el, q) {
        if (!q) return true;
        return (el.getAttribute('data-offer-search') || '').indexOf(q) !== -1;
      }

      function matchesCategory(el) {
        if (activeFilter === 'all') return true;
        return el.getAttribute('data-category') === activeFilter;
      }

      function applyFilter(animate) {
        var q = ((search && search.value) || '').trim().toLowerCase();
        var visible = 0;
        var toShow = [];
        var toHide = [];

        items.forEach(function (el) {
          var show = matchesCategory(el) && matchesSearch(el, q);
          if (show) {
            visible++;
            toShow.push(el);
          } else {
            toHide.push(el);
          }
        });

        if (!animate) {
          toHide.forEach(function (el) {
            el.classList.add('is-hidden');
            el.classList.remove('is-hiding');
          });
          toShow.forEach(function (el) {
            el.classList.remove('is-hidden', 'is-hiding');
          });
          if (emptyMsg) emptyMsg.classList.toggle('d-none', visible > 0);
          return;
        }

        toHide.forEach(function (el) {
          if (!el.classList.contains('is-hidden')) el.classList.add('is-hiding');
        });

        window.setTimeout(function () {
          toHide.forEach(function (el) {
            el.classList.add('is-hidden');
            el.classList.remove('is-hiding');
          });
          toShow.forEach(function (el) {
            el.classList.remove('is-hidden');
            el.classList.add('is-hiding');
          });
          // next frame: fade in
          requestAnimationFrame(function () {
            requestAnimationFrame(function () {
              toShow.forEach(function (el) { el.classList.remove('is-hiding'); });
            });
          });
          if (emptyMsg) emptyMsg.classList.toggle('d-none', visible > 0);
        }, 220);
      }

      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          activeFilter = tab.getAttribute('data-menu-filter') || 'all';
          tabs.forEach(function (t) {
            var on = t === tab;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
          });
          applyFilter(true);
        });
      });

      if (search) {
        search.addEventListener('input', function () { applyFilter(false); });
      }
    })();
  </script>
  @endpush
@endonce
