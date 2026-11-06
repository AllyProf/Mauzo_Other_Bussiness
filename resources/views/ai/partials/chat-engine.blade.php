{{-- Shared MauzoLink AI chat engine (full page + floating widget) --}}
<script>
(function (window) {
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderMarkdown(text) {
    var src = String(text || '').replace(/\r\n/g, '\n');
    var lines = src.split('\n');
    var html = [];
    var listType = null;

    function closeList() {
      if (listType) {
        html.push(listType === 'ol' ? '</ol>' : '</ul>');
        listType = null;
      }
    }

    function inlineFormat(s) {
      s = escapeHtml(s);
      s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
      s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
      s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
      s = s.replace(/(^|[^\*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
      return s;
    }

    function isTableRow(s) {
      return s.indexOf('|') !== -1 && s.replace(/\|/g, '').trim() !== '';
    }

    function isTableSeparator(s) {
      return /^\|?(\s*:?-+:?\s*\|)+\s*:?-+:?\s*\|?$/.test(s);
    }

    function splitTableCells(s) {
      return s.replace(/^\|/, '').replace(/\|$/, '').split('|').map(function (c) {
        return c.trim();
      });
    }

    function renderTable(startIndex) {
      var rows = [];
      var i = startIndex;
      while (i < lines.length) {
        var rowLine = lines[i].trim();
        if (!rowLine) break;
        if (!isTableRow(rowLine)) break;
        if (!isTableSeparator(rowLine)) {
          rows.push(splitTableCells(rowLine));
        }
        i++;
      }
      if (rows.length === 0) return { html: '', next: startIndex };

      var out = ['<div class="ai-table-wrap"><table class="ai-table"><thead><tr>'];
      rows[0].forEach(function (cell) {
        out.push('<th>' + inlineFormat(cell) + '</th>');
      });
      out.push('</tr></thead><tbody>');
      for (var r = 1; r < rows.length; r++) {
        out.push('<tr>');
        rows[r].forEach(function (cell) {
          out.push('<td>' + inlineFormat(cell) + '</td>');
        });
        out.push('</tr>');
      }
      out.push('</tbody></table></div>');
      return { html: out.join(''), next: i - 1 };
    }

    for (var i = 0; i < lines.length; i++) {
      var trimmed = lines[i].trim();
      if (!trimmed) {
        closeList();
        continue;
      }

      if (isTableRow(trimmed)) {
        closeList();
        var table = renderTable(i);
        if (table.html) {
          html.push(table.html);
          i = table.next;
          continue;
        }
      }

      var heading = trimmed.match(/^(#{1,4})\s+(.+)$/);
      if (heading) {
        closeList();
        var level = heading[1].length;
        html.push('<h' + level + '>' + inlineFormat(heading[2]) + '</h' + level + '>');
        continue;
      }

      var ol = trimmed.match(/^\d+\.\s+(.+)$/);
      if (ol) {
        if (listType !== 'ol') {
          closeList();
          html.push('<ol>');
          listType = 'ol';
        }
        html.push('<li>' + inlineFormat(ol[1]) + '</li>');
        continue;
      }

      var ul = trimmed.match(/^[-*•]\s+(.+)$/);
      if (ul) {
        if (listType !== 'ul') {
          closeList();
          html.push('<ul>');
          listType = 'ul';
        }
        html.push('<li>' + inlineFormat(ul[1]) + '</li>');
        continue;
      }

      closeList();
      html.push('<p>' + inlineFormat(trimmed) + '</p>');
    }

    closeList();
    return html.join('') || '<p></p>';
  }

  function createMauzoAiChat(opts) {
    var messages = opts.messagesEl;
    var form = opts.formEl;
    var input = opts.inputEl;
    var sendBtn = opts.sendBtn;
    var askUrl = opts.askUrl;
    var csrf = opts.csrf;
    var avatarUrl = opts.avatarUrl;
    var variant = opts.variant || 'panel';
    var scrollParent = opts.scrollParent || messages;
    var history = clipHistory(Array.isArray(opts.initialHistory) ? opts.initialHistory : []);
    var busy = false;

    function clipHistory(items) {
      return (items || []).slice(-12).map(function (h) {
        return {
          role: h.role,
          content: String(h.content || '').slice(0, 4000)
        };
      }).filter(function (h) {
        return (h.role === 'user' || h.role === 'assistant') && h.content.length > 0;
      });
    }

    function setWorkspaceEmpty(isEmpty) {
      if (opts.workspaceEl) {
        opts.workspaceEl.classList.toggle('is-empty', !!isEmpty);
        opts.workspaceEl.classList.toggle('has-messages', !isEmpty);
      }
      if (opts.chipsEl) {
        opts.chipsEl.classList.toggle('d-none', !isEmpty);
      }
    }

    function hideEmpty() {
      if (opts.emptyStateEl) opts.emptyStateEl.classList.add('d-none');
      messages.classList.remove('d-none');
      setWorkspaceEmpty(false);
    }

    function showEmpty() {
      if (opts.emptyStateEl) opts.emptyStateEl.classList.remove('d-none');
      if (variant === 'page') {
        messages.innerHTML = '';
        messages.classList.add('d-none');
      }
      setWorkspaceEmpty(true);
    }

    function scrollDown() {
      if (scrollParent) scrollParent.scrollTop = scrollParent.scrollHeight;
      messages.scrollTop = messages.scrollHeight;
    }

    function appendRow(contentHtml, type, options) {
      options = options || {};
      hideEmpty();

      var row = document.createElement('div');
      row.className = 'ai-chat-row ai-chat-row--' + (type === 'thinking' ? 'bot' : type)
        + (variant === 'page' ? ' ai-chat-row--page' : '');

      // Full /ai page keeps avatars; floating panel stays bubble-only.
      if (variant !== 'panel' && (type === 'bot' || type === 'error' || type === 'thinking')) {
        var avatar = document.createElement('img');
        avatar.src = avatarUrl;
        avatar.alt = '';
        avatar.className = 'ai-chat-avatar';
        avatar.setAttribute('aria-hidden', 'true');
        row.appendChild(avatar);
      }

      var bubble = document.createElement('div');
      bubble.className = 'ai-chat-bubble ai-chat-bubble--' + (type === 'thinking' ? 'bot' : type)
        + (variant === 'page' ? ' ai-chat-bubble--page' : '');

      if (type === 'thinking') {
        bubble.innerHTML =
          '<div class="ai-thinking" aria-live="polite">' +
            '<span class="ai-thinking__label">Thinking</span>' +
            '<span class="ai-thinking__dots" aria-hidden="true"><span></span><span></span><span></span></span>' +
          '</div>';
      } else if (type === 'user') {
        bubble.textContent = contentHtml;
      } else {
        bubble.innerHTML = contentHtml;
      }

      if (type === 'bot' && options.showName) {
        var stack = document.createElement('div');
        stack.className = 'ai-chat-stack';
        var meta = document.createElement('div');
        meta.className = 'ai-chat-meta';
        meta.textContent = 'MauzoLink AI';
        stack.appendChild(meta);
        stack.appendChild(bubble);
        row.appendChild(stack);
      } else {
        row.appendChild(bubble);
      }

      messages.appendChild(row);
      scrollDown();
      return row;
    }

    function autosize() {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, variant === 'page' ? 160 : 96) + 'px';
    }

    function resetChat() {
      history = [];
      busy = false;
      sendBtn.disabled = false;
      if (variant === 'page') {
        showEmpty();
      } else {
        messages.innerHTML = '';
        appendRow(renderMarkdown('👋 Habari! I can train you on MauzoLink and answer sales, stock, or debt questions.'), 'bot');
      }
      input.value = '';
      autosize();
      input.focus();
    }

    function sendMessage(text) {
      text = String(text || '').trim();
      if (busy || text.length < 2) return;

      busy = true;
      sendBtn.disabled = true;
      appendRow(text, 'user');
      input.value = '';
      autosize();
      var thinking = appendRow('', 'thinking');

      var payload = {
        message: text,
        history: clipHistory(history)
      };
      if (typeof opts.getExtraPayload === 'function') {
        Object.assign(payload, opts.getExtraPayload() || {});
      }

      fetch(askUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          thinking.remove();
          if (!result.ok) {
            appendRow(escapeHtml((result.data && result.data.message) || 'Request failed.'), 'error');
            return;
          }
          var reply = (result.data && result.data.reply) || 'No reply.';
          appendRow(renderMarkdown(reply), 'bot', { showName: true });
          history.push({ role: 'user', content: text.slice(0, 1500) });
          history.push({ role: 'assistant', content: String(reply).slice(0, 4000) });
          if (history.length > 12) history = history.slice(-12);
          if (typeof opts.onReply === 'function') {
            opts.onReply(result.data || {});
          }
        })
        .catch(function () {
          thinking.remove();
          appendRow(escapeHtml('Network error. Please try again.'), 'error');
        })
        .finally(function () {
          busy = false;
          sendBtn.disabled = false;
          input.focus();
        });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      sendMessage(input.value);
    });

    input.addEventListener('input', autosize);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(input.value);
      }
    });

    if (opts.hintButtons) {
      Array.prototype.forEach.call(opts.hintButtons, function (btn) {
        btn.addEventListener('click', function () {
          sendMessage(btn.getAttribute('data-prompt') || '');
        });
      });
    }

    if (opts.newChatBtn) {
      opts.newChatBtn.addEventListener('click', function (e) {
        // Allow normal navigation when it's an <a href="/ai">
        if (opts.newChatBtn.tagName === 'A' && opts.newChatBtn.getAttribute('href')) {
          return;
        }
        e.preventDefault();
        resetChat();
      });
    }

    return { sendMessage: sendMessage, resetChat: resetChat, appendRow: appendRow };
  }

  window.MauzoAiChat = {
    create: createMauzoAiChat,
    renderMarkdown: renderMarkdown,
    escapeHtml: escapeHtml
  };
})(window);
</script>
