@if(
  Auth::check()
  && !Auth::user()->isPlatformAdmin()
  && Auth::user()->business_id
  && config('services.groq.key')
  && Auth::user()->can('use_ai_assistant')
  && plan_feature('ai_assistant')
  && !request()->routeIs('ai.*')
)
@php
  $aiIcon = mauzo_ai_icon_url();
  $aiFirst = \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower(
      \Illuminate\Support\Str::of(Auth::user()->name ?? 'there')->trim()->explode(' ')->first() ?: 'there'
  ));
@endphp

<button type="button" class="ai-fab" id="aiAssistantFab" title="MauzoLink AI" aria-label="Open MauzoLink AI">
  <img src="{{ $aiIcon }}" alt="MauzoLink AI" class="ai-fab__logo" width="68" height="68" decoding="async">
</button>

<div class="ai-assistant-panel d-none" id="aiAssistantPanel" role="dialog" aria-labelledby="aiAssistantTitle" aria-modal="true">
  <div class="ai-assistant-panel__header">
    <div class="ai-assistant-panel__brand">
      <img src="{{ $aiIcon }}" alt="" class="ai-assistant-panel__avatar ai-brand-tint" aria-hidden="true">
      <div>
        <div class="ai-assistant-panel__name" id="aiAssistantTitle">MauzoLink AI</div>
        <div class="ai-assistant-panel__sub">Trainer &amp; assistant</div>
      </div>
    </div>
    <div class="ai-assistant-panel__actions">
      <a href="{{ route('ai.index') }}" class="ai-assistant-panel__icon-btn" title="Open full chat" aria-label="Open full chat">
        <i class="fa fa-expand" aria-hidden="true"></i>
      </a>
      <button type="button" class="ai-assistant-panel__icon-btn" id="aiAssistantClose" aria-label="Close" title="Close">
        <i class="fa fa-times" aria-hidden="true"></i>
      </button>
    </div>
  </div>

  <div class="ai-assistant-panel__messages" id="aiAssistantMessages">
    <div class="ai-chat-row ai-chat-row--bot">
      <div class="ai-chat-bubble ai-chat-bubble--bot">👋 Habari, {{ $aiFirst }}! Ask a quick question here, or open full chat for history &amp; voice.</div>
    </div>
  </div>

  <form class="ai-assistant-panel__form" id="aiAssistantForm">
    <textarea id="aiAssistantInput" rows="1" maxlength="1500" placeholder="Type your question…" required></textarea>
    <button type="submit" class="ai-send-btn" id="aiAssistantSend" title="Send" aria-label="Send">
      <i class="fa fa-paper-plane" aria-hidden="true"></i>
    </button>
  </form>
</div>

<style>
  :root {
    --ai-brand: #940000;
    --ai-brand-dark: #7a0000;
    --ai-brand-soft: rgba(148, 0, 0, 0.10);
  }
  .ai-brand-tint {
    filter: hue-rotate(148deg) saturate(1.35) contrast(1.08) brightness(0.95);
  }
  .ai-fab {
    position: fixed;
    bottom: 92px;
    right: 24px;
    z-index: 1040;
    width: 68px;
    height: 68px;
    padding: 0;
    border: 3px solid #fff;
    border-radius: 50%;
    background: #940000;
    box-shadow: 0 6px 20px rgba(148, 0, 0, 0.5);
    overflow: hidden;
    cursor: pointer;
    transition: transform .2s ease, box-shadow .2s ease;
  }
  .ai-fab__logo {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    filter: hue-rotate(148deg) saturate(1.35) contrast(1.08) brightness(0.95);
    transform: none;
  }
  .ai-fab:hover,
  .ai-fab:focus {
    transform: translateY(-2px) scale(1.06);
    box-shadow: 0 10px 26px rgba(148, 0, 0, 0.6);
    outline: none;
  }
  .ai-assistant-panel {
    position: fixed;
    right: 24px;
    bottom: 172px;
    z-index: 1050;
    width: min(380px, calc(100vw - 24px));
    height: min(560px, calc(100vh - 180px));
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 18px 48px rgba(15, 23, 42, 0.22);
    overflow: hidden;
  }
  .ai-assistant-panel.d-none { display: none !important; }
  .ai-assistant-panel__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: var(--ai-brand);
    color: #fff;
  }
  .ai-assistant-panel__brand {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
  }
  .ai-assistant-panel__avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    background: #5a0000;
    border: 2px solid rgba(255,255,255,.55);
    flex-shrink: 0;
  }
  .ai-assistant-panel__name {
    font-weight: 700;
    font-size: 15px;
    line-height: 1.2;
  }
  .ai-assistant-panel__sub {
    font-size: 11px;
    opacity: .92;
    margin-top: 1px;
  }
  .ai-assistant-panel__actions {
    display: flex;
    align-items: center;
    gap: 2px;
  }
  .ai-assistant-panel__icon-btn {
    border: 0;
    background: transparent;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    line-height: 32px;
    text-align: center;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .ai-assistant-panel__icon-btn:hover { background: rgba(255,255,255,.15); color: #fff; }
  .ai-assistant-panel__messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px 12px 8px;
    background: #fff;
  }
  .ai-chat-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    margin-bottom: 12px;
    max-width: 100%;
  }
  .ai-chat-row--bot { justify-content: flex-start; }
  .ai-chat-row--user { justify-content: flex-end; }
  .ai-chat-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid #e5e7eb;
    background: #940000;
  }
  .ai-chat-stack {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
  }
  .ai-chat-meta {
    font-size: 11px;
    color: #64748b;
    margin: 0 0 4px 2px;
  }
  .ai-chat-bubble {
    max-width: min(320px, 100%);
    padding: 10px 12px;
    border-radius: 16px;
    font-size: 13px;
    line-height: 1.45;
    word-break: break-word;
  }
  .ai-chat-bubble--bot {
    background: var(--ai-brand);
    color: #fff;
    border-bottom-left-radius: 5px;
  }
  .ai-chat-bubble--user {
    background: #eef1f4;
    color: #1e293b;
    border-bottom-right-radius: 5px;
  }
  .ai-chat-bubble--error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
  }
  .ai-chat-bubble p { margin: 0 0 .55em; }
  .ai-chat-bubble p:last-child { margin-bottom: 0; }
  .ai-chat-bubble ul, .ai-chat-bubble ol {
    margin: .35em 0 .55em;
    padding-left: 1.2em;
  }
  .ai-chat-bubble strong { font-weight: 700; }
  .ai-chat-bubble .ai-table-wrap {
    overflow-x: auto; margin: .5em 0; border-radius: 8px;
    border: 1px solid #e8d0d0; background: #fff;
  }
  .ai-chat-bubble table.ai-table {
    width: 100%; border-collapse: collapse; font-size: 12.5px; margin: 0; min-width: 240px;
  }
  .ai-chat-bubble table.ai-table th {
    background: #940000; color: #fff; font-weight: 600; text-align: left;
    padding: 7px 8px; white-space: nowrap;
  }
  .ai-chat-bubble table.ai-table td {
    padding: 6px 8px; border-top: 1px solid #f0e0e0; vertical-align: top;
  }
  .ai-chat-bubble table.ai-table tbody tr:nth-child(even) td { background: #fff8f8; }
  .ai-chat-bubble table.ai-table th:last-child,
  .ai-chat-bubble table.ai-table td:last-child { text-align: right; }
  .ai-chat-bubble code {
    font-family: Consolas, Monaco, monospace;
    font-size: .92em;
    background: rgba(255,255,255,.18);
    padding: 0 .25em;
    border-radius: 4px;
  }
  .ai-thinking {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .ai-thinking__dots {
    display: inline-flex;
    gap: 4px;
  }
  .ai-thinking__dots span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #fff;
    opacity: .45;
    animation: aiThinkBounce 1.1s ease-in-out infinite;
  }
  .ai-thinking__dots span:nth-child(2) { animation-delay: .15s; }
  .ai-thinking__dots span:nth-child(3) { animation-delay: .3s; }
  @keyframes aiThinkBounce {
    0%, 80%, 100% { transform: translateY(0); opacity: .35; }
    40% { transform: translateY(-4px); opacity: 1; }
  }
  .ai-assistant-panel__form {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 10px 12px 12px;
    border-top: 1px solid #eef2f7;
    background: #fff;
  }
  .ai-assistant-panel__form textarea {
    flex: 1;
    resize: none;
    border: 0;
    outline: none;
    padding: 8px 4px;
    font-size: 14px;
    line-height: 1.4;
    min-height: 40px;
    max-height: 96px;
    background: transparent;
  }
  .ai-send-btn {
    width: 40px;
    height: 40px;
    border: 0;
    border-radius: 50%;
    background: var(--ai-brand);
    color: #fff;
    cursor: pointer;
    flex-shrink: 0;
  }
  .ai-send-btn:hover { background: var(--ai-brand-dark); }
  .ai-send-btn:disabled { opacity: .55; cursor: not-allowed; }
  @media (max-width: 576px) {
    .ai-fab { bottom: 80px; right: 16px; width: 60px; height: 60px; }
    .ai-assistant-panel {
      right: 8px;
      left: 8px;
      width: auto;
      bottom: 150px;
      height: min(68vh, 560px);
    }
  }
  @media print {
    .ai-fab, .ai-assistant-panel { display: none !important; }
  }
</style>

@push('scripts')
@include('ai.partials.chat-engine')
<script>
(function () {
  var fab = document.getElementById('aiAssistantFab');
  var panel = document.getElementById('aiAssistantPanel');
  var closeBtn = document.getElementById('aiAssistantClose');
  if (!fab || !panel || !window.MauzoAiChat) return;

  fab.addEventListener('click', function () {
    panel.classList.toggle('d-none');
    if (!panel.classList.contains('d-none')) {
      document.getElementById('aiAssistantInput')?.focus();
    }
  });

  closeBtn?.addEventListener('click', function () {
    panel.classList.add('d-none');
  });

  var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
  MauzoAiChat.create({
    messagesEl: document.getElementById('aiAssistantMessages'),
    formEl: document.getElementById('aiAssistantForm'),
    inputEl: document.getElementById('aiAssistantInput'),
    sendBtn: document.getElementById('aiAssistantSend'),
    askUrl: @json(route('ai.assistant.ask')),
    csrf: csrf,
    avatarUrl: @json($aiIcon),
    variant: 'panel',
    getExtraPayload: function () {
      return { temporary: true };
    }
  });
})();
</script>
@endpush
@endif
