<style>
  .business-type-tabs,
  .category-tabs,
  .pulse-filter-tabs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    flex-wrap: wrap;
    flex: 1;
    min-width: 0;
    align-items: center;
    -webkit-overflow-scrolling: touch;
  }
  .business-type-tab,
  .category-tab,
  .pulse-filter-tab {
    cursor: pointer;
    padding: 8px 16px;
    border-radius: 5px;
    background: #fff;
    color: #333;
    font-size: 13px;
    white-space: nowrap;
    border: 1px solid #d0d4d9;
    font-weight: 500;
    transition: border-color .15s ease, background .15s ease, color .15s ease;
    line-height: 1.4;
    text-decoration: none !important;
    flex-shrink: 0;
  }
  button.business-type-tab,
  button.category-tab,
  button.pulse-filter-tab {
    appearance: none;
    -webkit-appearance: none;
  }
  a.business-type-tab:hover,
  a.category-tab:hover,
  a.pulse-filter-tab:hover {
    text-decoration: none !important;
    color: #333;
  }
  .business-type-tab.active,
  .category-tab.active,
  .pulse-filter-tab.active {
    background: #fce8e8;
    color: #940000 !important;
    border-color: #940000;
    font-weight: 700;
  }
  .business-type-tab:hover:not(.active),
  .category-tab:hover:not(.active),
  .pulse-filter-tab:hover:not(.active) {
    border-color: #b8bcc2;
    background: #fafafa;
    color: #222 !important;
  }
  .business-type-tab i,
  .category-tab i,
  .pulse-filter-tab i {
    margin-right: 6px;
  }
  @media (max-width: 575.98px) {
    .business-type-tab,
    .category-tab,
    .pulse-filter-tab {
      padding: 7px 12px;
      font-size: 12px;
    }
  }
</style>
