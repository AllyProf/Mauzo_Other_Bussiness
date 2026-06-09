<style>
  .role-form-page .role-preset-bar .role-preset-btn,
  .role-form-page .role-preset-bar #clearAllPermissionsBtn {
    flex-shrink: 0;
  }
  .role-form-page .animated-checkbox .label-text {
    line-height: 1.4;
    word-break: break-word;
  }

  @media (max-width: 991.98px) {
    .role-form-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .role-form-page .app-title p { font-size: 0.88rem; }
  }

  @media (max-width: 767.98px) {
    .role-form-page .app-title { flex-direction: column; align-items: flex-start !important; }
    .role-form-page .app-title h1 { font-size: 1.15rem; }
    .role-form-page .tile-body,
    .role-form-page .tile-footer { padding-left: 16px; padding-right: 16px; }
    .role-form-page .tile-footer { display: flex; flex-direction: column; gap: 8px; }
    .role-form-page .tile-footer .btn { width: 100%; margin: 0 !important; }
    .role-form-page .permission-group-header {
      flex-direction: column;
      align-items: flex-start !important;
      gap: 8px;
    }
    .role-form-page .permission-group-actions {
      width: 100%;
      display: flex;
    }
    .role-form-page .permission-group-actions .btn {
      flex: 1 1 50%;
    }
    .role-form-page .role-preset-bar {
      flex-direction: column;
      align-items: stretch !important;
    }
    .role-form-page .role-preset-bar > span {
      margin-right: 0 !important;
    }
    .role-form-page .role-preset-bar .role-preset-btn,
    .role-form-page .role-preset-bar #clearAllPermissionsBtn {
      width: 100%;
      margin-right: 0 !important;
    }
  }
</style>
