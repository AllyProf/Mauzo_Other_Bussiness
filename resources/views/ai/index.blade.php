@extends('layouts.app')

@section('title', __('menu.ai_assistant') . ' - MauzoLink')

@section('styles')
<style>
  :root { --ai-brand: #940000; }
  /* Fill remaining viewport under app header + title (keeps page from growing) */
  .ai-shell {
    align-items: stretch;
    height: calc(100vh - 150px);
    min-height: 520px;
    margin-left: 0; margin-right: 0;
  }
  .ai-shell > [class*="col-"] {
    display: flex; flex-direction: column;
    height: 100%; max-height: 100%;
    padding-bottom: 0;
  }
  .ai-main-tile,
  .ai-history-tile {
    display: flex; flex-direction: column;
    flex: 1 1 auto;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    margin-bottom: 0 !important;
  }
  .ai-main-tile > .tile-body {
    flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden;
  }
  .ai-history-tile > .tile-body { flex: 1; min-height: 0; overflow-y: auto; }
  .ai-history-item {
    display: flex; align-items: stretch; gap: 0;
    border: 1px solid #eee; border-radius: 8px; margin-bottom: 8px;
    background: #fff; overflow: hidden;
  }
  .ai-history-item:hover { border-color: #940000; background: #fff8f8; }
  .ai-history-item.is-active { border-color: #940000; background: #fff1f1; }
  .ai-history-item__link {
    flex: 1; min-width: 0; padding: 10px 12px;
    text-decoration: none; color: inherit; display: block;
  }
  .ai-history-item__link:hover { text-decoration: none; color: inherit; }
  .ai-history-item__title { font-size: 13px; font-weight: 600; line-height: 1.3; }
  .ai-history-item__meta { font-size: 11px; color: #888; margin-top: 2px; }
  .ai-history-item__del {
    border: 0; border-left: 1px solid #eee; background: transparent; color: #999;
    cursor: pointer; padding: 0 12px; flex-shrink: 0; align-self: stretch;
  }
  .ai-history-item__del:hover { color: #940000; background: #fff5f5; }
  .ai-chat-stage {
    background: #fafafa; border: 1px solid #eee; border-radius: 8px;
    flex: 1 1 auto; min-height: 0; height: 100%;
    overflow-y: auto; padding: 16px;
    overscroll-behavior: contain;
    display: flex; flex-direction: column;
  }
  .ai-composer-wrap { flex-shrink: 0; padding-top: 12px; }
  .ai-empty {
    text-align: center; padding: 12px; max-width: 640px;
    margin: auto; width: 100%;
  }
  .ai-empty__gif {
    width: 72px; height: 72px; object-fit: cover; margin-bottom: 10px;
    border-radius: 16px; background: #940000; box-shadow: 0 8px 22px rgba(148,0,0,.22);
    filter: hue-rotate(148deg) saturate(1.35) contrast(1.08) brightness(0.95);
  }
  .ai-page__messages { width: 100%; }
  .ai-header-gif {
    width: 40px; height: 40px; border-radius: 10px; object-fit: cover; background: #940000;
    filter: hue-rotate(148deg) saturate(1.35) contrast(1.08) brightness(0.95);
  }
  .ai-temp-switch {
    display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; margin: 0;
  }
  .ai-temp-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
  .ai-temp-switch__track {
    width: 42px; height: 24px; border-radius: 999px; background: #d1d5db; position: relative;
    transition: background .2s ease; flex-shrink: 0;
  }
  .ai-temp-switch__track::after {
    content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px;
    border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2);
    transition: transform .2s ease;
  }
  .ai-temp-switch input:checked + .ai-temp-switch__track { background: #940000; }
  .ai-temp-switch input:checked + .ai-temp-switch__track::after { transform: translateX(18px); }
  .ai-temp-switch__label { font-size: 13px; color: #555; font-weight: 500; }
  .ai-empty h2 { font-size: 1.25rem; font-weight: 700; color: #222; margin-bottom: 6px; }
  .ai-empty p { color: #777; margin-bottom: 14px; }
  .ai-chips {
    display: flex; flex-wrap: wrap; justify-content: center; gap: 8px;
  }
  .ai-chip {
    border: 1px solid #e5e5e5; background: #fff; border-radius: 999px;
    padding: 8px 12px; font-size: 12px; color: #444; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .ai-chip i { color: #940000; }
  .ai-chip:hover { border-color: #940000; color: #940000; background: #fff8f8; }
  .ai-chat-row { display: flex; gap: 10px; margin-bottom: 14px; align-items: flex-start; }
  .ai-chat-row--user { justify-content: flex-end; }
  .ai-chat-avatar {
    width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
    border: 1px solid #eee; background: #940000; flex-shrink: 0;
    filter: hue-rotate(148deg) saturate(1.35) contrast(1.08) brightness(0.95);
  }
  .ai-chat-stack { max-width: min(680px, 100%); }
  .ai-chat-meta { font-size: 11px; color: #888; margin-bottom: 4px; }
  .ai-chat-bubble {
    border-radius: 12px; padding: 10px 12px; font-size: 13.5px; line-height: 1.5;
    word-break: break-word; background: #fff; border: 1px solid #eee;
  }
  .ai-chat-row--user .ai-chat-bubble { background: #f3f3f3; border-color: #e8e8e8; }
  .ai-chat-bubble--error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
  .ai-chat-bubble p { margin: 0 0 .5em; }
  .ai-chat-bubble p:last-child { margin-bottom: 0; }
  .ai-chat-bubble ul, .ai-chat-bubble ol { margin: .3em 0 .5em; padding-left: 1.2em; }
  .ai-chat-bubble strong { font-weight: 700; }
  .ai-chat-bubble .ai-table-wrap {
    overflow-x: auto; margin: .55em 0; border-radius: 8px;
    border: 1px solid #e8d0d0; background: #fff;
  }
  .ai-chat-bubble table.ai-table {
    width: 100%; border-collapse: collapse; font-size: 13px; margin: 0; min-width: 260px;
  }
  .ai-chat-bubble table.ai-table th {
    background: #940000; color: #fff; font-weight: 600; text-align: left;
    padding: 8px 10px; white-space: nowrap;
  }
  .ai-chat-bubble table.ai-table td {
    padding: 7px 10px; border-top: 1px solid #f0e0e0; vertical-align: top;
  }
  .ai-chat-bubble table.ai-table tbody tr:nth-child(even) td { background: #fff8f8; }
  .ai-chat-bubble table.ai-table th:last-child,
  .ai-chat-bubble table.ai-table td:last-child { text-align: right; }
  .ai-thinking { display: inline-flex; align-items: center; gap: 8px; color: #940000; }
  .ai-thinking__dots { display: inline-flex; gap: 4px; }
  .ai-thinking__dots span {
    width: 6px; height: 6px; border-radius: 50%; background: #940000; opacity: .4;
    animation: aiThinkBounce 1.1s ease-in-out infinite;
  }
  .ai-thinking__dots span:nth-child(2) { animation-delay: .15s; }
  .ai-thinking__dots span:nth-child(3) { animation-delay: .3s; }
  @keyframes aiThinkBounce {
    0%, 80%, 100% { transform: translateY(0); opacity: .35; }
    40% { transform: translateY(-4px); opacity: 1; }
  }
  .ai-composer {
    display: flex; align-items: flex-end; gap: 8px;
    border: 1px solid #ddd; border-radius: 12px; padding: 10px; background: #fff;
  }
  .ai-composer:focus-within { border-color: #940000; box-shadow: 0 0 0 3px rgba(148,0,0,.1); }
  .ai-composer textarea {
    flex: 1; border: 0; outline: none; resize: none; min-height: 44px; max-height: 140px;
    font-size: 14px; background: transparent;
  }
  .ai-icon-btn, .ai-send-btn {
    width: 40px; height: 40px; border: 0; border-radius: 50%; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .ai-icon-btn { background: #f3f3f3; color: #555; }
  .ai-icon-btn.is-recording { background: #940000; color: #fff; animation: aiPulse 1.2s ease infinite; }
  .ai-send-btn { background: #940000; color: #fff; }
  .ai-send-btn:disabled, .ai-icon-btn:disabled { opacity: .55; cursor: not-allowed; }
  @keyframes aiPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(148,0,0,.45); }
    50% { box-shadow: 0 0 0 8px rgba(148,0,0,0); }
  }
  .ai-temp-banner {
    display: none; background: #fff8e6; border: 1px solid #f0d78c; color: #7a5b00;
    border-radius: 8px; padding: 8px 12px; font-size: 12px; margin-bottom: 10px;
  }
  .ai-temp-banner.is-on { display: block; }
  .ai-main-tile > .tile-title,
  .ai-history-tile > .tile-title { flex-shrink: 0; }
  @media (max-width: 991px) {
    .ai-shell { height: auto; min-height: 0; }
    .ai-shell > [class*="col-"] { height: auto; max-height: none; margin-bottom: 12px; }
    .ai-main-tile,
    .ai-history-tile { height: auto; max-height: none; }
    .ai-history-tile > .tile-body { max-height: 220px; }
    .ai-chat-stage { min-height: 360px; max-height: 60vh; }
  }
</style>
@endsection

@section('content')
@php $hasMessages = $messages->isNotEmpty(); @endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-magic"></i> {{ __('menu.ai_assistant') }}</h1>
    <p>{{ __('ai.page_subtitle') }}</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    @include('layouts.partials._breadcrumb_manual')
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item active"><a href="{{ route('ai.index') }}">{{ __('menu.ai_assistant') }}</a></li>
  </ul>
</div>

<div class="row ai-shell">
  <div class="col-lg-3 col-md-4">
    <div class="tile ai-history-tile">
      <div class="tile-title d-flex align-items-center justify-content-between">
        <span>{{ __('ai.history') }}</span>
        <a href="{{ route('ai.index') }}" class="btn btn-sm btn-outline-primary" id="aiPageNewChat">
          <i class="fa fa-plus"></i> {{ __('ai.new_chat') }}
        </a>
      </div>
      <div class="tile-body" id="aiHistoryList">
        @forelse($conversations as $convo)
          <div class="ai-history-item {{ optional($activeConversation)->id === $convo->id ? 'is-active' : '' }}" data-id="{{ $convo->id }}">
            <a href="{{ route('ai.index', ['c' => $convo->id]) }}" class="ai-history-item__link">
              <div class="ai-history-item__title text-truncate">{{ $convo->title ?: __('ai.untitled') }}</div>
              <div class="ai-history-item__meta">{{ optional($convo->last_message_at)->diffForHumans() ?? optional($convo->updated_at)->diffForHumans() }}</div>
            </a>
            <button type="button" class="ai-history-item__del" data-delete-id="{{ $convo->id }}" title="{{ __('ai.delete') }}">
              <i class="fa fa-trash"></i>
            </button>
          </div>
        @empty
          <p class="text-muted small mb-0" id="aiHistoryEmpty">{{ __('ai.no_history') }}</p>
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-lg-9 col-md-8">
    <div class="tile ai-main-tile">
      <div class="tile-title d-flex flex-wrap align-items-center justify-content-between" style="gap:10px;">
        <div class="d-flex align-items-center" style="gap:10px;">
          <img src="{{ $aiIcon }}" alt="MauzoLink AI" class="ai-header-gif">
          <div>
            <strong>{{ optional($activeConversation)->title ?: __('menu.ai_assistant') }}</strong>
            <div class="small text-muted">{{ __('ai.rail_subtitle') }}</div>
          </div>
        </div>
        <label class="ai-temp-switch" for="aiTemporaryToggle">
          <input type="checkbox" id="aiTemporaryToggle">
          <span class="ai-temp-switch__track" aria-hidden="true"></span>
          <span class="ai-temp-switch__label">{{ __('ai.temporary_chat') }}</span>
        </label>
      </div>
      <div class="tile-body">
        <div class="ai-temp-banner" id="aiTempBanner">{{ __('ai.temporary_hint') }}</div>

        <div class="ai-chat-stage" id="aiChatStage">
          <div class="ai-empty {{ $hasMessages ? 'd-none' : '' }}" id="aiPageEmpty">
            <img src="{{ $aiIcon }}" alt="MauzoLink AI" class="ai-empty__gif">
            <h2>{{ __('ai.agenda_title_named', ['name' => $firstName]) }}</h2>
            <p>{{ __('ai.empty_text') }}</p>
            <div class="ai-chips" id="aiChips">
              <button type="button" class="ai-chip ai-starter" data-prompt="Jinsi ya kupokea stock (receive an item) step by step">
                <i class="fa fa-cube"></i> {{ __('ai.prompt_receive') }}
              </button>
              <button type="button" class="ai-chip ai-starter" data-prompt="Nipe ripoti ya mwaka">
                <i class="fa fa-bar-chart"></i> {{ __('ai.prompt_year') }}
              </button>
              <button type="button" class="ai-chip ai-starter" data-prompt="faida je">
                <i class="fa fa-line-chart"></i> {{ __('ai.prompt_profit') }}
              </button>
              <button type="button" class="ai-chip ai-starter" data-prompt="Which items are low or out of stock?">
                <i class="fa fa-archive"></i> {{ __('ai.prompt_low_stock') }}
              </button>
            </div>
          </div>

          <div class="ai-page__messages {{ $hasMessages ? '' : 'd-none' }}" id="aiPageMessages">
            @foreach($messages as $msg)
              @if($msg->role === 'user')
                <div class="ai-chat-row ai-chat-row--user">
                  <div class="ai-chat-bubble">{{ $msg->content }}</div>
                </div>
              @else
                <div class="ai-chat-row ai-chat-row--bot">
                  <img src="{{ $aiIcon }}" alt="" class="ai-chat-avatar">
                  <div class="ai-chat-stack">
                    <div class="ai-chat-meta">MauzoLink AI</div>
                    <div class="ai-chat-bubble ai-md">{{ $msg->content }}</div>
                  </div>
                </div>
              @endif
            @endforeach
          </div>
        </div>

        <div class="ai-composer-wrap">
          <form class="ai-composer" id="aiPageForm">
            <button type="button" class="ai-icon-btn" id="aiVoiceBtn" title="{{ __('ai.voice') }}" aria-label="{{ __('ai.voice') }}">
              <i class="fa fa-microphone"></i>
            </button>
            <textarea id="aiPageInput" rows="1" maxlength="1500" placeholder="{{ __('ai.input_placeholder') }}" required></textarea>
            <button type="submit" class="ai-send-btn" id="aiPageSend" title="{{ __('ai.send') }}" aria-label="{{ __('ai.send') }}">
              <i class="fa fa-paper-plane"></i>
            </button>
          </form>
          <div class="small text-muted text-center mt-2 mb-0">{{ __('ai.footer_hint') }}</div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@include('ai.partials.chat-engine')
<script>
(function () {
  if (!window.MauzoAiChat) return;

  var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
  var askUrl = @json(route('ai.assistant.ask'));
  var transcribeUrl = @json(route('ai.transcribe'));
  var destroyBase = @json(url('/ai/conversations'));
  var avatarUrl = @json($aiIcon);
  var conversationId = @json(optional($activeConversation)->id);
  var temporaryToggle = document.getElementById('aiTemporaryToggle');
  var tempBanner = document.getElementById('aiTempBanner');
  var messagesEl = document.getElementById('aiPageMessages');
  var emptyEl = document.getElementById('aiPageEmpty');
  var stage = document.getElementById('aiChatStage');
  var voiceBtn = document.getElementById('aiVoiceBtn');

  messagesEl.querySelectorAll('.ai-md').forEach(function (el) {
    var raw = el.textContent || '';
    el.classList.remove('ai-md');
    el.innerHTML = MauzoAiChat.renderMarkdown(raw);
  });

  var initialHistory = @json(
      $messages->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->values()
  );

  MauzoAiChat.create({
    messagesEl: messagesEl,
    formEl: document.getElementById('aiPageForm'),
    inputEl: document.getElementById('aiPageInput'),
    sendBtn: document.getElementById('aiPageSend'),
    askUrl: askUrl,
    csrf: csrf,
    avatarUrl: avatarUrl,
    variant: 'page',
    initialHistory: initialHistory,
    hintButtons: document.querySelectorAll('.ai-starter'),
    emptyStateEl: emptyEl,
    newChatBtn: document.getElementById('aiPageNewChat'),
    getExtraPayload: function () {
      return {
        conversation_id: temporaryToggle.checked ? null : conversationId,
        temporary: !!temporaryToggle.checked
      };
    },
    onReply: function (result) {
      if (temporaryToggle.checked) return;
      if (result.conversation_id) {
        conversationId = result.conversation_id;
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, '', @json(route('ai.index')) + '?c=' + conversationId);
        }
      }
    },
    scrollParent: stage
  });

  temporaryToggle.addEventListener('change', function () {
    tempBanner.classList.toggle('is-on', temporaryToggle.checked);
  });

  document.getElementById('aiHistoryList').addEventListener('click', function (e) {
    var del = e.target.closest('[data-delete-id]');
    if (!del) return;
    e.preventDefault();
    e.stopPropagation();
    var id = del.getAttribute('data-delete-id');
    if (!id || !confirm(@json(__('ai.delete_confirm')))) return;

    fetch(destroyBase + '/' + id, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(function (res) {
      if (!res.ok) throw new Error('fail');
      var item = del.closest('.ai-history-item');
      if (item) item.remove();
      if (String(conversationId) === String(id)) {
        window.location.href = @json(route('ai.index'));
      }
    }).catch(function () {
      alert(@json(__('ai.delete_failed')));
    });
  });

  var mediaRecorder = null;
  var chunks = [];
  var recording = false;

  voiceBtn.addEventListener('click', async function () {
    if (recording && mediaRecorder) {
      mediaRecorder.stop();
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert(@json(__('ai.voice_unsupported')));
      return;
    }

    try {
      var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      chunks = [];
      mediaRecorder = new MediaRecorder(stream);
      mediaRecorder.ondataavailable = function (ev) {
        if (ev.data && ev.data.size > 0) chunks.push(ev.data);
      };
      mediaRecorder.onstop = function () {
        recording = false;
        voiceBtn.classList.remove('is-recording');
        stream.getTracks().forEach(function (t) { t.stop(); });
        var blob = new Blob(chunks, { type: mediaRecorder.mimeType || 'audio/webm' });
        if (blob.size < 500) return;
        uploadAudio(blob);
      };
      mediaRecorder.start();
      recording = true;
      voiceBtn.classList.add('is-recording');
    } catch (err) {
      alert(@json(__('ai.voice_permission')));
    }
  });

  function uploadAudio(blob) {
    voiceBtn.disabled = true;
    var fd = new FormData();
    fd.append('audio', blob, 'voice.webm');
    fetch(transcribeUrl, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: fd
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        if (!result.ok) {
          alert((result.data && result.data.message) || @json(__('ai.voice_failed')));
          return;
        }
        var input = document.getElementById('aiPageInput');
        input.value = (input.value ? (input.value + ' ') : '') + (result.data.text || '');
        input.dispatchEvent(new Event('input'));
        input.focus();
      })
      .catch(function () { alert(@json(__('ai.voice_failed'))); })
      .finally(function () { voiceBtn.disabled = false; });
  }
})();
</script>
@endpush
