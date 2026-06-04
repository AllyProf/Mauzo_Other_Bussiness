<style>
  .business-type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; max-height: 320px; overflow-y: auto; }
  .business-type-card { border: 2px solid #e9ecef; border-radius: 8px; padding: 12px; text-align: center; cursor: pointer; background: #fff; min-height: 88px; }
  .business-type-card:hover, .business-type-card.selected { border-color: #940000; background: #fff5f5; }
  .business-type-card.imported { border-color: #28a745; background: #f6fff8; }
  .business-type-card.selected { background: #940000; color: #fff; }
  .business-type-card.selected .small { color: rgba(255,255,255,.85) !important; }
</style>
