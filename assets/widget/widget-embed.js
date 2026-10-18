/**
 * Mteja Widget Embed (Runs inside isolated iframe)
 */
(function() {
  'use strict';

  const config = window.__MTEJA_WIDGET_CONFIG__ || {
    apiUrl: '/wp-json/mteja/v1/',
    siteName: 'Support'
  };

  let sessionId = localStorage.getItem('mteja_conv_id') || null;
  let visitorToken = localStorage.getItem('mteja_visitor_token') || null;
  let currentStatus = 'ai_handling';
  let isSending = false;
  let messages = [
    { sender_type: 'ai', content: `Hello! Welcome to ${config.siteName}. How can I assist you today?` }
  ];

  async function initSession(forceNew = false) {
    if (!visitorToken) {
      visitorToken = 'tok_' + Math.random().toString(36).substring(2, 15);
      localStorage.setItem('mteja_visitor_token', visitorToken);
    }

    if (forceNew) {
      localStorage.removeItem('mteja_conv_id');
      sessionId = null;
    }

    try {
      const resp = await fetch(config.apiUrl + 'visitor/session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          visitor_token: visitorToken,
          force_new: forceNew
        })
      });
      if (resp.ok) {
        const data = await resp.json();
        sessionId = data.id;
        currentStatus = data.status || 'ai_handling';
        localStorage.setItem('mteja_conv_id', sessionId);
        if (forceNew) {
          const inputEl = document.getElementById('widget-input');
          if (inputEl) {
            inputEl.value = '';
            inputEl.focus();
          }
          messages = [
            { sender_type: 'ai', content: `Hello! Welcome to ${config.siteName}. How can I assist you today?` }
          ];
          renderMessages();
          renderHeader();
        } else {
          await loadMessages();
        }
      }
    } catch (e) {
      console.warn('Visitor session init error', e);
    }
  }

  async function loadMessages() {
    if (!sessionId || isSending) return;
    try {
      const resp = await fetch(config.apiUrl + `visitor/messages?conversation_id=${sessionId}`);
      if (resp.ok) {
        const msgs = await resp.json();
        if (msgs && Array.isArray(msgs) && msgs.length > 0) {
          messages = msgs;
          const lastMsg = msgs[msgs.length - 1];
          if (lastMsg && lastMsg.sender_type === 'agent' && currentStatus !== 'human_active') {
            currentStatus = 'human_active';
            renderHeader();
          }
          renderMessages();
        }
      }
    } catch (e) {}
  }

  function renderHeader() {
    const headerEl = document.getElementById('widget-header');
    if (!headerEl) return;
    const isResolved = currentStatus === 'resolved';
    const isHumanWaiting = currentStatus === 'human_waiting';
    const isHumanActive = currentStatus === 'human_active';

    let statusText = 'Autonomous AI Support • Active';
    let dotColor = 'bg-emerald-500';

    if (isResolved) {
      statusText = 'Ticket Resolved';
      dotColor = 'bg-amber-400';
    } else if (isHumanActive) {
      statusText = 'Human Specialist Active';
      dotColor = 'bg-blue-500';
    } else if (isHumanWaiting) {
      statusText = 'Waiting for Support Specialist';
      dotColor = 'bg-amber-500';
    }

    headerEl.innerHTML = `
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full ${dotColor}"></span>
        <div>
          <div class="font-medium text-xs text-[#111111]">${config.siteName} Support</div>
          <div class="text-[10px] text-[#71717A]">${statusText}</div>
        </div>
      </div>
      <button 
        type="button" 
        id="widget-new-chat-btn" 
        title="Start fresh conversation" 
        class="px-2 py-1 rounded-sm text-[11px] font-medium border border-[#E5E5E2] bg-[#FAF9F6] text-[#111111] hover:bg-white hover:border-[#111111] transition-all flex items-center gap-1 cursor-pointer">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 5v14M5 12h14"></path>
        </svg>
        <span>New Chat</span>
      </button>
    `;
    bindHeaderEvents();
  }

  function render() {
    const root = document.getElementById('mteja-widget-frame-root');
    if (!root) return;

    root.innerHTML = `
      <div class="flex flex-col h-[100dvh] w-screen bg-[#f7f7f4] font-sans text-[13px] text-[#26251e]" style="touch-action: manipulation; -webkit-tap-highlight-color: transparent;">
        <!-- Top Header -->
        <header class="p-3.5 bg-[#f2f1ed] border-b border-[#cdcdc9] flex items-center justify-between" id="widget-header">
        </header>

        <!-- Message Feed -->
        <main class="flex-1 overflow-y-auto p-4 space-y-3" id="widget-msg-list" style="overscroll-behavior: contain;">
          ${renderMessageHTML()}
        </main>

        <!-- Composer -->
        <footer class="p-3 bg-[#f2f1ed] border-t border-[#cdcdc9] flex gap-2 items-center">
          <input 
            type="text" 
            id="widget-input"
            class="flex-1 px-3 py-2 rounded-sm border border-[#cdcdc9] bg-[#f7f7f4] text-xs focus:outline-none focus:border-[#26251e] focus:bg-white transition-colors"
            placeholder="Write your message..." />
          <button 
            type="button"
            id="widget-send-btn"
            style="transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1), background-color 150ms ease;"
            class="w-8 h-8 rounded-sm bg-[#26251e] text-[#f7f7f4] flex items-center justify-center hover:bg-black active:scale-[0.97] disabled:opacity-50 cursor-pointer shadow-xs">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M12 5l7 7-7 7"></path>
            </svg>
          </button>
        </footer>
      </div>
    `;

    renderHeader();
    bindEvents();
  }

  function renderMessageHTML() {
    let html = messages.map(m => {
      const isUser = m.sender_type === 'visitor';
      const senderLabel = isUser ? 'You' : (m.sender_type === 'ai' ? 'Support AI' : 'Specialist');
      return `
        <div class="flex flex-col ${isUser ? 'items-end' : 'items-start'}">
          <span class="text-[10px] font-mono text-[#7a7974] mb-0.5 px-1">${senderLabel}</span>
          <div class="max-w-[85%] p-3 rounded-sm leading-relaxed ${isUser ? 'bg-[#26251e] text-[#f7f7f4]' : 'bg-[#f2f1ed] border border-[#cdcdc9] text-[#26251e] shadow-xs'}">
            ${escapeHtml(m.content)}
          </div>
        </div>
      `;
    }).join('');

    if (currentStatus === 'resolved') {
      html += `
        <div class="flex justify-center my-2">
          <div class="px-3 py-1 rounded-sm bg-[#F4F4F0] border border-[#E5E5E2] text-[11px] text-[#71717A] flex items-center gap-1.5 font-mono">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span>Ticket resolved. Send a message to reopen or start a new chat.</span>
          </div>
        </div>
      `;
    }

    if (isSending) {
      html += `
        <div class="flex items-start">
          <div class="px-3 py-2 rounded-sm bg-white border border-[#E5E5E2] text-[#71717A] text-[11px] font-mono flex items-center gap-1.5 shadow-xs">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
            <span>Thinking...</span>
          </div>
        </div>
      `;
    }

    return html;
  }

  function renderMessages() {
    const list = document.getElementById('widget-msg-list');
    if (list) {
      list.innerHTML = renderMessageHTML();
      list.scrollTop = list.scrollHeight;
    }
  }

  function bindHeaderEvents() {
    const newChatBtn = document.getElementById('widget-new-chat-btn');
    if (newChatBtn) {
      newChatBtn.addEventListener('click', async () => {
        if (isSending) return;
        newChatBtn.disabled = true;
        newChatBtn.innerHTML = `
          <svg class="animate-spin" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
          </svg>
          <span>Creating...</span>
        `;
        try {
          await initSession(true);
        } finally {
          renderHeader();
        }
      });
    }
  }

  function bindEvents() {
    bindHeaderEvents();
    const input = document.getElementById('widget-input');
    const sendBtn = document.getElementById('widget-send-btn');

    const handleSend = async () => {
      if (isSending) return;
      const val = input.value.trim();
      if (!val) return;

      messages.push({ sender_type: 'visitor', content: val });
      isSending = true;
      if (currentStatus === 'resolved' || currentStatus === 'human_waiting') {
        currentStatus = 'ai_handling';
        renderHeader();
      }
      renderMessages();
      input.value = '';

      if (!sessionId) {
        await initSession();
      }

      if (sessionId) {
        try {
          const resp = await fetch(config.apiUrl + 'visitor/messages', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              conversation_id: sessionId,
              content: val
            })
          });
          if (resp.ok) {
            const updatedMsgs = await resp.json();
            if (updatedMsgs && Array.isArray(updatedMsgs)) {
              messages = updatedMsgs;
              const lastMsg = updatedMsgs[updatedMsgs.length - 1];
              if (lastMsg && lastMsg.sender_type === 'ai') {
                if (lastMsg.content.includes("connected you with our human support") || lastMsg.content.includes("transferred this conversation")) {
                  currentStatus = 'human_waiting';
                } else {
                  currentStatus = 'ai_handling';
                }
                renderHeader();
              } else if (lastMsg && lastMsg.sender_type === 'agent') {
                currentStatus = 'human_active';
                renderHeader();
              }
            }
          }
        } catch (e) {
          console.warn('Send message error', e);
        }
      }

      isSending = false;
      renderMessages();
    };

    if (sendBtn) sendBtn.addEventListener('click', handleSend);
    if (input) input.addEventListener('keydown', (e) => { if (e.key === 'Enter') handleSend(); });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  render();
  initSession();
  setInterval(loadMessages, 3000);

})();
