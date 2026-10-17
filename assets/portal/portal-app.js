/**
 * Mteja Operations Desk SPA
 * Warm Parchment Atelier · Cursor Design System · Emil Kowalski Motion Engineering
 */
(function() {
  'use strict';

  // Read Bootstrap configuration injected by WordPress
  const bootstrapEl = document.getElementById('mteja-bootstrap');
  const bootstrap = bootstrapEl ? JSON.parse(bootstrapEl.textContent) : {
    apiUrl: '/wp-json/mteja/v1/',
    restNonce: '',
    siteName: 'WordPress',
    portalSlug: 'mteja',
    currentUser: null,
    hasWooCommerce: false
  };

  // State Management
  const state = {
    currentTab: 'inbox', // 'inbox' | 'knowledge' | 'studio' | 'analytics' | 'integrations'
    conversations: [],
    selectedConvId: null,
    activeConversation: null,
    messages: [],
    context: null,
    composerType: 'public', // 'public' | 'whisper'
    composerText: '',
    searchQuery: '',
    queueFilter: 'escalations', // 'escalations' | 'all' | 'resolved'
    holdProgress: 0,
    isHolding: false,
    workspace: null,
    knowledgeDocs: [],
    uploadQueue: [],
    isUploadingKnowledge: false,
    studioSettings: {
      name: bootstrap.siteName + ' Support Agent',
      systemPrompt: 'You are Mteja, an autonomous customer support agent. Answer customer inquiries politely using the uploaded knowledge base.',
      threshold: 0.70,
      offlineEmail: true,
      allowedDomains: window.location.origin
    },
    livePreviewMessages: [
      { sender: 'ai', text: 'Hello! How can I assist you with ' + bootstrap.siteName + ' today?' }
    ],
    livePreviewInput: '',
    testQueryText: '',
    testQueryResult: null,
    isPolling: false,
    pollTimer: null,
    settings: null,
    settingsLoading: false,
    settingsSaving: false,
    settingsTesting: false,
    settingsTestResult: null,
    settingsSavedNotice: null,
    showSecretKey: false,
    showDbPassword: false,
    showOpenAiKey: false,
    showFreeLlmKey: false
  };

  // API Client with Nonce header
  async function api(endpoint, options = {}) {
    const url = bootstrap.apiUrl + endpoint.replace(/^\//, '');
    const headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': bootstrap.restNonce,
      ...(options.headers || {})
    };
    try {
      const resp = await fetch(url, { ...options, headers });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      return await resp.json();
    } catch (err) {
      console.warn('API error on', endpoint, err);
      return null;
    }
  }

  // Initial Data Fetching
  async function initData() {
    setupGlobalEventDelegation();

    // 1. Fetch Conversations
    const convs = await api(`conversations?filter=${state.queueFilter}&search=${encodeURIComponent(state.searchQuery)}`);
    if (convs && Array.isArray(convs)) {
      state.conversations = convs;
      if (convs.length > 0 && !state.selectedConvId) {
        state.selectedConvId = convs[0].id;
      }
    }

    // 2. Fetch Selected Conversation Messages & Context
    if (state.selectedConvId) {
      await loadConversationDetails(state.selectedConvId);
    }

    // 3. Fetch Workspace & Knowledge
    const ws = await api('workspaces');
    if (ws) {
      state.workspace = ws;
      state.studioSettings.name = ws.name || state.studioSettings.name;
      state.studioSettings.systemPrompt = ws.system_prompt || state.studioSettings.systemPrompt;
      state.studioSettings.threshold = ws.similarity_threshold || 0.70;
    }

    const docs = await api('knowledge');
    if (docs && Array.isArray(docs)) {
      state.knowledgeDocs = docs;
    }

    render();
    startPolling();
  }

  async function loadConversationDetails(id) {
    state.selectedConvId = id;
    const msgs = await api(`conversations/${id}/messages`);
    if (msgs) state.messages = msgs;
    const ctx = await api(`conversations/${id}/context`);
    if (ctx) state.context = ctx;

    const convDetail = await api(`conversations/${id}`);
    if (convDetail && convDetail.id) {
      state.activeConversation = convDetail;
      const idx = state.conversations.findIndex(c => c.id === id);
      if (idx !== -1) {
        state.conversations[idx] = { ...state.conversations[idx], ...convDetail };
      }
    } else {
      let conv = state.conversations.find(c => c.id === id);
      if (conv) {
        state.activeConversation = conv;
      }
    }

    render();
  }

  async function loadSettings() {
    if (state.settingsLoading) return;
    state.settingsLoading = true;
    render();
    const data = await api('settings');
    state.settingsLoading = false;
    if (data) {
      state.settings = data;
    }
    render();
  }

  // 3-Second Long-Polling Fallback
  function startPolling() {
    if (state.pollTimer) clearInterval(state.pollTimer);
    state.pollTimer = setInterval(async () => {
      if (state.currentTab === 'inbox') {
        const convs = await api(`conversations?filter=${state.queueFilter}&search=${encodeURIComponent(state.searchQuery)}`);
        if (convs && Array.isArray(convs)) {
          state.conversations = convs;
          if (state.selectedConvId) {
            const currentInList = convs.find(c => c.id === state.selectedConvId);
            if (currentInList) {
              state.activeConversation = currentInList;
            }
          }
        }
        if (state.selectedConvId) {
          const msgs = await api(`conversations/${state.selectedConvId}/messages`);
          if (msgs && JSON.stringify(msgs) !== JSON.stringify(state.messages)) {
            state.messages = msgs;
          }
        }
        renderFast();
      }
    }, 3000);
  }

  // =========================================================================
  // UI RENDERING ENGINE (Pure Reactive HTML Templates)
  // =========================================================================

  function render() {
    const root = document.getElementById('mteja-portal-root');
    if (!root) return;

    root.innerHTML = `
      <div class="flex h-screen w-screen overflow-hidden bg-canvas font-sans text-[13.5px] text-pitch select-none">
        ${renderNavRail()}
        <div class="flex flex-col flex-1 min-w-0 pl-16">
          ${renderTopUtilityBar()}
          <main class="flex-1 overflow-hidden pt-12 flex bg-canvas">
            ${state.currentTab === 'inbox' ? renderInboxView() : ''}
            ${state.currentTab === 'studio' ? renderStudioView() : ''}
            ${state.currentTab === 'knowledge' ? renderKnowledgeView() : ''}
            ${state.currentTab === 'analytics' ? renderPlaceholderView('Analytics & Realtime Telemetry') : ''}
            ${state.currentTab === 'integrations' ? renderPlaceholderView('Integrations, Webhooks & MCP') : ''}
            ${state.currentTab === 'settings' ? renderSettingsView() : ''}
          </main>
        </div>
      </div>
    `;

    attachEventListeners();
  }

  // Fast patch for polling (updates ticket list, message list, and conversation header to avoid scroll jumps)
  function renderFast() {
    const queueFeed = document.getElementById('queue-feed-container');
    if (queueFeed) {
      queueFeed.innerHTML = renderQueueCards();
      attachQueueEvents();
    }
    const msgFeed = document.getElementById('messages-feed-container');
    if (msgFeed) {
      msgFeed.innerHTML = renderMessageItems();
    }
    const headerContainer = document.getElementById('active-conversation-header-container');
    if (headerContainer && state.selectedConvId) {
      const selectedConv = state.conversations.find(c => c.id === state.selectedConvId) || state.activeConversation;
      if (selectedConv) {
        headerContainer.innerHTML = renderActiveConversationHeader(selectedConv);
      }
    }
  }

  // 1. COLLAPSED ICON-ONLY NAVIGATION RAIL (64px)
  function renderNavRail() {
    const isOwner = Boolean(bootstrap.currentUser && (bootstrap.currentUser.isOwner || bootstrap.currentUser.canManage || bootstrap.currentUser.role === 'administrator'));
    const tabs = [
      { id: 'inbox', icon: 'inbox', label: 'Inbox', badge: true },
      { id: 'knowledge', icon: 'menu_book', label: 'Knowledge Base' },
      { id: 'studio', icon: 'forum', label: 'Agent Studio & Live Preview' },
      { id: 'analytics', icon: 'insights', label: 'Analytics' },
      { id: 'integrations', icon: 'hub', label: 'Integrations & Webhooks' }
    ];

    if (isOwner) {
      tabs.push({ id: 'settings', icon: 'settings', label: 'Platform & Supabase Settings (Owner Only)' });
    }

    return `
      <aside class="fixed left-0 top-0 h-screen w-16 bg-surface border-r border-border z-50 flex flex-col justify-between items-center py-3">
        <div class="flex flex-col items-center gap-4 w-full">
          <!-- Brand Badge -->
          <a class="w-10 h-10 rounded-sm bg-pitch flex items-center justify-center text-white hover:bg-black transition-all shadow-sm" href="#" title="Mteja Desk">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" viewBox="0 0 24 24">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
              <path d="M8 10h.01M12 10h.01M16 10h.01" stroke-width="2.8"></path>
            </svg>
          </a>
          <!-- Nav Icons -->
          <nav class="flex flex-col gap-1 items-center w-full px-2 pt-2 border-t border-border/60">
            ${tabs.map(t => `
              <button 
                class="nav-tab-btn relative w-10 h-10 rounded-sm flex items-center justify-center transition-all ${state.currentTab === t.id ? 'bg-pitch text-white shadow-sm' : 'text-muted hover:bg-surface-muted hover:text-pitch'}" 
                data-tab="${t.id}" 
                title="${t.label}">
                <span class="material-symbols-outlined text-[19px]">${t.icon}</span>
                ${t.badge ? `<span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-emerald-500"></span>` : ''}
              </button>
            `).join('')}
          </nav>
        </div>
        <!-- Bottom Rail -->
        <div class="flex flex-col items-center gap-2 w-full px-2 border-t border-border/60 pt-3">
          <div class="w-8 h-8 rounded-sm bg-surface-muted border border-border flex items-center justify-center text-emerald-600 cursor-pointer" title="Autonomous Engine Active (99.4% SLA)">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          </div>
          <div class="w-8 h-8 rounded-sm bg-surface-muted border border-border flex items-center justify-center text-pitch font-medium text-[11px] font-mono" title="${bootstrap.currentUser.displayName}">
            ${(bootstrap.currentUser.displayName || 'AD').substring(0, 2).toUpperCase()}
          </div>
        </div>
      </aside>
    `;
  }

  // 2. TOP UTILITY BAR (48px)
  function renderTopUtilityBar() {
    return `
      <header class="fixed top-0 right-0 left-16 h-12 bg-surface/90 backdrop-blur-md border-b border-border z-40 flex items-center justify-between px-5 text-xs">
        <div class="flex items-center gap-4 flex-1 max-w-xl">
          <!-- Env Switcher -->
          <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-sm border border-border bg-surface-subtle text-pitch font-mono text-[11.5px]">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span class="font-medium">${bootstrap.siteName.toLowerCase().replace(/[^a-z0-9]/g, '-')}-live</span>
            <span class="material-symbols-outlined text-[14px] text-muted ml-0.5">unfold_more</span>
          </div>
          <!-- Global Search -->
          <div class="relative flex-1">
            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[15px] text-muted">search</span>
            <input 
              class="w-full h-7 pl-8 pr-12 rounded-sm border border-border bg-surface text-pitch placeholder:text-muted font-sans text-[12px] focus:outline-none focus:border-pitch transition-colors" 
              placeholder="Search conversations, telemetry, docs..." 
              value="${state.searchQuery}"
              id="top-search-input"
              type="text"/>
            <kbd class="absolute right-2 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] font-mono rounded-sm bg-surface-subtle border border-border text-muted">⌘K</kbd>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <!-- Online Indicator -->
          <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-sm border border-border bg-surface text-[11.5px]">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span class="font-medium text-pitch">Online & Polling</span>
          </div>
          <!-- Profile Pill -->
          <div class="flex items-center gap-2 pl-1">
            <div class="w-6 h-6 rounded-sm bg-pitch text-white flex items-center justify-center font-mono text-[10px]">
              ${(bootstrap.currentUser.displayName || 'AD').substring(0, 2).toUpperCase()}
            </div>
            <span class="hidden lg:inline text-[12px] font-medium text-pitch">${bootstrap.currentUser.displayName}</span>
          </div>
        </div>
      </header>
    `;
  }

  // 3. INBOX VIEW (3-PANE)
  function renderInboxView() {
    const selectedConv = state.conversations.find(c => c.id === state.selectedConvId)
      || state.activeConversation
      || (state.conversations.length > 0 ? state.conversations[0] : null);

    return `
      <div class="flex h-full w-full overflow-hidden">
        <!-- PANE 1: Queue List (330px) -->
        <section class="w-[330px] flex-shrink-0 flex flex-col bg-surface border-r border-border z-10">
          <div class="p-3 border-b border-border flex flex-col gap-2.5 bg-surface">
            <div class="flex items-center justify-between pb-0.5">
              <span class="text-[13.5px] font-semibold tracking-tight text-pitch">Inbox</span>
              <span class="text-[11px] font-mono text-muted">${state.conversations.length} tickets</span>
            </div>
            <!-- Filter Tabs -->
            <div class="flex gap-1">
              ${['escalations', 'all', 'resolved'].map(f => `
                <button 
                  class="filter-tab-btn px-2.5 py-1 rounded-sm text-[11.5px] font-medium transition-colors ${state.queueFilter === f ? 'bg-pitch text-white' : 'text-muted hover:text-pitch'}" 
                  data-filter="${f}">
                  ${f.charAt(0).toUpperCase() + f.slice(1)}
                </button>
              `).join('')}
            </div>
          </div>
          <!-- Queue Feed -->
          <div class="flex-1 overflow-y-auto flex flex-col divide-y divide-border" id="queue-feed-container">
            ${renderQueueCards()}
          </div>
        </section>

        <!-- PANE 2: Active Thread Stage (Fluid) -->
        <main class="flex-1 flex flex-col bg-canvas min-w-0 border-r border-border">
          <div id="active-conversation-header-container">
            ${selectedConv ? renderActiveConversationHeader(selectedConv) : '<div class="p-6 text-muted">Select a conversation</div>'}
          </div>
          
          <!-- Message Feed -->
          <section class="flex-1 overflow-y-auto p-5 space-y-4" id="messages-feed-container">
            ${renderMessageItems()}
          </section>

          <!-- Response Composer Dock -->
          ${renderComposerDock()}
        </main>

        <!-- PANE 3: Dynamic Context Rail (310px) -->
        <aside class="w-[310px] flex-shrink-0 flex flex-col bg-surface overflow-y-auto p-4 gap-5">
          ${renderContextInspector(selectedConv)}
        </aside>
      </div>
    `;
  }

  function renderQueueCards() {
    if (state.conversations.length === 0) {
      return `<div class="p-6 text-center text-muted text-xs">No conversations found</div>`;
    }

    return state.conversations.map(c => {
      const isSelected = c.id === state.selectedConvId;
      const isEscalated = c.status === 'human_waiting' || c.status === 'human_active';
      const name = c.visitor_email ? c.visitor_email.split('@')[0] : 'Visitor #' + (c.visitor_token || '8942').substring(0, 4);

      return `
        <div 
          class="queue-card p-3 ${isSelected ? 'bg-surface-subtle border-l-2 border-pitch' : 'bg-surface hover:bg-surface-subtle'} cursor-pointer transition-colors flex flex-col gap-1"
          data-id="${c.id}">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-4 h-4 rounded-sm bg-surface-muted border border-border flex items-center justify-center font-mono text-[9px]">
                ${name.substring(0, 1).toUpperCase()}
              </span>
              <span class="text-[12.5px] font-medium text-pitch">${name}</span>
            </div>
            <div class="flex items-center gap-1">
              ${isEscalated ? `<span class="w-1.5 h-1.5 rounded-full bg-amber-action" title="Escalated"></span>` : ''}
              <span class="text-[11px] text-muted font-mono">1m</span>
            </div>
          </div>
          <p class="text-[12px] ${isSelected ? 'text-charcoal-text font-medium' : 'text-muted'} line-clamp-1 leading-relaxed">
            ${c.last_message || 'Customer initiated chat'}
          </p>
          <div class="flex items-center justify-between text-[10.5px] text-muted pt-0.5">
            <span>${(c.geo_city || c.geo_country) ? [c.geo_city, c.geo_country].filter(Boolean).join(', ') : 'Location unknown'}</span>
            <span class="font-mono uppercase text-[9.5px] px-1 py-0.2 rounded-sm bg-surface-subtle border border-border">
              ${c.status.replace('_', ' ')}
            </span>
          </div>
        </div>
      `;
    }).join('');
  }

  function renderActiveConversationHeader(conv) {
    const isEscalated = conv.status === 'human_waiting';
    const isHumanActive = conv.status === 'human_active';
    const isResolved = conv.status === 'resolved';

    return `
      <header class="px-5 py-3 bg-surface border-b border-border flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="w-7 h-7 rounded-sm bg-pitch text-white flex items-center justify-center font-mono text-xs">
              ${(conv.visitor_email || 'V').substring(0, 1).toUpperCase()}
            </span>
            <div class="flex items-center gap-2">
              <span class="text-[13.5px] font-medium text-pitch">${conv.visitor_email || 'Visitor #' + (conv.visitor_token || '').substring(0, 6)}</span>
              <span class="text-muted">·</span>
              <span class="px-1.5 py-0.2 rounded-sm ${isResolved ? 'bg-emerald-50 text-emerald-800 border border-emerald-300 font-semibold' : 'bg-surface-subtle border border-border text-muted'} text-[11px] font-mono">
                ${conv.status}
              </span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            ${isResolved ? `
              <button 
                type="button"
                class="interactive-btn px-3 py-1 rounded-sm bg-pitch text-white hover:bg-black font-medium text-[12px] flex items-center gap-1 transition-all shadow-xs cursor-pointer" 
                id="btn-reopen">
                <span class="material-symbols-outlined text-[14px]">refresh</span>
                <span>Reopen Ticket</span>
              </button>
            ` : `
              ${!isHumanActive ? `
                <button 
                  type="button"
                  class="interactive-btn px-3 py-1 rounded-sm bg-amber-action text-white hover:opacity-90 font-medium text-[12px] flex items-center gap-1 transition-all shadow-xs cursor-pointer" 
                  id="btn-takeover">
                  <span class="material-symbols-outlined text-[14px]">pan_tool</span>
                  <span>Takeover Chat</span>
                </button>
              ` : ''}
              ${conv.status !== 'ai_handling' ? `
                <button 
                  type="button"
                  class="interactive-btn px-3 py-1 rounded-sm bg-white text-pitch border border-border hover:bg-surface-subtle font-medium text-[12px] flex items-center gap-1.5 transition-all shadow-xs cursor-pointer" 
                  id="btn-handover-ai"
                  title="Transfer chat back to Autonomous AI assistant">
                  <span class="material-symbols-outlined text-[14px] text-emerald-600">smart_toy</span>
                  <span>Handover to AI</span>
                </button>
              ` : ''}
              <button 
                type="button"
                class="interactive-btn px-3 py-1 rounded-sm bg-forest-action text-white hover:opacity-90 font-medium text-[12px] flex items-center gap-1 transition-all shadow-xs cursor-pointer" 
                id="btn-resolve">
                <span class="material-symbols-outlined text-[14px]">check</span>
                <span>Resolve Ticket</span>
              </button>
            `}
          </div>
        </div>
        ${isEscalated ? `
          <div class="px-3 py-1.5 rounded-sm bg-amber-50 border border-amber-200 text-[11.5px] text-amber-900 flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-action animate-pulse"></span>
            <span>Customer requested human assistance. AI paused waiting for operator intervention.</span>
          </div>
        ` : ''}
        ${isResolved ? `
          <div class="px-3 py-1.5 rounded-sm bg-emerald-50 border border-emerald-200 text-[11.5px] text-emerald-900 flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
            <span>This ticket is resolved. New incoming visitor messages or clicking Reopen will return it to active queues.</span>
          </div>
        ` : ''}
      </header>
    `;
  }

  function renderMessageItems() {
    if (state.messages.length === 0) {
      return `<div class="p-8 text-center text-muted text-xs">Loading thread messages...</div>`;
    }

    let html = state.messages.map(m => {
      const isVisitor = m.sender_type === 'visitor';
      const isAI = m.sender_type === 'ai';
      const isWhisper = m.sender_type === 'whisper';
      const isAgent = m.sender_type === 'agent';

      if (isVisitor) {
        return `
          <div class="flex items-start gap-3 justify-end">
            <div class="max-w-[70%] bg-ink text-white rounded-sm p-3.5 text-[13px] leading-relaxed shadow-xs">
              ${escapeHtml(m.content)}
              <div class="text-[10px] text-white/50 text-right mt-1 font-mono">${(m.created_at || '').substring(11, 16)}</div>
            </div>
          </div>
        `;
      }

      if (isAI) {
        return `
          <div class="flex items-start gap-3">
            <div class="w-6 h-6 rounded-sm bg-surface border border-border flex items-center justify-center text-pitch shrink-0 font-medium text-[10px] font-mono mt-1">
              AI
            </div>
            <div class="max-w-[80%] bg-surface border border-border rounded-sm p-4 text-[13px] leading-relaxed text-charcoal-text space-y-2 shadow-xs">
              <div class="flex items-center justify-between text-[11px] text-muted pb-1 border-b border-border/60">
                <span class="font-medium text-pitch">${bootstrap.siteName} Autonomous AI</span>
                <span class="font-mono text-[10px] text-emerald-600">RAG Verified (0.70)</span>
              </div>
              <div>${escapeHtml(m.content)}</div>
            </div>
          </div>
        `;
      }

      if (isWhisper) {
        return `
          <div class="p-3 rounded-sm bg-amber-50/60 border border-amber-200 text-amber-900 text-[12px] flex items-start gap-2">
            <span class="material-symbols-outlined text-[15px] text-amber-700 mt-0.5">lock</span>
            <div class="flex-1">
              <div class="font-semibold text-[11px] uppercase tracking-wider text-amber-800">Internal Whisper Note</div>
              <div>${escapeHtml(m.content)}</div>
            </div>
          </div>
        `;
      }

      // Human Agent Reply
      return `
        <div class="flex items-start gap-3 justify-end">
          <div class="max-w-[70%] bg-[#E6E5E0] text-ink border border-border rounded-sm p-3.5 text-[13px] leading-relaxed shadow-xs">
            <div class="text-[10px] text-muted uppercase font-mono mb-1">Human Agent (${bootstrap.currentUser.displayName})</div>
            ${escapeHtml(m.content)}
            <div class="text-[10px] text-muted text-right mt-1 font-mono">${(m.created_at || '').substring(11, 16)}</div>
          </div>
        </div>
      `;
    }).join('');

    // Determine AI Suggested Draft dynamically based on conversation history
    let suggestedDraftText = "Hi! I've reviewed your request and verified your account details. I am taking over directly to make sure this is resolved for you immediately.";
    if (state.messages && state.messages.length > 0) {
      const visitorMessages = state.messages.filter(m => m.sender_type === 'visitor');
      if (visitorMessages.length > 0) {
        const lastVisitorMsg = visitorMessages[visitorMessages.length - 1].content.trim();
        const lower = lastVisitorMsg.toLowerCase();
        if (lower.includes('return') || lower.includes('refund')) {
          suggestedDraftText = "Hello! I'd be happy to assist you with your return. You can return any item within 30 days of purchase for a 100% full refund with zero restocking fees. Please let me know your order number to get started!";
        } else if (lower.includes('order') || lower.includes('track') || lower.includes('shipping') || lower.includes('delivery')) {
          suggestedDraftText = "Hello! I am checking your order and shipping status right now. Could you please share your order number or the email associated with your purchase?";
        } else if (lower.includes('human') || lower.includes('agent') || lower.includes('person') || lower.includes('help')) {
          suggestedDraftText = "Hello! I am a live support specialist taking over from our automated assistant. How can I assist you with your inquiry today?";
        } else if (lastVisitorMsg.length > 5) {
          suggestedDraftText = `Hello! Thank you for reaching out. I've reviewed your question regarding "${escapeHtml(lastVisitorMsg.substring(0, 60))}${lastVisitorMsg.length > 60 ? '...' : ''}" and I'm here to help you get this resolved right away.`;
        }
      }
    }

    // AI Suggested Draft Card (Stitch feature)
    html += `
      <div class="p-3.5 rounded-sm bg-surface border border-border shadow-xs flex flex-col gap-2 mt-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[15px] text-pitch">auto_awesome</span>
            <span class="text-[12px] font-medium text-pitch">AI Suggested Draft</span>
          </div>
          <button 
            type="button"
            class="interactive-btn px-2 py-0.5 rounded-sm bg-pitch text-white hover:bg-black text-[11px] font-medium flex items-center gap-1 cursor-pointer transition-all active:scale-95" 
            id="btn-insert-suggested-draft">
            <span class="material-symbols-outlined text-[12px]">edit_note</span>
            <span>Insert Draft</span>
          </button>
        </div>
        <div class="text-[12.5px] text-charcoal-text leading-relaxed font-sans" id="suggested-draft-content">
          ${escapeHtml(suggestedDraftText)}
        </div>
      </div>
    `;

    return html;
  }

  function renderComposerDock() {
    return `
      <footer class="p-3.5 bg-surface border-t border-border flex flex-col gap-2">
        <!-- Composer Mode Tabs -->
        <div class="flex items-center justify-between">
          <div class="flex gap-1">
            <button 
              class="composer-mode-btn px-2.5 py-1 rounded-sm text-[11.5px] font-medium transition-colors ${state.composerType === 'public' ? 'bg-pitch text-white' : 'text-muted hover:text-pitch'}" 
              data-mode="public">
              Public Reply
            </button>
            <button 
              class="composer-mode-btn px-2.5 py-1 rounded-sm text-[11.5px] font-medium transition-colors ${state.composerType === 'whisper' ? 'bg-amber-action text-white' : 'text-muted hover:text-pitch'}" 
              data-mode="whisper">
              Internal Whisper Note
            </button>
          </div>
          <div class="text-[11px] text-muted font-mono">
            Type <span class="bg-surface-subtle px-1 border border-border rounded-sm">/</span> for macros
          </div>
        </div>
        
        <!-- Textarea Card -->
        <div class="flex flex-col rounded-sm border border-border bg-white p-2.5 focus-within:border-pitch transition-colors">
          <textarea 
            class="w-full bg-transparent resize-none text-pitch placeholder:text-muted font-sans text-[13px] focus:outline-none" 
            id="composer-textarea"
            rows="3" 
            placeholder="${state.composerType === 'public' ? 'Type your response to the customer...' : 'Write an internal note visible only to support staff...'}">${state.composerText}</textarea>
          
          <div class="flex items-center justify-between pt-2 border-t border-border/60 mt-1">
            <div class="flex items-center gap-1.5 text-muted">
              <button type="button" class="quick-snippet-btn p-1 rounded-sm hover:text-pitch cursor-pointer flex items-center gap-1" data-snippet="/aisupport" title="Quick Snippet: /aisupport (Transfer chat back to Autonomous AI)">
                <span class="material-symbols-outlined text-[13px] text-emerald-600">smart_toy</span>
                <span class="font-mono text-xs font-semibold text-emerald-800 bg-emerald-50 px-1 py-0.2 rounded-xs border border-emerald-200">/aisupport</span>
              </button>
              <button type="button" class="quick-snippet-btn p-1 rounded-sm hover:text-pitch cursor-pointer" data-snippet="I have processed your refund." title="Quick Snippet: /refund">
                <span class="font-mono text-xs font-semibold">/refund</span>
              </button>
              <button type="button" class="quick-snippet-btn p-1 rounded-sm hover:text-pitch cursor-pointer" data-snippet="I have escalated this to engineering." title="Quick Snippet: /escalate">
                <span class="font-mono text-xs font-semibold">/escalate</span>
              </button>
            </div>
            <button 
              class="interactive-btn px-4 py-1.5 rounded-sm font-medium text-[12px] text-white flex items-center gap-1.5 shadow-sm transition-all ${state.composerType === 'public' ? 'bg-pitch hover:bg-black' : 'bg-amber-action hover:opacity-90'}" 
              id="btn-send-reply">
              <span>${state.composerType === 'public' ? 'Send Reply' : 'Save Whisper Note'}</span>
              <span class="material-symbols-outlined text-[13px]">send</span>
            </button>
          </div>
        </div>
      </footer>
    `;
  }

  function renderContextInspector(conv) {
    if (!conv) {
      return `<div class="text-xs text-muted">No conversation context</div>`;
    }

    const ctx = state.context || { customer: {}, hasWooCommerce: bootstrap.hasWooCommerce, wooCommerceOrders: [] };

    return `
      <div class="space-y-4">
        <div class="border-b border-border pb-2 flex items-center justify-between">
          <span class="text-[11.5px] font-semibold text-pitch tracking-tight">Context Inspector</span>
          <span class="text-[10px] font-mono text-muted uppercase">${conv.status}</span>
        </div>

        <!-- Customer Card -->
        <div class="space-y-2">
          <div class="text-[11px] font-medium text-muted uppercase tracking-wider">Visitor Profile</div>
          <div class="p-3 rounded-sm bg-surface-subtle border border-border space-y-2 text-[12px]">
            <div class="flex justify-between">
              <span class="text-muted">Contact</span>
              <span class="font-medium text-pitch">${conv.visitor_email || 'Anonymous'}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted">Location</span>
              <span class="text-pitch">${(conv.geo_city || conv.geo_country) ? [conv.geo_city, conv.geo_country].filter(Boolean).join(', ') : 'Unknown'}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted">Visitor Token</span>
              <span class="font-mono text-[10px] text-muted">${(conv.visitor_token || '').substring(0, 10)}...</span>
            </div>
          </div>
        </div>

        <!-- WooCommerce Customer Orders (Native integration) -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-muted uppercase tracking-wider">WooCommerce Store</span>
            <span class="text-[10px] font-mono text-emerald-600">${ctx.hasWooCommerce ? 'Active' : 'Not Detected'}</span>
          </div>
          ${ctx.hasWooCommerce && ctx.wooCommerceOrders && ctx.wooCommerceOrders.length > 0 ? `
            <div class="space-y-1.5">
              ${ctx.wooCommerceOrders.map(o => `
                <div class="p-2.5 rounded-sm bg-surface-subtle border border-border text-[11.5px]">
                  <div class="flex justify-between font-medium">
                    <span>Order #${o.id}</span>
                    <span>${o.total}</span>
                  </div>
                  <div class="flex justify-between text-muted text-[10.5px] mt-0.5">
                    <span>${o.date}</span>
                    <span class="uppercase">${o.status}</span>
                  </div>
                </div>
              `).join('')}
            </div>
          ` : `
            <div class="p-3 rounded-sm bg-surface-subtle border border-border text-[11.5px] text-muted">
              ${ctx.hasWooCommerce ? 'No prior orders for this email.' : 'WooCommerce is not installed on this WordPress site. Lead email and telemetry captured natively.'}
            </div>
          `}
        </div>

        <!-- Escalation Summary -->
        <div class="space-y-2">
          <div class="text-[11px] font-medium text-muted uppercase tracking-wider">Escalation Summary</div>
          <div class="p-3 rounded-sm bg-surface-subtle border border-border text-[12px] text-charcoal-text leading-relaxed">
            Autonomous RAG confidence was triggered. Human specialist intervention assigned.
          </div>
        </div>
      </div>
    `;
  }

  // 4. AGENT STUDIO & LIVE PREVIEW VIEW
  function renderStudioView() {
    const embedCode = `<script src="${bootstrap.siteUrl}/wp-content/plugins/mteja/assets/widget/widget.js" data-workspace-id="${bootstrap.workspaceId}" async defer></script>`;

    return `
      <div class="flex h-full w-full overflow-hidden bg-canvas">
        <!-- Left: Configuration Form -->
        <div class="w-[520px] flex-shrink-0 flex flex-col bg-surface border-r border-border overflow-y-auto p-6 space-y-6">
          <div>
            <h2 class="text-xl font-medium tracking-tight text-pitch">Agent Studio & Tuning</h2>
            <p class="text-xs text-muted mt-1">Configure bot persona, similarity threshold, and allowed domains.</p>
          </div>

          <div class="space-y-4">
            <!-- Bot Name -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-pitch">Bot Name</label>
              <input 
                class="w-full h-8 px-3 rounded-sm border border-border bg-white text-pitch text-xs focus:outline-none focus:border-pitch" 
                id="studio-name-input"
                value="${escapeHtml(state.studioSettings.name)}" />
            </div>

            <!-- Persona Prompt (Markdown) -->
            <div class="space-y-1.5">
              <div class="flex justify-between items-center">
                <label class="text-xs font-semibold text-pitch">Persona & Boundaries (System Prompt)</label>
                <span class="text-[10px] font-mono text-muted">Markdown Supported</span>
              </div>
              <textarea 
                class="w-full h-32 p-3 rounded-sm border border-border bg-white text-pitch font-sans text-xs focus:outline-none focus:border-pitch resize-none leading-relaxed" 
                id="studio-prompt-input">${escapeHtml(state.studioSettings.systemPrompt)}</textarea>
            </div>

            <!-- Threshold Slider -->
            <div class="p-3.5 rounded-sm bg-surface-subtle border border-border space-y-2">
              <div class="flex justify-between items-center text-xs">
                <span class="font-semibold text-pitch">Cosine Similarity Threshold</span>
                <span class="font-mono text-xs font-bold text-pitch" id="threshold-display">${Number(state.studioSettings.threshold).toFixed(2)}</span>
              </div>
              <input 
                type="range" min="0.50" max="0.95" step="0.05" 
                value="${state.studioSettings.threshold}" 
                id="studio-threshold-slider"
                class="w-full cursor-pointer accent-pitch" />
              <div class="flex justify-between text-[10px] text-muted font-mono">
                <span>0.50 (Loose)</span>
                <span>0.70 (Recommended)</span>
                <span>0.95 (Strict)</span>
              </div>
            </div>

            <!-- Embed Code Generator -->
            <div class="space-y-1.5">
              <div class="flex justify-between items-center">
                <label class="text-xs font-semibold text-pitch">Embed Snippet (External Websites)</label>
                <button class="text-xs text-muted hover:text-pitch font-medium" onclick="navigator.clipboard.writeText('${escapeHtml(embedCode)}'); alert('Embed snippet copied!');">Copy Snippet</button>
              </div>
              <div class="p-2.5 rounded-sm bg-surface-subtle border border-border font-mono text-[11px] text-charcoal-text break-all">
                ${escapeHtml(embedCode)}
              </div>
            </div>

            <button 
              class="interactive-btn w-full py-2 rounded-sm bg-pitch text-white font-medium text-xs hover:bg-black transition-all shadow-sm"
              id="btn-save-studio">
              Save Configuration
            </button>
          </div>
        </div>

        <!-- Right: Interactive Live Sandbox Preview -->
        <div class="flex-1 flex flex-col items-center justify-center p-8 bg-canvas">
          <div class="w-[380px] h-[580px] bg-white border border-border rounded-sm shadow-xl flex flex-col overflow-hidden">
            <!-- Sandbox Header -->
            <div class="p-3.5 bg-pitch text-white flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span class="font-medium text-xs">${state.studioSettings.name}</span>
              </div>
              <span class="text-[10px] font-mono text-white/60">Live Preview</span>
            </div>

            <!-- Sandbox Message Stream -->
            <div class="flex-1 p-4 overflow-y-auto space-y-3 bg-canvas text-xs" id="sandbox-messages">
              ${state.livePreviewMessages.map(m => `
                <div class="flex ${m.sender === 'user' ? 'justify-end' : 'justify-start'}">
                  <div class="max-w-[85%] p-3 rounded-sm ${m.sender === 'user' ? 'bg-pitch text-white' : 'bg-white border border-border text-pitch'}">
                    ${escapeHtml(m.text)}
                  </div>
                </div>
              `).join('')}
            </div>

            <!-- Sandbox Input -->
            <div class="p-2.5 border-t border-border bg-white flex gap-2">
              <input 
                class="flex-1 px-3 py-1.5 rounded-sm border border-border text-xs focus:outline-none" 
                placeholder="Ask preview bot..." 
                id="sandbox-input" />
              <button 
                class="px-3 py-1.5 rounded-sm bg-pitch text-white text-xs font-medium"
                id="sandbox-send-btn">
                Send
              </button>
            </div>
          </div>
          <div class="text-[11px] text-muted mt-3 font-mono">
            Preview reflects changes in real-time.
          </div>
        </div>
      </div>
    `;
  }

  // 5. KNOWLEDGE BASE & INGESTION VIEW
  function renderKnowledgeView() {
    const totalQueued = state.uploadQueue.length;
    const completedQueued = state.uploadQueue.filter(q => q.stage === 'completed').length;
    const totalProgress = totalQueued > 0
      ? Math.round((state.uploadQueue.reduce((acc, q) => acc + (q.progress || 0), 0) / (totalQueued * 100)) * 100)
      : 0;

    return `
      <div class="flex-1 overflow-y-auto p-8 max-w-6xl mx-auto space-y-6 w-full">
        <div class="flex justify-between items-end pb-2 border-b border-border">
          <div>
            <h2 class="text-xl font-medium tracking-tight text-pitch">Knowledge Ingestion Hub</h2>
            <p class="text-xs text-muted mt-1">Upload company documentation, policies, and product guides for deterministic RAG retrieval.</p>
          </div>
          <div class="text-xs font-mono text-muted">
            Vector Store: Supabase pgvector
          </div>
        </div>

        <!-- Upload Dropzone (Multi-File Drag & Drop) -->
        <div class="p-8 border-2 border-dashed border-border hover:border-pitch rounded-sm bg-surface flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-150 group" id="kb-dropzone">
          <div class="w-12 h-12 rounded-sm bg-surface-subtle border border-border group-hover:border-pitch flex items-center justify-center text-pitch mb-3 shadow-xs transition-colors">
            <span class="material-symbols-outlined text-2xl">upload_file</span>
          </div>
          <div class="font-medium text-[13px] text-pitch">Click to select files or drag & drop multiple files</div>
          <div class="text-[11px] text-muted mt-1 max-w-md leading-relaxed">
            Upload multiple documents simultaneously. Each document is parsed, chunked, and vectorized in Supabase.
          </div>
          <div class="flex flex-wrap justify-center items-center gap-1.5 mt-3">
            <span class="px-2 py-0.5 rounded-xs bg-surface-subtle border border-border text-[10px] font-mono text-muted">.MD</span>
            <span class="px-2 py-0.5 rounded-xs bg-surface-subtle border border-border text-[10px] font-mono text-muted">.PDF</span>
            <span class="px-2 py-0.5 rounded-xs bg-surface-subtle border border-border text-[10px] font-mono text-muted">.TXT</span>
            <span class="px-2 py-0.5 rounded-xs bg-surface-subtle border border-border text-[10px] font-mono text-muted">.HTML</span>
            <span class="px-2 py-0.5 rounded-xs bg-surface-subtle border border-border text-[10px] font-mono text-muted">.JSON</span>
            <span class="px-2 py-0.5 rounded-xs bg-surface-subtle border border-border text-[10px] font-mono text-muted">.CSV</span>
          </div>
          <input type="file" id="kb-file-input" class="hidden" accept=".pdf,.md,.txt,.html,.htm,.json,.csv" multiple />
        </div>

        <!-- Active Ingestion Queue & Real-Time Progress -->
        ${totalQueued > 0 ? `
          <div class="bg-surface border border-border rounded-sm overflow-hidden p-4 space-y-3 shadow-xs" id="kb-upload-queue-container">
            <div class="flex items-center justify-between border-b border-border/80 pb-2.5">
              <div class="flex items-center gap-2">
                ${state.isUploadingKnowledge ? `
                  <span class="material-symbols-outlined text-[18px] text-amber-700 animate-spin">sync</span>
                ` : `
                  <span class="material-symbols-outlined text-[18px] text-forest-action">task_alt</span>
                `}
                <span class="font-medium text-xs text-pitch">
                  ${state.isUploadingKnowledge ? 'Processing Ingestion Queue' : 'Ingestion Queue Summary'}: ${completedQueued} of ${totalQueued} files complete
                </span>
              </div>
              <div class="flex items-center gap-3">
                <span class="font-mono text-xs font-semibold text-pitch" id="kb-overall-percent">${totalProgress}%</span>
                ${!state.isUploadingKnowledge ? `
                  <button type="button" class="text-xs text-muted hover:text-pitch font-medium cursor-pointer" id="btn-clear-upload-queue">
                    Clear Queue
                  </button>
                ` : ''}
              </div>
            </div>

            <!-- Master Overall Batch Progress Bar -->
            <div class="w-full bg-surface-subtle border border-border rounded-xs h-2 overflow-hidden">
              <div 
                class="h-full bg-pitch transition-all duration-300 ease-out" 
                id="kb-overall-bar" 
                style="width: ${totalProgress}%">
              </div>
            </div>

            <!-- Individual File Progress Cards -->
            <div class="space-y-2 pt-1" id="kb-queue-items-list">
              ${state.uploadQueue.map(item => `
                <div class="p-3 rounded-sm bg-surface-subtle border border-border flex flex-col gap-2 queue-file-item" id="queue-item-${item.id}">
                  <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="material-symbols-outlined text-[16px] text-muted shrink-0">
                        ${item.name.endsWith('.pdf') ? 'picture_as_pdf' : (item.name.endsWith('.md') ? 'markdown' : 'description')}
                      </span>
                      <span class="font-medium text-pitch truncate max-w-sm" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</span>
                      <span class="font-mono text-[10px] text-muted shrink-0">(${(item.size / 1024).toFixed(1)} KB)</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                      <span class="font-mono text-[11px] status-label ${item.stage === 'completed' ? 'text-forest-action font-semibold' : (item.stage === 'error' ? 'text-red-600 font-semibold' : 'text-amber-800')}">
                        ${item.stage === 'uploading' ? `Uploading ${item.progress}%` : ''}
                        ${item.stage === 'processing' ? `Extracting & Vectorizing...` : ''}
                        ${item.stage === 'completed' ? `✓ Synced (${item.chunkCount || 0} chunks, ${item.vectorCount || 0} vectors)` : ''}
                        ${item.stage === 'error' ? `✕ Failed: ${escapeHtml(item.error || 'Error')}` : ''}
                      </span>
                      <span class="font-mono text-[11px] text-muted font-bold percent-label">${item.progress}%</span>
                    </div>
                  </div>
                  <!-- Individual File Progress Bar Track -->
                  <div class="w-full bg-white border border-border rounded-xs h-1.5 overflow-hidden">
                    <div 
                      class="h-full transition-all duration-200 ease-out progress-bar-fill ${item.stage === 'completed' ? 'bg-forest-action' : (item.stage === 'error' ? 'bg-red-500' : 'bg-amber-action')}" 
                      style="width: ${item.progress}%">
                    </div>
                  </div>
                </div>
              `).join('')}
            </div>
          </div>
        ` : ''}

        <!-- Document Table -->
        <div class="bg-surface border border-border rounded-sm overflow-hidden shadow-xs">
          <div class="px-4 py-3 border-b border-border bg-surface-subtle font-medium text-xs text-pitch flex justify-between">
            <span>Indexed Documents (${state.knowledgeDocs.length})</span>
            <span class="font-mono text-[11px] text-muted">Auto-chunked in 50-batch requests</span>
          </div>
          <table class="w-full text-left text-xs">
            <thead class="border-b border-border text-[11px] font-mono uppercase text-muted bg-surface">
              <tr>
                <th class="px-4 py-2.5">File Name</th>
                <th class="px-4 py-2.5">Size</th>
                <th class="px-4 py-2.5">Status</th>
                <th class="px-4 py-2.5">Chunks</th>
                <th class="px-4 py-2.5">Vectors</th>
                <th class="px-4 py-2.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              ${state.knowledgeDocs.length === 0 ? `
                <tr>
                  <td colspan="6" class="px-4 py-8 text-center text-muted text-xs">
                    No documents indexed yet. Upload PDF, Markdown, or text files above to begin semantic vector search.
                  </td>
                </tr>
              ` : state.knowledgeDocs.map(d => `
                <tr class="hover:bg-surface-subtle transition-colors">
                  <td class="px-4 py-3 font-medium text-pitch flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px] text-muted">description</span>
                    <span>${escapeHtml(d.file_name)}</span>
                  </td>
                  <td class="px-4 py-3 font-mono text-muted">${(d.file_size ? (d.file_size / 1024).toFixed(1) + ' KB' : '—')}</td>
                  <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-sm text-[10px] font-mono ${d.status === 'synced' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : (d.status === 'failed' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-700 border-amber-200')} border">
                      <span class="w-1.5 h-1.5 rounded-full ${d.status === 'synced' ? 'bg-emerald-500' : (d.status === 'failed' ? 'bg-red-500' : 'bg-amber-500')}"></span>
                      ${escapeHtml(d.status || 'pending')}
                    </span>
                  </td>
                  <td class="px-4 py-3 font-mono">${d.chunk_count !== undefined && d.chunk_count !== null ? d.chunk_count : 0}</td>
                  <td class="px-4 py-3 font-mono">${d.vector_count !== undefined && d.vector_count !== null ? d.vector_count : 0}</td>
                  <td class="px-4 py-3 text-right">
                    <button class="interactive-btn text-muted hover:text-red-600 text-xs delete-doc-btn cursor-pointer" data-id="${d.id}">
                      Delete
                    </button>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  function renderPlaceholderView(title) {
    return `
      <div class="flex-1 flex flex-col items-center justify-center p-12 text-center">
        <span class="material-symbols-outlined text-4xl text-muted mb-2">construction</span>
        <h3 class="text-base font-medium text-pitch">${title}</h3>
        <p class="text-xs text-muted max-w-md mt-1">This module is part of the Mteja Enterprise suite. Core operations desk and live support are fully active.</p>
      </div>
    `;
  }

  function renderAccessDeniedView() {
    return `
      <div class="flex-1 flex flex-col items-center justify-center p-12 text-center bg-canvas">
        <span class="material-symbols-outlined text-4xl text-amber-600 mb-2">lock</span>
        <h3 class="text-base font-semibold text-pitch">Owner Access Restricted</h3>
        <p class="text-xs text-muted max-w-md mt-1">Platform credentials, database parameters, and API keys can only be viewed and modified by the platform owner.</p>
      </div>
    `;
  }

  function renderSettingsView() {
    const isOwner = Boolean(bootstrap.currentUser && (bootstrap.currentUser.isOwner || bootstrap.currentUser.canManage || bootstrap.currentUser.role === 'administrator'));
    if (!isOwner) {
      return renderAccessDeniedView();
    }

    if (state.settingsLoading || !state.settings) {
      return `
        <div class="flex-1 flex flex-col items-center justify-center p-12 text-center">
          <span class="material-symbols-outlined text-3xl animate-spin text-muted mb-2">progress_activity</span>
          <p class="text-xs font-mono text-muted">Loading platform settings...</p>
        </div>
      `;
    }

    const s = state.settings;

    // Dynamically compute URI from current settings
    let connUriDisplay = '';
    if (s.supabase_db_host && s.supabase_db_user) {
      const p = state.showDbPassword ? encodeURIComponent(s.supabase_db_password || '') : (s.supabase_db_password ? '••••••••' : '[YOUR-PASSWORD]');
      connUriDisplay = `postgresql://${encodeURIComponent(s.supabase_db_user)}:${p}@${s.supabase_db_host}:${s.supabase_db_port || 5432}/${encodeURIComponent(s.supabase_db_name || 'postgres')}`;
    }

    return `
      <div class="flex-1 overflow-y-auto px-8 py-8 bg-canvas">
        <div class="max-w-4xl mx-auto space-y-6">

          <!-- Header -->
          <div class="flex items-center justify-between pb-4 border-b border-border">
            <div>
              <div class="flex items-center gap-2">
                <h1 class="text-lg font-semibold text-pitch">Platform &amp; Database Settings</h1>
                <span class="px-2 py-0.5 rounded-sm text-[10px] font-mono uppercase bg-amber-50 text-amber-800 border border-amber-200">Owner Only</span>
              </div>
              <p class="text-xs text-muted mt-1">Configure Supabase pgvector RAG, direct PostgreSQL connection parameters, and FreeLLMAPI Unified AI Engine credentials.</p>
            </div>
            <div class="flex items-center gap-2">
              <button id="btn-test-supabase" type="button" class="interactive-btn px-3 py-1.5 rounded-sm border border-border bg-surface text-pitch hover:bg-surface-subtle text-xs font-medium flex items-center gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-[15px] ${state.settingsTesting ? 'animate-spin' : ''}">${state.settingsTesting ? 'progress_activity' : 'network_check'}</span>
                <span>${state.settingsTesting ? 'Testing Connection...' : 'Test Connection'}</span>
              </button>
              <button id="btn-save-settings" type="button" class="interactive-btn px-4 py-1.5 rounded-sm bg-pitch text-white hover:bg-black text-xs font-medium flex items-center gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-[15px]">${state.settingsSaving ? 'progress_activity' : 'save'}</span>
                <span>${state.settingsSaving ? 'Saving...' : 'Save Settings'}</span>
              </button>
            </div>
          </div>

          ${state.settingsSavedNotice ? `
            <div class="p-3 rounded-sm bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2 shadow-sm">
              <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
              <span class="font-medium">${escapeHtml(state.settingsSavedNotice)}</span>
            </div>
          ` : ''}

          ${state.settingsTestResult ? `
            <div class="p-4 rounded-sm ${state.settingsTestResult.success ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900'} border text-xs shadow-sm">
              <div class="font-semibold mb-2 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">${state.settingsTestResult.success ? 'verified' : 'warning'}</span>
                Connection Diagnostic Report
              </div>
              <ul class="space-y-1 font-mono text-[11.5px] pl-4 list-disc">
                ${(state.settingsTestResult.messages || []).map(m => `<li>${escapeHtml(m)}</li>`).join('')}
              </ul>
            </div>
          ` : ''}

          <!-- CARD 1: Supabase Server Build APIs -->
          <div class="p-6 rounded-sm bg-surface border border-border space-y-4 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-border">
              <div>
                <h3 class="text-sm font-semibold text-pitch">1. Supabase Server Build APIs</h3>
                <p class="text-[11.5px] text-muted mt-0.5">Primary high-performance HTTPS PostgREST &amp; pgvector RPC endpoints.</p>
              </div>
              <span class="px-2 py-0.5 rounded-sm text-[10px] font-mono uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">Server Build APIs</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">SUPABASE_URL</label>
                <input 
                  id="settings-supabase-url" 
                  type="url" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="https://<project-ref>.supabase.co" 
                  value="${escapeHtml(s.supabase_url || '')}" />
                <p class="text-[10.5px] text-muted">Project API base URL from Supabase dashboard.</p>
              </div>

              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">SUPABASE_PUBLISHABLE_KEY</label>
                <input 
                  id="settings-supabase-pub" 
                  type="text" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." 
                  value="${escapeHtml(s.supabase_publishable_key || '')}" />
                <p class="text-[10.5px] text-muted">Public anon API key.</p>
              </div>

              <div class="space-y-1 md:col-span-2">
                <label class="text-xs font-medium text-pitch font-mono">SUPABASE_SECRET_KEY (Service Role)</label>
                <div class="relative">
                  <input 
                    id="settings-supabase-sec" 
                    type="${state.showSecretKey ? 'text' : 'password'}" 
                    class="w-full h-8 pl-3 pr-16 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                    placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." 
                    value="${escapeHtml(s.supabase_secret_key || '')}" />
                  <button type="button" id="toggle-sec-key-btn" class="interactive-btn absolute right-1 top-1 h-6 px-2 text-[10px] font-mono rounded-sm border border-border bg-surface hover:bg-surface-subtle text-pitch">
                    ${state.showSecretKey ? 'Hide' : 'Show'}
                  </button>
                </div>
                <p class="text-[10.5px] text-muted">Service role key used for vector chunk writes and privileged RAG operations.</p>
              </div>

              <div class="space-y-1 md:col-span-2">
                <label class="text-xs font-medium text-pitch font-mono">SUPABASE_JWKS_URL</label>
                <input 
                  id="settings-supabase-jwks" 
                  type="url" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="https://<project-ref>.supabase.co/auth/v1/.well-known/jwks.json" 
                  value="${escapeHtml(s.supabase_jwks_url || '')}" />
                <p class="text-[10.5px] text-muted">JWKS endpoint for JWT verification.</p>
              </div>
            </div>
          </div>

          <!-- CARD 2: Direct PostgreSQL Database Connection -->
          <div class="p-6 rounded-sm bg-surface border border-border space-y-4 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-border">
              <div>
                <h3 class="text-sm font-semibold text-pitch">2. Direct PostgreSQL Database Connection</h3>
                <p class="text-[11.5px] text-muted mt-0.5">Parameters for direct database connectivity, migrations, and connection strings.</p>
              </div>
              <span class="px-2 py-0.5 rounded-sm text-[10px] font-mono uppercase bg-amber-50 text-amber-800 border border-amber-200">Direct Postgres</span>
            </div>

            <!-- Architectural Options Explainer -->
            <div class="p-3 rounded-sm bg-surface-subtle border border-border text-xs leading-relaxed space-y-1.5">
              <div class="font-semibold text-pitch">Supabase PostgreSQL Connection Options:</div>
              <ul class="space-y-1 pl-4 list-disc text-[11px] text-muted">
                <li><strong class="text-pitch">Direct connection:</strong> Port <code>5432</code>, User <code>postgres</code> &mdash; Ideal for persistent, long-lived connections (VMs, containers).</li>
                <li><strong class="text-pitch">Transaction pooler:</strong> Port <code>6543</code>, User <code>postgres.&lt;project-ref&gt;</code> &mdash; Ideal for stateless applications, serverless functions, and short-lived web requests.</li>
                <li><strong class="text-pitch">Session pooler:</strong> Port <code>5432</code>, User <code>postgres.&lt;project-ref&gt;</code> &mdash; Recommended alternative when connecting via an IPv4 network.</li>
              </ul>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-1 md:col-span-2">
                <label class="text-xs font-medium text-pitch font-mono">SUPABASE_DB_HOST (host)</label>
                <input 
                  id="settings-db-host" 
                  type="text" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="e.g. db.<project-ref>.supabase.co or <pooler-host>.pooler.supabase.com" 
                  value="${escapeHtml(s.supabase_db_host || '')}" />
                <p class="text-[10.5px] text-muted">Direct host or pooler host from Supabase Database settings.</p>
              </div>

              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">SUPABASE_DB_PORT (port)</label>
                <input 
                  id="settings-db-port" 
                  type="number" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="5432 or 6543" 
                  value="${escapeHtml(s.supabase_db_port || '5432')}" />
                <p class="text-[10.5px] text-muted">Port 5432 (Direct/Session) or 6543 (Transaction pooler).</p>
              </div>

              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">SUPABASE_DB_NAME (database)</label>
                <input 
                  id="settings-db-name" 
                  type="text" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="postgres" 
                  value="${escapeHtml(s.supabase_db_name || 'postgres')}" />
                <p class="text-[10.5px] text-muted">Database name (typically <code>postgres</code>).</p>
              </div>

              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">SUPABASE_DB_USER (user)</label>
                <input 
                  id="settings-db-user" 
                  type="text" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="postgres or postgres.<project-ref>" 
                  value="${escapeHtml(s.supabase_db_user || '')}" />
                <p class="text-[10.5px] text-muted">User: <code>postgres</code> (Direct) or <code>postgres.&lt;project-ref&gt;</code> (Pooler).</p>
              </div>

              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">SUPABASE_DB_PASSWORD (password)</label>
                <div class="relative">
                  <input 
                    id="settings-db-pass" 
                    type="${state.showDbPassword ? 'text' : 'password'}" 
                    class="w-full h-8 pl-3 pr-16 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                    placeholder="Enter PostgreSQL password" 
                    value="${escapeHtml(s.supabase_db_password || '')}" />
                  <button type="button" id="toggle-db-pass-btn" class="interactive-btn absolute right-1 top-1 h-6 px-2 text-[10px] font-mono rounded-sm border border-border bg-surface hover:bg-surface-subtle text-pitch">
                    ${state.showDbPassword ? 'Hide' : 'Show'}
                  </button>
                </div>
                <p class="text-[10.5px] text-muted">Master database password.</p>
              </div>

              <!-- Connection URI Preview -->
              <div class="space-y-1 md:col-span-2 pt-2">
                <label class="text-xs font-medium text-pitch">Dynamic Connection URI</label>
                <div class="p-2.5 rounded-sm border border-border bg-canvas font-mono text-[11px] text-pitch flex items-center justify-between gap-3">
                  <span id="settings-uri-preview" class="truncate ${connUriDisplay ? 'text-pitch' : 'text-muted italic'}">${connUriDisplay || '(Enter database host, port, user and password above)'}</span>
                  ${connUriDisplay ? `
                    <button type="button" id="copy-conn-uri-btn" class="interactive-btn px-2 py-1 rounded-sm border border-border bg-surface text-pitch hover:bg-surface-subtle text-[11px] font-mono whitespace-nowrap">
                      Copy URI
                    </button>
                  ` : ''}
                </div>
              </div>
            </div>
          </div>

          <!-- CARD 3: FreeLLMAPI Unified AI Engine -->
          <div class="p-6 rounded-sm bg-surface border border-border space-y-4 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-border">
              <div>
                <h3 class="text-sm font-semibold text-pitch">3. FreeLLMAPI Unified AI Engine (<a href="https://freellmapi.co" target="_blank" class="text-pitch underline">freellmapi.co</a>)</h3>
                <p class="text-[11.5px] text-muted mt-0.5">One OpenAI-compatible unified key routing across 600+ free frontier models with zero API costs.</p>
              </div>
              <span class="px-2 py-0.5 rounded-sm text-[10px] font-mono uppercase bg-emerald-50 text-emerald-800 border border-emerald-200">Unified Free LLM</span>
            </div>

            <div class="p-3 rounded-sm bg-surface-subtle border border-border text-xs leading-relaxed space-y-1">
              <div class="font-semibold text-pitch flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[15px] text-emerald-600">hub</span>
                <span>Unified API Routing &amp; Embeddings</span>
              </div>
              <p class="text-[11px] text-muted">
                FreeLLMAPI exposes standard OpenAI-compatible <code>/v1/chat/completions</code> and <code>/v1/embeddings</code> endpoints. Enter your unified key from <a href="https://freellmapi.co" target="_blank" class="text-pitch underline">freellmapi.co</a>. Set Base URL to your local instance (<code>http://localhost:3001/v1</code>) or your hosted endpoint.
              </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-1 md:col-span-2">
                <label class="text-xs font-medium text-pitch font-mono">FREELLM_API_KEY (Unified Key)</label>
                <div class="relative">
                  <input 
                    id="settings-freellm-key" 
                    type="${state.showFreeLlmKey ? 'text' : 'password'}" 
                    class="w-full h-8 pl-3 pr-16 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                    placeholder="freellmapi-..." 
                    value="${escapeHtml(s.freellm_api_key || s.openai_api_key || '')}" />
                  <button type="button" id="toggle-freellm-key-btn" class="interactive-btn absolute right-1 top-1 h-6 px-2 text-[10px] font-mono rounded-sm border border-border bg-surface hover:bg-surface-subtle text-pitch">
                    ${state.showFreeLlmKey ? 'Hide' : 'Show'}
                  </button>
                </div>
                <p class="text-[10.5px] text-muted">Unified API key from freellmapi.co.</p>
              </div>

              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">FREELLM_BASE_URL (Endpoint)</label>
                <input 
                  id="settings-freellm-url" 
                  type="url" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="http://localhost:3001/v1" 
                  value="${escapeHtml(s.freellm_base_url || 'http://localhost:3001/v1')}" />
                <p class="text-[10.5px] text-muted">OpenAI-compatible router URL (e.g. <code>http://localhost:3001/v1</code>).</p>
              </div>

              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">FREELLM_CHAT_MODEL (Chat Model)</label>
                <input 
                  id="settings-freellm-chat-model" 
                  type="text" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="auto" 
                  value="${escapeHtml(s.freellm_chat_model || 'auto')}" />
                <p class="text-[10.5px] text-muted">Model strategy: <code>auto</code>, <code>auto:smart</code>, <code>auto:fast</code>, or specific model.</p>
              </div>

              <div class="space-y-1 md:col-span-2">
                <label class="text-xs font-medium text-pitch font-mono">FREELLM_EMBEDDING_MODEL (Embedding Model)</label>
                <input 
                  id="settings-freellm-embed-model" 
                  type="text" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="text-embedding-3-small" 
                  value="${escapeHtml(s.freellm_embedding_model || 'text-embedding-3-small')}" />
                <p class="text-[10.5px] text-muted">Model for Supabase 1536-dim embeddings (e.g. <code>text-embedding-3-small</code>).</p>
              </div>
            </div>
          </div>

          <!-- CARD 4: Desk Routing & Widget Injection -->
          <div class="p-6 rounded-sm bg-surface border border-border space-y-4 shadow-sm">
            <div class="pb-3 border-b border-border">
              <h3 class="text-sm font-semibold text-pitch">4. Routing &amp; Client Embed Configuration</h3>
              <p class="text-[11.5px] text-muted mt-0.5">Configure operations desk URL slug and public chat widget injection.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">Desk URL Slug</label>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-mono text-muted">${window.location.origin}/</span>
                  <input 
                    id="settings-portal-slug" 
                    type="text" 
                    class="flex-1 h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                    value="${escapeHtml(s.portal_slug || 'mteja')}" />
                </div>
                <p class="text-[10.5px] text-muted">URL path for support staff.</p>
              </div>

              <div class="space-y-1">
                <label class="text-xs font-medium text-pitch font-mono">Edge WebSocket Gateway</label>
                <input 
                  id="settings-realtime-ws" 
                  type="text" 
                  class="w-full h-8 px-3 rounded-sm border border-border bg-canvas text-pitch text-xs font-mono focus:outline-none focus:border-pitch" 
                  placeholder="wss://your-room.partykit.dev" 
                  value="${escapeHtml(s.realtime_ws_url || '')}" />
                <p class="text-[10.5px] text-muted">Optional PartyKit gateway. Leave blank for automated 3s polling.</p>
              </div>

              <div class="md:col-span-2 pt-2">
                <label class="flex items-center gap-2 cursor-pointer text-xs text-pitch select-none">
                  <input 
                    id="settings-auto-inject" 
                    type="checkbox" 
                    class="rounded-sm border-border text-pitch focus:ring-pitch" 
                    ${s.auto_inject_widget ? 'checked' : ''} />
                  <span>Automatically inject customer chat widget on public pages of this WordPress site</span>
                </label>
              </div>
            </div>
          </div>

          <!-- CARD 5: Supabase SQL Setup Script -->
          <div class="p-6 rounded-sm bg-surface border border-border space-y-4 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-border">
              <div>
                <h3 class="text-sm font-semibold text-pitch">5. Supabase pgvector SQL Migration Script</h3>
                <p class="text-[11.5px] text-muted mt-0.5">Run this script in Supabase Dashboard (SQL Editor &rarr; New Query &rarr; Run) to create tables and functions.</p>
              </div>
              <button type="button" id="copy-sql-btn" class="interactive-btn px-3 py-1 rounded-sm border border-border bg-surface text-pitch hover:bg-surface-subtle text-xs font-mono">
                Copy SQL Script
              </button>
            </div>

            <textarea 
              id="settings-sql-box" 
              readonly 
              rows="10" 
              class="w-full p-3 rounded-sm border border-border bg-[#1E1E1E] text-[#D4D4D4] font-mono text-[11px] leading-relaxed resize-y focus:outline-none">${escapeHtml(s.sql_migration_script || '')}</textarea>
          </div>

          <!-- Bottom Action Bar -->
          <div class="flex items-center justify-end gap-3 pt-4 pb-12">
            <button id="btn-save-settings-bottom" type="button" class="interactive-btn px-6 py-2 rounded-sm bg-pitch text-white hover:bg-black text-xs font-semibold shadow-sm flex items-center gap-2">
              <span class="material-symbols-outlined text-[16px]">${state.settingsSaving ? 'progress_activity' : 'save'}</span>
              <span>${state.settingsSaving ? 'Saving Changes...' : 'Save All Settings'}</span>
            </button>
          </div>

        </div>
      </div>
    `;
  }

  // =========================================================================
  // GLOBAL EVENT DELEGATION (Attached once, immune to polling DOM rebuilds)
  // =========================================================================

  let globalDelegationAttached = false;
  function setupGlobalEventDelegation() {
    if (globalDelegationAttached) return;
    globalDelegationAttached = true;

    // 1. Synchronize composer text dynamically on every input
    document.addEventListener('input', (e) => {
      if (e.target && e.target.id === 'composer-textarea') {
        state.composerText = e.target.value;
      }
    });

    // 2. Keyboard shortcut Cmd/Ctrl + Enter to send reply
    document.addEventListener('keydown', (e) => {
      if (e.target && e.target.id === 'composer-textarea' && (e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault();
        const sendBtn = document.getElementById('btn-send-reply');
        if (sendBtn) sendBtn.click();
      }
    });

    // 3. Document-level click event delegation
    document.addEventListener('click', async (e) => {
      // (a) Insert Suggested Draft
      const btnInsertDraft = e.target.closest('#btn-insert-suggested-draft');
      if (btnInsertDraft) {
        e.preventDefault();
        const draftEl = document.getElementById('suggested-draft-content');
        const textarea = document.getElementById('composer-textarea');
        if (draftEl && textarea) {
          let text = draftEl.innerText.trim();
          text = text.replace(/^["'“](.*)["'”]$/s, '$1').trim();
          textarea.value = text;
          state.composerText = text;
          state.composerType = 'public';

          // Ensure composer tab UI is synced to public mode
          const publicBtn = document.querySelector('.composer-mode-btn[data-mode="public"]');
          const whisperBtn = document.querySelector('.composer-mode-btn[data-mode="whisper"]');
          if (publicBtn && whisperBtn) {
            publicBtn.className = 'composer-mode-btn px-2.5 py-1 rounded-sm text-[11.5px] font-medium transition-colors bg-pitch text-white';
            whisperBtn.className = 'composer-mode-btn px-2.5 py-1 rounded-sm text-[11.5px] font-medium transition-colors text-muted hover:text-pitch';
          }
          const sendBtn = document.getElementById('btn-send-reply');
          if (sendBtn) {
            sendBtn.innerHTML = `<span>Send Reply</span><span class="material-symbols-outlined text-[13px]">send</span>`;
            sendBtn.className = 'interactive-btn px-4 py-1.5 rounded-sm font-medium text-[12px] text-white flex items-center gap-1.5 shadow-sm transition-all bg-pitch hover:bg-black';
          }

          textarea.focus();
          textarea.dispatchEvent(new Event('input', { bubbles: true }));
          textarea.dispatchEvent(new Event('change', { bubbles: true }));

          const origHtml = btnInsertDraft.innerHTML;
          btnInsertDraft.innerHTML = `<span class="material-symbols-outlined text-[12px]">check</span><span>Inserted!</span>`;
          btnInsertDraft.classList.add('bg-emerald-700');
          setTimeout(() => {
            btnInsertDraft.innerHTML = origHtml;
            btnInsertDraft.classList.remove('bg-emerald-700');
          }, 1500);
        }
        return;
      }

      // (b) Quick Snippet Buttons
      const btnSnippet = e.target.closest('.quick-snippet-btn');
      if (btnSnippet) {
        e.preventDefault();
        const snippet = btnSnippet.getAttribute('data-snippet');
        const textarea = document.getElementById('composer-textarea');
        if (snippet && textarea) {
          if (textarea.value && !textarea.value.endsWith(' ')) {
            textarea.value += ' ' + snippet;
          } else {
            textarea.value += snippet;
          }
          state.composerText = textarea.value;
          textarea.focus();
        }
        return;
      }

      // (c) Reopen Ticket Button
      const btnReopen = e.target.closest('#btn-reopen');
      if (btnReopen && state.selectedConvId) {
        e.preventDefault();
        btnReopen.disabled = true;
        btnReopen.innerHTML = `<span class="material-symbols-outlined text-[14px] animate-spin">refresh</span><span>Reopening...</span>`;
        await api(`conversations/${state.selectedConvId}/reopen`, { method: 'POST' });
        const convs = await api(`conversations?filter=${state.queueFilter}&search=${encodeURIComponent(state.searchQuery)}`);
        if (convs && Array.isArray(convs)) state.conversations = convs;
        await loadConversationDetails(state.selectedConvId);
        return;
      }

      // (d) Resolve Ticket Button
      const btnResolve = e.target.closest('#btn-resolve');
      if (btnResolve && state.selectedConvId) {
        e.preventDefault();
        btnResolve.disabled = true;
        btnResolve.innerHTML = `<span class="material-symbols-outlined text-[14px]">done_all</span><span>Resolving...</span>`;
        await api(`conversations/${state.selectedConvId}/resolve`, { method: 'POST' });
        const convs = await api(`conversations?filter=${state.queueFilter}&search=${encodeURIComponent(state.searchQuery)}`);
        if (convs && Array.isArray(convs)) state.conversations = convs;
        await loadConversationDetails(state.selectedConvId);
        return;
      }

      // (e) Takeover Chat Button
      const btnTakeover = e.target.closest('#btn-takeover');
      if (btnTakeover && state.selectedConvId) {
        e.preventDefault();
        btnTakeover.disabled = true;
        await api(`conversations/${state.selectedConvId}/takeover`, { method: 'POST' });
        const convs = await api(`conversations?filter=${state.queueFilter}&search=${encodeURIComponent(state.searchQuery)}`);
        if (convs && Array.isArray(convs)) state.conversations = convs;
        await loadConversationDetails(state.selectedConvId);
        return;
      }

      // (f) Handover to AI Button
      const btnHandover = e.target.closest('#btn-handover-ai');
      if (btnHandover && state.selectedConvId) {
        e.preventDefault();
        btnHandover.disabled = true;
        btnHandover.innerHTML = `<span class="material-symbols-outlined text-[14px] animate-spin">refresh</span><span>Handing over...</span>`;
        await api(`conversations/${state.selectedConvId}/handover-ai`, { method: 'POST' });
        const convs = await api(`conversations?filter=${state.queueFilter}&search=${encodeURIComponent(state.searchQuery)}`);
        if (convs && Array.isArray(convs)) state.conversations = convs;
        await loadConversationDetails(state.selectedConvId);
        return;
      }

      // (f) Send Reply Button
      const btnSend = e.target.closest('#btn-send-reply');
      if (btnSend && state.selectedConvId) {
        e.preventDefault();
        const textarea = document.getElementById('composer-textarea');
        const text = textarea ? textarea.value.trim() : state.composerText.trim();
        if (!text) return;

        btnSend.disabled = true;
        await api(`conversations/${state.selectedConvId}/messages`, {
          method: 'POST',
          body: JSON.stringify({
            content: text,
            sender_type: state.composerType === 'whisper' ? 'whisper' : 'agent'
          })
        });

        if (textarea) textarea.value = '';
        state.composerText = '';
        btnSend.disabled = false;
        const convs = await api(`conversations?filter=${state.queueFilter}&search=${encodeURIComponent(state.searchQuery)}`);
        if (convs && Array.isArray(convs)) state.conversations = convs;
        await loadConversationDetails(state.selectedConvId);
        return;
      }
    });
  }

  // =========================================================================
  // VIEW EVENT LISTENERS & INTERACTION BINDINGS
  // =========================================================================

  function attachEventListeners() {
    // Nav Tab Switching
    document.querySelectorAll('.nav-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        state.currentTab = btn.getAttribute('data-tab');
        if (state.currentTab === 'settings' && !state.settings && !state.settingsLoading) {
          loadSettings();
        } else {
          render();
        }
      });
    });

    // Top Search Input
    const topSearch = document.getElementById('top-search-input');
    if (topSearch) {
      topSearch.addEventListener('input', (e) => {
        state.searchQuery = e.target.value;
        startPolling();
      });
    }

    // Filter Tabs
    document.querySelectorAll('.filter-tab-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        state.queueFilter = btn.getAttribute('data-filter');
        const convs = await api(`conversations?filter=${state.queueFilter}&search=${encodeURIComponent(state.searchQuery)}`);
        if (convs && Array.isArray(convs)) state.conversations = convs;
        render();
      });
    });

    attachQueueEvents();

    // Composer Mode Tabs
    document.querySelectorAll('.composer-mode-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        state.composerType = btn.getAttribute('data-mode');
        render();
      });
    });

    // Studio Settings Events
    const thresholdSlider = document.getElementById('studio-threshold-slider');
    const thresholdDisplay = document.getElementById('threshold-display');
    if (thresholdSlider && thresholdDisplay) {
      thresholdSlider.addEventListener('input', (e) => {
        state.studioSettings.threshold = parseFloat(e.target.value);
        thresholdDisplay.innerText = Number(e.target.value).toFixed(2);
      });
    }

    const btnSaveStudio = document.getElementById('btn-save-studio');
    if (btnSaveStudio && state.workspace) {
      btnSaveStudio.addEventListener('click', async () => {
        const nameInput = document.getElementById('studio-name-input');
        const promptInput = document.getElementById('studio-prompt-input');
        if (nameInput) state.studioSettings.name = nameInput.value;
        if (promptInput) state.studioSettings.systemPrompt = promptInput.value;

        await api(`workspaces/${state.workspace.id}`, {
          method: 'PUT',
          body: JSON.stringify({
            name: state.studioSettings.name,
            system_prompt: state.studioSettings.systemPrompt,
            similarity_threshold: state.studioSettings.threshold
          })
        });

        alert('Agent Studio configuration saved!');
      });
    }

    // Live Sandbox Events
    const sandboxInput = document.getElementById('sandbox-input');
    const sandboxSendBtn = document.getElementById('sandbox-send-btn');
    if (sandboxInput && sandboxSendBtn) {
      const handleSandboxSend = async () => {
        const val = sandboxInput.value.trim();
        if (!val) return;
        state.livePreviewMessages.push({ sender: 'user', text: val });
        state.livePreviewMessages.push({ sender: 'ai', text: 'Searching knowledge chunks...', pending: true });
        sandboxInput.value = '';
        render();

        try {
          const res = await api('studio/test-rag', {
            method: 'POST',
            body: JSON.stringify({ query: val })
          });
          state.livePreviewMessages = state.livePreviewMessages.filter(m => !m.pending);
          if (res && res.reply) {
            state.livePreviewMessages.push({ sender: 'ai', text: res.reply });
          } else {
            state.livePreviewMessages.push({ sender: 'ai', text: '[System]: No response received from RAG engine.' });
          }
        } catch (err) {
          state.livePreviewMessages = state.livePreviewMessages.filter(m => !m.pending);
          state.livePreviewMessages.push({ sender: 'ai', text: '[Error]: Failed to query test RAG endpoint.' });
        }
        render();
      };
      sandboxSendBtn.addEventListener('click', handleSandboxSend);
      sandboxInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') handleSandboxSend(); });
    }

    // Knowledge Dropzone & Multi-File Drag-and-Drop
    const dropzone = document.getElementById('kb-dropzone');
    const fileInput = document.getElementById('kb-file-input');

    function updateQueueItemDOM(item) {
      const el = document.getElementById('queue-item-' + item.id);
      if (el) {
        const bar = el.querySelector('.progress-bar-fill');
        const statusLabel = el.querySelector('.status-label');
        const percentLabel = el.querySelector('.percent-label');

        if (bar) {
          bar.style.width = item.progress + '%';
          bar.className = `h-full transition-all duration-200 ease-out progress-bar-fill ${item.stage === 'completed' ? 'bg-forest-action' : (item.stage === 'error' ? 'bg-red-500' : 'bg-amber-action')}`;
        }
        if (statusLabel) {
          if (item.stage === 'uploading') statusLabel.textContent = `Uploading ${item.progress}%`;
          else if (item.stage === 'processing') statusLabel.textContent = `Extracting & Vectorizing...`;
          else if (item.stage === 'completed') statusLabel.textContent = `✓ Synced (${item.chunkCount || 0} chunks, ${item.vectorCount || 0} vectors)`;
          else if (item.stage === 'error') statusLabel.textContent = `✕ Failed: ${item.error || 'Error'}`;
          statusLabel.className = `font-mono text-[11px] status-label ${item.stage === 'completed' ? 'text-forest-action font-semibold' : (item.stage === 'error' ? 'text-red-600 font-semibold' : 'text-amber-800')}`;
        }
        if (percentLabel) {
          percentLabel.textContent = item.progress + '%';
        }
      }

      // Update overall master bar
      const totalQueued = state.uploadQueue.length;
      if (totalQueued > 0) {
        const overall = Math.round((state.uploadQueue.reduce((acc, q) => acc + (q.progress || 0), 0) / (totalQueued * 100)) * 100);
        const overallBar = document.getElementById('kb-overall-bar');
        const overallPercent = document.getElementById('kb-overall-percent');
        if (overallBar) overallBar.style.width = overall + '%';
        if (overallPercent) overallPercent.textContent = overall + '%';
      }
    }

    function uploadSingleFileWithProgress(item) {
      return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        const formData = new FormData();
        formData.append('file', item.file);

        xhr.open('POST', bootstrap.apiUrl + 'knowledge', true);
        xhr.setRequestHeader('X-WP-Nonce', bootstrap.restNonce);

        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 80);
            item.progress = Math.max(item.progress, pct);
            updateQueueItemDOM(item);
          }
        };

        xhr.upload.onload = () => {
          item.progress = 85;
          item.stage = 'processing';
          updateQueueItemDOM(item);
        };

        xhr.onload = () => {
          if (xhr.status >= 200 && xhr.status < 300) {
            try {
              const data = JSON.parse(xhr.responseText);
              item.progress = 100;
              item.stage = 'completed';
              item.chunkCount = data.chunk_count || 0;
              item.vectorCount = data.vector_count || 0;
            } catch (e) {
              item.progress = 100;
              item.stage = 'completed';
            }
          } else {
            item.stage = 'error';
            try {
              const err = JSON.parse(xhr.responseText);
              item.error = err.message || `HTTP ${xhr.status}`;
            } catch (e) {
              item.error = `HTTP ${xhr.status}`;
            }
          }
          updateQueueItemDOM(item);
          resolve();
        };

        xhr.onerror = () => {
          item.stage = 'error';
          item.error = 'Network upload failed';
          updateQueueItemDOM(item);
          resolve();
        };

        xhr.send(formData);
      });
    }

    async function uploadFilesBatch(files) {
      if (!files || files.length === 0) return;

      const allowedExts = ['pdf', 'md', 'txt', 'html', 'htm', 'json', 'csv'];
      const validFiles = [];
      for (const f of files) {
        const ext = f.name.split('.').pop().toLowerCase();
        if (allowedExts.includes(ext)) {
          validFiles.push(f);
        } else {
          alert(`File "${f.name}" has an unsupported format. Supported formats: .pdf, .md, .txt, .html, .json, .csv`);
        }
      }

      if (validFiles.length === 0) return;

      state.isUploadingKnowledge = true;

      const newItems = validFiles.map(f => ({
        id: 'up_' + Math.random().toString(36).substring(2, 9),
        file: f,
        name: f.name,
        size: f.size,
        progress: 0,
        stage: 'uploading',
        error: null,
        chunkCount: 0,
        vectorCount: 0
      }));

      state.uploadQueue = [...newItems, ...state.uploadQueue];
      render();

      for (const item of newItems) {
        await uploadSingleFileWithProgress(item);
        // Refresh knowledge list so table updates after each file
        const docs = await api('knowledge');
        if (docs && Array.isArray(docs)) {
          state.knowledgeDocs = docs;
        }
      }

      state.isUploadingKnowledge = false;
      render();
    }

    if (dropzone && fileInput) {
      dropzone.addEventListener('click', (e) => {
        if (e.target !== fileInput) {
          fileInput.click();
        }
      });

      fileInput.addEventListener('change', async (e) => {
        if (e.target.files && e.target.files.length > 0) {
          const files = Array.from(e.target.files);
          fileInput.value = '';
          await uploadFilesBatch(files);
        }
      });

      ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
          e.preventDefault();
          e.stopPropagation();
          dropzone.classList.add('border-pitch', 'bg-surface-subtle');
        });
      });

      ['dragleave', 'dragend'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
          e.preventDefault();
          e.stopPropagation();
          dropzone.classList.remove('border-pitch', 'bg-surface-subtle');
        });
      });

      dropzone.addEventListener('drop', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('border-pitch', 'bg-surface-subtle');
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
          const files = Array.from(e.dataTransfer.files);
          await uploadFilesBatch(files);
        }
      });
    }

    // Clear Upload Queue Button
    const btnClearQueue = document.getElementById('btn-clear-upload-queue');
    if (btnClearQueue) {
      btnClearQueue.addEventListener('click', (e) => {
        e.preventDefault();
        state.uploadQueue = [];
        render();
      });
    }

    // Delete Document
    document.querySelectorAll('.delete-doc-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        btn.disabled = true;
        btn.innerText = 'Deleting...';
        await api(`knowledge/${id}`, { method: 'DELETE' });
        const docs = await api('knowledge');
        if (docs) state.knowledgeDocs = docs;
        render();
      });
    });

    attachSettingsEvents();
  }

  function attachSettingsEvents() {
    function readSettingsInputs() {
      if (!state.settings) state.settings = {};
      const urlEl = document.getElementById('settings-supabase-url');
      if (urlEl) state.settings.supabase_url = urlEl.value.trim();
      const pubEl = document.getElementById('settings-supabase-pub');
      if (pubEl) state.settings.supabase_publishable_key = pubEl.value.trim();
      const secEl = document.getElementById('settings-supabase-sec');
      if (secEl) state.settings.supabase_secret_key = secEl.value.trim();
      const jwksEl = document.getElementById('settings-supabase-jwks');
      if (jwksEl) state.settings.supabase_jwks_url = jwksEl.value.trim();

      const hostEl = document.getElementById('settings-db-host');
      if (hostEl) state.settings.supabase_db_host = hostEl.value.trim();
      const portEl = document.getElementById('settings-db-port');
      if (portEl) state.settings.supabase_db_port = portEl.value.trim();
      const nameEl = document.getElementById('settings-db-name');
      if (nameEl) state.settings.supabase_db_name = nameEl.value.trim();
      const userEl = document.getElementById('settings-db-user');
      if (userEl) state.settings.supabase_db_user = userEl.value.trim();
      const passEl = document.getElementById('settings-db-pass');
      if (passEl) state.settings.supabase_db_password = passEl.value;

      const freeKeyEl = document.getElementById('settings-freellm-key');
      if (freeKeyEl) {
        state.settings.freellm_api_key = freeKeyEl.value.trim();
        state.settings.openai_api_key = freeKeyEl.value.trim();
      }
      const freeUrlEl = document.getElementById('settings-freellm-url');
      if (freeUrlEl) state.settings.freellm_base_url = freeUrlEl.value.trim();
      const freeChatEl = document.getElementById('settings-freellm-chat-model');
      if (freeChatEl) state.settings.freellm_chat_model = freeChatEl.value.trim();
      const freeEmbedEl = document.getElementById('settings-freellm-embed-model');
      if (freeEmbedEl) state.settings.freellm_embedding_model = freeEmbedEl.value.trim();

      const aiEl = document.getElementById('settings-openai-key');
      if (aiEl) {
        state.settings.openai_api_key = aiEl.value.trim();
        if (!state.settings.freellm_api_key) {
          state.settings.freellm_api_key = aiEl.value.trim();
        }
      }

      const slugEl = document.getElementById('settings-portal-slug');
      if (slugEl) state.settings.portal_slug = slugEl.value.trim();
      const autoEl = document.getElementById('settings-auto-inject');
      if (autoEl) state.settings.auto_inject_widget = autoEl.checked;
      const wsEl = document.getElementById('settings-realtime-ws');
      if (wsEl) state.settings.realtime_ws_url = wsEl.value.trim();
    }

    async function handleSaveSettings() {
      readSettingsInputs();
      state.settingsSaving = true;
      render();
      const updated = await api('settings', {
        method: 'POST',
        body: JSON.stringify(state.settings)
      });
      state.settingsSaving = false;
      if (updated) {
        state.settings = updated;
        state.settingsSavedNotice = 'Platform configuration saved successfully!';
        setTimeout(() => {
          state.settingsSavedNotice = null;
          render();
        }, 4000);
      }
      render();
    }

    const btnSave = document.getElementById('btn-save-settings');
    if (btnSave) btnSave.addEventListener('click', handleSaveSettings);
    const btnSaveBtm = document.getElementById('btn-save-settings-bottom');
    if (btnSaveBtm) btnSaveBtm.addEventListener('click', handleSaveSettings);

    const btnTest = document.getElementById('btn-test-supabase');
    if (btnTest) {
      btnTest.addEventListener('click', async () => {
        readSettingsInputs();
        state.settingsTesting = true;
        state.settingsTestResult = null;
        render();

        // First save current values so test runs against them
        await api('settings', {
          method: 'POST',
          body: JSON.stringify(state.settings)
        });

        const res = await api('supabase/test', { method: 'POST' });
        state.settingsTesting = false;
        state.settingsTestResult = res;
        render();
      });
    }

    // Show/Hide Toggles
    const toggleSec = document.getElementById('toggle-sec-key-btn');
    if (toggleSec) {
      toggleSec.addEventListener('click', () => {
        readSettingsInputs();
        state.showSecretKey = !state.showSecretKey;
        render();
      });
    }

    const toggleDb = document.getElementById('toggle-db-pass-btn');
    if (toggleDb) {
      toggleDb.addEventListener('click', () => {
        readSettingsInputs();
        state.showDbPassword = !state.showDbPassword;
        render();
      });
    }

    const toggleFreeLlm = document.getElementById('toggle-freellm-key-btn');
    if (toggleFreeLlm) {
      toggleFreeLlm.addEventListener('click', () => {
        readSettingsInputs();
        state.showFreeLlmKey = !state.showFreeLlmKey;
        render();
      });
    }

    const toggleAi = document.getElementById('toggle-openai-key-btn');
    if (toggleAi) {
      toggleAi.addEventListener('click', () => {
        readSettingsInputs();
        state.showOpenAiKey = !state.showOpenAiKey;
        render();
      });
    }

    // Copy URI
    const copyUriBtn = document.getElementById('copy-conn-uri-btn');
    if (copyUriBtn) {
      copyUriBtn.addEventListener('click', () => {
        readSettingsInputs();
        const p = encodeURIComponent((state.settings && state.settings.supabase_db_password) || '');
        const host = (state.settings && state.settings.supabase_db_host) || '';
        const port = (state.settings && state.settings.supabase_db_port) || '5432';
        const user = encodeURIComponent((state.settings && state.settings.supabase_db_user) || '');
        const db   = encodeURIComponent((state.settings && state.settings.supabase_db_name) || 'postgres');
        const uri = `postgresql://${user}:${p}@${host}:${port}/${db}`;
        navigator.clipboard.writeText(uri);
        copyUriBtn.innerText = 'Copied!';
        setTimeout(() => { copyUriBtn.innerText = 'Copy URI'; }, 2000);
      });
    }

    // Copy SQL
    const copySqlBtn = document.getElementById('copy-sql-btn');
    const sqlBox = document.getElementById('settings-sql-box');
    if (copySqlBtn && sqlBox) {
      copySqlBtn.addEventListener('click', () => {
        navigator.clipboard.writeText(sqlBox.value);
        copySqlBtn.innerText = 'SQL Copied!';
        setTimeout(() => { copySqlBtn.innerText = 'Copy SQL Script'; }, 2000);
      });
    }
  }

  function attachQueueEvents() {
    document.querySelectorAll('.queue-card').forEach(card => {
      card.addEventListener('click', () => {
        const id = card.getAttribute('data-id');
        loadConversationDetails(id);
      });
    });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // Boot on DOM Ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initData);
  } else {
    initData();
  }

})();
