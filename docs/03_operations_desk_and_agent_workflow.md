# Mteja Operations Desk & Agent Workflow Guide

## 1. Accessing the Operations Desk

The Operations Desk is a distraction-free, full-screen Single Page Application (SPA) designed to let store owners and support specialists manage live inquiries rapidly.

- **URL:** `http://your-site.com/mteja` (or `/mteja/`)
- **Authentication Required:** You must be logged into WordPress with an account that has `edit_posts` (Editor / Support Agent) or `manage_options` (Administrator / Owner) capabilities.
- **Why It Bypasses `wp-admin`:** Traditional WordPress admin screens reload on every click and are cluttered with plugin menus and update notices. The Mteja Desk runs in an ultra-clean Warm Parchment Atelier interface designed around high-velocity customer communication.

---

## 2. Interface Layout (The 3-Pane Workspace)

```
┌──────┬──────────────────────┬──────────────────────────────────────────┬────────────────────────┐
│ Nav  │ Pane 1: Queue Feed   │ Pane 2: Active Thread Stage              │ Pane 3: Context Rail   │
│ Rail │                      │                                          │                        │
│      │ [Filter: Esc | All]  │ Customer Header: visitor@example.com     │ Customer Profile       │
│ [IN] │                      │ [Takeover Chat] [Resolve Ticket]         │ • Location: Dar es S.  │
│ [KB] │ ──────────────────── │ ──────────────────────────────────────── │ • Token: tok_9842...   │
│ [ST] │ Ticket #1 (Escalated)│ Visitor: "How do I return my item?"      │                        │
│ [SET]│ Ticket #2 (Active)   │ AI: "Our return window is 30 days..."    │ WooCommerce Orders     │
│      │ Ticket #3 (Resolved) │ ──────────────────────────────────────── │ • Order #1042: $84.00  │
│      │                      │ AI Suggested Draft:                      │ • Order #981:  $120.00 │
│      │                      │ "I have checked your order..." [Insert]  │                        │
│      │                      │ ──────────────────────────────────────── │                        │
│      │                      │ Composer: [Public Reply] [Whisper Note]  │                        │
│      │                      │ [/refund] [/escalate] [Send Reply]       │                        │
└──────┴──────────────────────┴──────────────────────────────────────────┴────────────────────────┘
```

### Pane 1: Queue Feed (Left)
- **Top Filter Tabs:**
  - `Escalations`: Displays tickets waiting for human assistance (`status === 'human_waiting'`).
  - `All`: Displays all tickets regardless of status.
  - `Resolved`: Displays tickets that have been closed (`status === 'resolved'`).
- **Ticket Cards:** Displays customer email/name, last message snippet, location (city/country), and ticket status badge.
- **Top Search Bar:** Filters tickets in real time by customer email, name, or keywords.

### Pane 2: Active Thread Stage (Center)
- **Thread Header:** Displays visitor contact details, status badge (`ai_handling`, `human_waiting`, `human_active`, `resolved`), and action buttons:
  - `Takeover Chat`: Immediately pauses autonomous AI and assigns the conversation to you (`human_active`).
  - `Resolve Ticket`: Closes the inquiry and marks the conversation as `resolved`.
  - `Reopen Ticket`: (Displayed when viewing resolved tickets) Re-activates the ticket and returns it to the queue.
- **Message Bubbles:**
  - **Black Bubbles (Right):** Incoming visitor messages.
  - **White Bordered Cards (Left):** Autonomous AI replies with RAG verification badges.
  - **Light Beige Cards (Right):** Human agent public replies with the operator's display name.
  - **Amber Cards (Center):** Internal Whisper Notes visible only to support staff.

### Pane 3: Dynamic Context Rail (Right)
- **Visitor Profile:** Contact email, geographical location derived from IP, and unique visitor token.
- **WooCommerce Order Telemetry:** If WooCommerce is active, automatically queries past customer orders matching the visitor's email address, displaying order numbers, dates, statuses, and order totals.

---

## 3. Working with AI Suggested Drafts

When an agent opens a conversation that was escalated by the AI:
1. Above the response composer, an **AI Suggested Draft** card is displayed.
2. The draft analyzes recent conversation messages and prepares a contextual reply (e.g., *"Hi! I've reviewed your request and verified your account details. I am taking over directly to make sure this is resolved for you immediately."*).
3. Click the **"Insert Draft"** button.
4. Mteja automatically:
   - Strips any wrapping quotes.
   - Populates the composer textarea with the draft.
   - Synchronizes internal reactive state (`state.composerText`).
   - Focuses the composer for immediate editing.
   - Displays a green `"Inserted!"` checkmark confirmation.
5. You can edit or augment the text as needed and send it!

---

## 4. Composer Modes, Snippets & Keyboard Shortcuts

### Public Reply vs. Internal Whisper Note
- **Public Reply:** Default mode. Messages sent in this mode are immediately visible to the customer in their chat widget.
- **Internal Whisper Note:** Switch mode by clicking **Internal Whisper Note**. The composer turns amber. Whisper notes are saved with `sender_type: 'whisper'` and are only visible to your support team in the Operations Desk. Customers never see them.

### Quick Snippet Macros
Located directly under the composer textarea:
- Click **`/refund`** to insert: *"I have processed your refund."*
- Click **`/escalate`** to insert: *"I have escalated this to engineering."*

### Power-User Keyboard Shortcut
- Press **`Cmd + Enter`** (Mac) or **`Ctrl + Enter`** (Windows/Linux) while typing inside the composer textarea to instantly send the reply without reaching for your mouse!
