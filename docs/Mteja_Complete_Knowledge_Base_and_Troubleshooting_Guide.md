# Mteja Support Desk: Architecture & Core Concepts

## 1. Executive Summary

Mteja is an autonomous customer support platform and live chat desk natively integrated into WordPress. It combines modern vector search (RAG - Retrieval-Augmented Generation) with a fullscreen Operations Desk designed for store owners and support specialists.

Unlike legacy WordPress chat plugins that rely on rigid decision trees, third-party iframe subscriptions, or slow wp-admin interfaces, Mteja provides:
- An isolated, zero-conflict client widget embedded on public pages.
- A standalone fullscreen desktop workspace at `/mteja` that completely bypasses `/wp-admin`.
- Native Supabase `pgvector` storage for real-time semantic document search.
- FreeLLMAPI unified proxy integration for multi-model AI inference and 1536-dimensional embeddings.
- A two-tier autonomous guardrail that automatically resolves repetitive questions while transferring complex inquiries to human specialists.

---

## 2. High-Level Architecture Overview

Mteja is composed of five interconnected layers:

```
┌─────────────────────────────────────────────────────────────┐
│                    Public Visitor Browser                   │
│   [Floating Chat Launcher] ───> [Isolated Iframe Widget]    │
│                        (/mteja/widget-embed)                │
└──────────────────────────────┬──────────────────────────────┘
                               │ JSON REST API
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                   WordPress Plugin Layer                    │
│   • REST Controller: /wp-json/mteja/v1/                     │
│   • Custom Routing: /mteja (Desk) & /mteja/widget-embed     │
│   • MySQL Tables: wp_mteja_conversations, messages, etc.    │
│   • Context Telemetry: WooCommerce orders & GeoIP detection │
└──────────────┬───────────────────────────────┬──────────────┘
               │                               │
               ▼                               ▼
┌──────────────────────────────┐ ┌─────────────────────────────┐
│   Supabase PostgreSQL DB     │ │   FreeLLMAPI Unified Router │
│   • Extension: pgvector      │ │   • Base URL: :3001/v1      │
│   • Table: document_chunks   │ │   • /v1/embeddings (1536d)  │
│   • Function: match_chunks   │ │   • /v1/chat/completions    │
│   • Cosine similarity search │ │   • Unified Routing: auto   │
└──────────────────────────────┘ └─────────────────────────────┘
               ▲                               ▲
               └───────────────┬───────────────┘
                               │
┌──────────────────────────────┴──────────────────────────────┐
│                    Operations Desk SPA                      │
│   • Fullscreen Workspace at http://your-site/mteja/         │
│   • 3-Pane Interface: Queues | Active Thread | Telemetry    │
│   • Knowledge Base Ingestion & Agent Studio Tuning          │
│   • Owner Platform & Supabase Connection Settings           │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Core Components Explained

### A. The Client Chat Widget
- **Script Loader (`assets/widget/widget.js`):** Lightweight script injected onto public WordPress pages. It injects a floating chat bubble with smooth spring animations.
- **Sandboxed Iframe (`/mteja/widget-embed`):** When opened, the widget renders inside an isolated `iframe` pointing to `/mteja/widget-embed`. This ensures 100% style isolation, meaning active WordPress theme styles, Gutenberg CSS, or third-party page builders cannot break the chat layout.
- **Visitor Session Persistence:** The widget generates a unique `mteja_visitor_token` stored in browser `localStorage`. When a visitor chats, their session (`mteja_conv_id`) persists across page navigation, browser tabs, and return visits.

### B. The Operations Desk SPA (`/mteja`)
- **Standalone Portal:** Accessed directly by staff at `http://your-site.com/mteja`.
- **Security & Authorization:** Staff must have `edit_posts` or `manage_options` capabilities. Non-staff visitors attempting to access `/mteja` are redirected to the login screen.
- **Owner vs Specialist Role Separation:**
  - Administrators (Owners) have access to the Settings tab to configure database credentials, Supabase connection URIs, API keys, and auto-injection.
  - Support Agents have access to the Inbox, Knowledge Base, and Studio without exposing sensitive platform configuration.

### C. The RAG Autonomous Engine
- **Multi-File Batch Ingestion:** The Knowledge Ingestion Hub supports simultaneous drag-and-drop or file-picker upload of multiple files (`.md`, `.txt`, `.pdf`, `.html`, `.json`, `.csv`) up to 15MB each.
- **Real-Time Progress Tracking:** Each uploaded file displays its own live progress bar and stage status (Uploading network bytes &rarr; Extracting text &rarr; Generating 50-chunk vector batches &rarr; Synced to pgvector) alongside an overall batch progress bar.
- **Document Chunking:** Knowledge uploaded via the Knowledge Base tab is parsed, sanitized, and broken into overlapping chunks (~1500 characters with 200 character overlap).
- **1536-Dimensional Embeddings:** Each chunk is converted into a 1536-dimensional vector using the FreeLLMAPI `/v1/embeddings` endpoint.
- **Supabase Storage:** Embeddings are stored in the `document_chunks` table in Supabase PostgreSQL equipped with the `pgvector` extension and indexed using IVFFlat cosine similarity.
- **Semantic Retrieval (`match_chunks`):** When a customer submits a query, Mteja vectors the question and executes the PostgreSQL function `match_chunks()` to retrieve the most semantically relevant text passages.

### D. The Two-Tier Autonomous Guardrail
1. **Confidence Evaluation:** Mteja calculates the cosine similarity score of the top retrieved knowledge chunk against the user prompt.
2. **Above Threshold (Default: 0.70):** The AI synthesizes an answer using the retrieved knowledge base chunks and posts an autonomous reply.
3. **Below Threshold (< 0.70):** If no knowledge matches or confidence is low, the AI will not hallucinate. It immediately switches the conversation status to `human_waiting`, politely asks the customer for their email address, and alerts human operators in the inbox queue.

---

## 4. Key Terminology & Concepts

| Concept | Description |
| :--- | :--- |
| **Workspace** | A support workspace instance holding an agent persona, system prompt, and similarity threshold. |
| **Visitor Token** | A persistent client UUID (`tok_...`) generated in the visitor's browser `localStorage`. |
| **Conversation** | A support ticket thread identified by UUID. Can transition between `ai_handling`, `human_waiting`, `human_active`, and `resolved`. |
| **Whisper Note** | An internal staff note (`sender_type === 'whisper'`) visible only to support agents in `/mteja`. Hidden from public visitors. |
| **Suggested Draft** | An AI-generated candidate reply displayed in the agent thread stage that operators can review and click "Insert Draft" to load. |
| **Similarity Threshold** | A floating-point value between `0.00` and `1.00` (default `0.70`). Higher values enforce stricter knowledge matching before AI answers. |


---


# Mteja Chat Widget: Embedding & Troubleshooting Guide

## 1. Overview of the Chat Widget

The Mteja chat widget is the customer-facing interface embedded on your WordPress storefront or website. It features:
- A responsive floating bubble button in the lower-right corner.
- An isolated iframe rendering `/mteja/widget-embed`.
- Automatic session caching in `localStorage`.
- Real-time polling every 3 seconds for lightning-fast replies without complex WebSocket setups.
- A "New Chat" button allowing customers to begin fresh conversations at any time.
- Inline status indicators informing the visitor when a previous ticket was resolved.

---

## 2. How to Embed the Widget on WordPress

There are two primary methods to enable the chat widget on your site:

### Method A: Automated Injection (Recommended)
1. Log in as an Administrator and navigate to the Operations Desk at `/mteja`.
2. Click the **Settings** icon on the left navigation rail.
3. Scroll down to **Card 4: Frontend Chat Widget & Realtime Gateway**.
4. Check the box labeled:
   `[x] Automatically inject customer chat widget on public pages of this WordPress site`
5. Click **Save All Settings**.

Mteja will automatically hook into `wp_footer` across all public pages and load the script.

### Method B: Manual Script Tag Injection
If you use a custom theme, child theme, Google Tag Manager, or want the widget only on specific landing pages, add the following script tag before the closing `</body>` tag:

```html
<script 
  src="https://your-domain.com/wp-content/plugins/mteja/assets/widget/widget.js" 
  id="mteja-widget-loader"
  data-embed-url="https://your-domain.com/mteja/widget-embed"
  data-workspace-id="YOUR_WORKSPACE_UUID"
  async 
  defer>
</script>
```

Replace `https://your-domain.com` with your WordPress site URL and `YOUR_WORKSPACE_UUID` with your workspace ID (available in `/mteja` under Agent Studio or Settings).

---

## 3. Visitor Session Management & Persistence

### How Sessions Work
When a customer loads the widget:
1. The widget inspects `localStorage` for `mteja_visitor_token`. If none exists, it generates a unique random token (e.g., `tok_9xk2l81ab9c`) and persists it.
2. The widget sends a `POST /wp-json/mteja/v1/visitor/session` request with the visitor token.
3. The WordPress backend returns the customer's most recent active conversation, or provisions a new conversation UUID in MySQL if none exists.
4. The conversation ID is stored in `localStorage` under `mteja_conv_id`.

### Starting a New Conversation ("New Chat" Feature)
If a customer has previously resolved an issue and wishes to start an inquiry regarding an unrelated topic:
1. The customer clicks the **"New Chat"** button located in the top-right header of the widget.
2. The widget purges `mteja_conv_id` from `localStorage` and executes:
   ```json
   POST /wp-json/mteja/v1/visitor/session
   Body: { "visitor_token": "tok_...", "force_new": true }
   ```
3. The backend immediately creates a fresh conversation in `ai_handling` status and returns the new ID.
4. The message history resets to the initial welcome prompt, allowing a clean dialogue.

### Automatic Email Address Capture
If an unauthenticated visitor mentions their email address anywhere in the conversation (e.g., *"My email is jane@example.com"*):
- The backend regex automatically captures the email address.
- It updates `visitor_email` in `wp_mteja_conversations`.
- If WooCommerce is installed, the dynamic context inspector instantly pulls all past WooCommerce orders associated with that email for human agents to see!

---

## 4. Chat Widget Troubleshooting & Solutions

### Problem 1: The widget bubble does not appear on public pages
**Probable Causes & Solutions:**
- **Auto-Injection is Disabled:** Go to `/mteja` &rarr; **Settings** &rarr; Card 4 &rarr; ensure **"Automatically inject customer chat widget..."** is checked and saved.
- **Aggressive Caching or Minification:** If using caching plugins (e.g., WP Super Cache, LiteSpeed, W3 Total Cache, WP Rocket) or Cloudflare, purge the cache so the updated footer script appears.
- **Missing `wp_footer()` call:** Ensure your theme's `footer.php` file contains `<?php wp_footer(); ?>` right before `</body>`.

---

### Problem 2: Clicking the bubble opens a blank white box or 404 error
**Probable Causes & Solutions:**
- **WordPress Permalinks Not Flushed:** Mteja uses custom rewrite rules for `/mteja/widget-embed`. If rewrite rules have not refreshed, visiting this endpoint returns 404.
  - *Fix:* Navigate to **WordPress Admin &rarr; Settings &rarr; Permalinks** and simply click **Save Changes** to flush the rewrite cache.
- **Custom Nginx / Apache Rules Blocking Slugs:** Ensure your web server passes pretty permalinks to WordPress (`index.php`).

---

### Problem 3: Messages fail to send or show an error
**Probable Causes & Solutions:**
- **REST API Blocked:** Security plugins (Wordfence, iThemes, Cloudflare WAF) may be blocking POST requests to `/wp-json/mteja/v1/visitor/messages`.
  - *Fix:* Whitelist `/wp-json/mteja/v1/` routes in your firewall.
- **CORS / Mixed Content Warning:** If your website is accessed via HTTPS, ensure the WordPress Site URL in **Settings &rarr; General** is set to `https://` to prevent browser mixed-content blocks.

---

### Problem 4: Resetting customer chat state for testing
If you want to clear your local visitor session during testing:
1. Open Developer Tools in your browser (`F12` or `Ctrl+Shift+I`).
2. Go to **Application** &rarr; **Storage** &rarr; **Local Storage**.
3. Delete the keys: `mteja_conv_id` and `mteja_visitor_token`.
4. Refresh the page to start as a brand new visitor.


---


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


---


# Mteja Ticket Lifecycle: Statuses, Resolution & Reopening

## 1. The Ticket State Machine

Every conversation in Mteja follows a deterministic lifecycle managed in the `wp_mteja_conversations` table. Understanding these states ensures your support operations run smoothly:

```
                  ┌───────────────┐
                  │ Customer Chat │
                  │   Initiated   │
                  └───────┬───────┘
                          │
                          ▼
                  ┌───────────────┐
       ┌─────────>│  ai_handling  │<────────────────────────┐
       │          └───────┬───────┘                         │
       │                  │                                 │
       │     Low Match /  │ Human Request                   │
       │     Low Conf     ▼                                 │
       │          ┌───────────────┐                         │
       │          │ human_waiting │                         │ Customer
       │          └───────┬───────┘                         │ Responds
Customer                  │                                 │ (No Agent)
Responds     Agent        │ Takeover                        │
(No Agent)   Takes Over   ▼                                 │
       │          ┌───────────────┐                         │
       │          │ human_active  │                         │
       │          └───────┬───────┘                         │
       │                  │                                 │
       │                  │ Agent Resolves                  │
       │                  ▼                                 │
       │          ┌───────────────┐                         │
       │          │   resolved    │                         │
       │          └───────┬───────┘                         │
       │                  │                                 │
       │                  ├─ Customer Responds (Assigned) ──┼──> human_waiting
       │                  ├─ Agent Sends Public Reply ──────┼──> human_active
       └──────────────────┴─ Staff Clicks "Reopen Ticket" ──┘
```

---

## 2. Description of Statuses

### 1. `ai_handling`
- **Meaning:** The autonomous RAG AI is actively monitoring and replying to customer messages.
- **Trigger:** Initiated by default when a new customer chat starts, or when an unassigned resolved conversation receives a new customer message.
- **Behavior:** Incoming messages query Supabase pgvector `match_chunks`. If similarity is $\ge 0.70$, the AI synthesizes a verified answer from the knowledge base.

### 2. `human_waiting`
- **Meaning:** The ticket is in the Escalations queue awaiting human intervention. The autonomous AI is paused.
- **Trigger:** Either the AI could not find knowledge matching the query with $\ge 0.70$ similarity, or the customer explicitly requested human assistance (or a resolved assigned ticket received a customer follow-up).
- **Behavior:** The ticket appears prominently in the **Escalations** filter tab of the Operations Desk.

### 3. `human_active`
- **Meaning:** A specific support agent has taken control of the chat.
- **Trigger:** An operator clicked the **Takeover Chat** button, or an agent sent a public message.
- **Behavior:** Autonomous AI remains silent. The customer interacts directly with the support specialist.

### 4. `resolved`
- **Meaning:** The inquiry is closed and marked complete.
- **Trigger:** An operator clicks the **Resolve Ticket** button in the conversation header.
- **Behavior:** The conversation moves out of the active queues into the **Resolved** filter tab.

---

## 3. Resolving a Ticket

To resolve an active ticket:
1. Open the conversation in the Operations Desk (`/mteja`).
2. Click the green **"Resolve Ticket"** button located in the upper-right corner of the active thread header.
3. The conversation status transitions to `resolved`, and `updated_at` is updated.
4. The ticket will now appear under the **Resolved** filter tab.
5. In the customer's chat widget, a discreet banner appears: *"Ticket resolved. Send a message to reopen or start a new chat."*

---

## 4. Reopening a Resolved Ticket

A common problem in legacy desks is that resolved tickets remain silently closed when a customer follows up, causing customer messages to be lost.

Mteja implements **Triple-Action Reopening Safeguards**:

### Safeguard A: Automatic Reopening to Autonomous AI Support Circle
When a visitor posts a new message on a conversation that was previously marked `resolved` (or returns after operator inactivity):
- The backend automatically transitions the conversation to `status = 'ai_handling'` and unassigns any previous agent (`assigned_agent_id = NULL`).
- The autonomous AI immediately picks up the visitor's question and executes the complete support circle:
  1. Queries Supabase pgvector `match_chunks` using FreeLLMAPI embeddings.
  2. If verified knowledge is found ($\ge 0.70$ similarity), the AI answers the customer's question directly.
  3. If the topic is not covered in the knowledge base, the AI gracefully transfers the conversation to `human_waiting` and asks the customer for their email address (or confirms their existing email).
- The `updated_at` timestamp is refreshed to the current second, pushing the conversation to the top of the queue.

### Safeguard B: Automatic Reopening on Agent Public Reply
If an agent opens a resolved conversation and sends a public reply:
- The backend automatically transitions the status from `resolved` to `human_active`.
- The conversation returns to active operational status assigned to that agent.

### Safeguard C: Manual "Reopen Ticket" Button
When viewing a resolved ticket in the Operations Desk:
- The header displays an emerald `resolved` badge and a **"Reopen Ticket"** button.
- Clicking **"Reopen Ticket"** immediately calls `POST /wp-json/mteja/v1/conversations/{id}/reopen`.
- The ticket returns to `ai_handling`, clearing agent assignment and allowing the autonomous AI to handle future inquiries.

---

## 5. Returning Conversations from Human to Autonomous AI

Support staff have total control to return any active or escalated chat back to the autonomous AI at any moment:

### Option 1: The "Handover to AI" Header Button
1. In the Operations Desk (`/mteja`), open any conversation that is in `human_active` or `human_waiting`.
2. In the header bar, click the **"Handover to AI"** button (decorated with a robot icon).
3. The conversation status is instantly updated to `ai_handling`, and `assigned_agent_id` is set to `NULL`.
4. An internal whisper note is automatically recorded: *"Operator transferred chat back to Autonomous AI Support."*
5. If the last message in the thread was sent by the visitor, the AI immediately searches the knowledge base and generates an AI response!

### Option 2: The `/aisupport` Chat Macro Command
Support agents can also trigger handover directly from the composer textarea:
- Type `/aisupport` (or `/ai` or `/handover`) in the message composer.
- You can optionally append a message to the customer, e.g.:
  `/aisupport I have resolved your account verification. Our AI assistant will answer any other questions you may have!`
- If typed without extra text, Mteja sends a polite standard message: *"I am transferring this chat back to our autonomous AI assistant. Feel free to ask any other questions!"*
- The conversation is instantly returned to `ai_handling`, an internal whisper note is logged, and the agent is unassigned.

---

## 6. Starting a New Ticket vs. Reopening
If a visitor has an entirely new question and does not wish to reopen their previous inquiry:
- They simply click the **"New Chat"** button in the header of the chat widget.
- This creates a completely new conversation UUID and starts a fresh dialogue without cluttering the previous inquiry.


---


# Supabase pgvector Setup & SQL Migrations Guide

## 1. Overview of Supabase in Mteja

Mteja uses [Supabase](https://supabase.com/) as its vector storage engine for Retrieval-Augmented Generation (RAG). Supabase provides PostgreSQL with the `pgvector` extension, enabling high-speed cosine similarity searches across millions of document chunks.

Mteja interacts with Supabase across two interfaces:
1. **Supabase REST API (PostgREST):** High-velocity HTTP queries for vector insertion (`/rest/v1/document_chunks`) and semantic similarity search (`/rest/v1/rpc/match_chunks`).
2. **PostgreSQL Direct / Pooler Connection:** Direct database connection used for table introspection, health checks, and database management.

---

## 2. Choosing the Right Supabase Connection Mode

When connecting your Supabase project in `/mteja` &rarr; **Settings** &rarr; **Card 2: Supabase Direct Database Connection**, Supabase offers three connection options:

### Option 1: Direct Connection
- **Ideal for:** Persistent long-lived connections running on virtual machines or containers with native IPv6 support.
- **Host format:** `db.[YOUR-PROJECT-REF].supabase.co`
- **Port:** `5432`
- **Database:** `postgres`
- **Username:** `postgres`
- **Connection URI:** `postgresql://postgres:[PASSWORD]@db.[YOUR-PROJECT-REF].supabase.co:5432/postgres`

### Option 2: Session Pooler (Recommended for IPv4 networks)
- **Ideal for:** Applications connecting from networks that lack native IPv6 support, or standard hosting providers.
- **Host format:** `aws-0-[REGION].pooler.supabase.com`
- **Port:** `5432`
- **Database:** `postgres`
- **Username:** `postgres.[YOUR-PROJECT-REF]`
- **Connection URI:** `postgresql://postgres.[YOUR-PROJECT-REF]:[PASSWORD]@aws-0-[REGION].pooler.supabase.com:5432/postgres`

### Option 3: Transaction Pooler
- **Ideal for:** Stateless, serverless architectures where each database transaction is brief and isolated.
- **Host format:** `aws-0-[REGION].pooler.supabase.com`
- **Port:** `6543`
- **Database:** `postgres`
- **Username:** `postgres.[YOUR-PROJECT-REF]`
- **Connection URI:** `postgresql://postgres.[YOUR-PROJECT-REF]:[PASSWORD]@aws-0-[REGION].pooler.supabase.com:6543/postgres`

> [!IMPORTANT]
> When using either the **Session Pooler** or **Transaction Pooler**, your database username must be `postgres.[YOUR-PROJECT-REF]`, not just `postgres`.

---

## 3. The Supabase SQL Migration Script

Before Mteja can store embeddings and execute semantic searches, you must create the necessary tables, indexes, and RPC functions in Supabase.

### Step-by-Step Instructions:
1. Log in to your [Supabase Dashboard](https://supabase.com/dashboard).
2. Open your project.
3. In the left navigation menu, click **SQL Editor**.
4. Click **New Query** (or the `+` button).
5. Copy and paste the complete script below.
6. Click **Run** (or press `Ctrl + Enter`).

### Complete SQL Script:

```sql
-- 1. Enable the pgvector extension for high-performance vector operations
CREATE EXTENSION IF NOT EXISTS vector;

-- 2. Create the document_chunks table for storing knowledge base embeddings
CREATE TABLE IF NOT EXISTS public.document_chunks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id TEXT NOT NULL DEFAULT '',
    document_id TEXT NOT NULL DEFAULT '',
    chunk_index INTEGER NOT NULL DEFAULT 0,
    content TEXT NOT NULL,
    embedding VECTOR(1536),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()) NOT NULL
);

-- 3. Create an IVFFlat index for lightning-fast approximate nearest neighbor search
CREATE INDEX IF NOT EXISTS idx_document_chunks_embedding 
ON public.document_chunks 
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);

-- 4. Create the match_chunks RPC function for semantic RAG retrieval
CREATE OR REPLACE FUNCTION public.match_chunks (
    query_embedding VECTOR(1536),
    match_threshold FLOAT DEFAULT 0.70,
    match_count INT DEFAULT 4,
    filter_workspace_id TEXT DEFAULT ''
)
RETURNS TABLE (
    id UUID,
    content TEXT,
    similarity FLOAT,
    document_id TEXT,
    chunk_index INT
)
LANGUAGE plpgsql
STABLE
AS $$
BEGIN
    RETURN QUERY
    SELECT
        dc.id,
        dc.content,
        1 - (dc.embedding <=> query_embedding) AS similarity,
        dc.document_id,
        dc.chunk_index
    FROM public.document_chunks dc
    WHERE 
        (filter_workspace_id = '' OR dc.workspace_id = filter_workspace_id)
        AND 1 - (dc.embedding <=> query_embedding) >= match_threshold
    ORDER BY dc.embedding <=> query_embedding ASC
    LIMIT match_count;
END;
$$;
```

---

## 4. Supabase Troubleshooting & Solutions

### Error: `Failed to query "document_chunks" table: cUrl error 28: Operation timed out after 8002 milliseconds with 0 bytes`
**Cause:**
Free-tier Supabase projects automatically pause after a period of inactivity. When a request arrives, the project undergoes a "cold start" waking up PostgreSQL, which can take 10–15 seconds. If the HTTP timeout is too short (e.g., 5 or 8 seconds), the request fails.

**Solution:**
Mteja incorporates an expanded 20-second timeout with automated retry logic on cold starts.
- Click **"Test Supabase Connection"** a second time. Once the database is awake, the check turns green.
- If timeouts persist, ensure you are connecting via the **Session Pooler** (`aws-0-[REGION].pooler.supabase.com:5432`) rather than direct IPv6 if your host has no IPv6 route.

---

### Error: `Table not found: relation "document_chunks" does not exist`
**Cause:**
The Supabase SQL migration script has not been executed yet in the SQL Editor.

**Solution:**
Open Supabase Dashboard &rarr; **SQL Editor** &rarr; Paste the script above &rarr; Click **Run**.
Then return to `/mteja` &rarr; Settings &rarr; Click **"Test Supabase Connection"**.

---

### Error: `Vector dimension mismatch (expected 1536, got ...)`
**Cause:**
The `embedding` column was declared with a different dimension (such as 768 or 3072).

**Solution:**
FreeLLMAPI produces 1536-dimensional embeddings. Ensure the column type is exactly `VECTOR(1536)`. To fix existing tables, run:
```sql
ALTER TABLE public.document_chunks ALTER COLUMN embedding TYPE VECTOR(1536);
```


---


# FreeLLMAPI Unified AI Router Configuration Guide

## 1. What is FreeLLMAPI?

[FreeLLMAPI](https://freellmapi.co/) is a unified LLM proxy router that allows you to connect multiple leading AI model providers (including Google AI Studio, Groq, Mistral, OpenRouter, and Cloudflare Workers AI) using a single, unified API key and an OpenAI-compatible interface.

Instead of managing separate subscription keys and SDKs for each provider, Mteja routes both semantic vector embeddings and chat completions through your local FreeLLMAPI router instance.

---

## 2. API Endpoints & Capabilities

FreeLLMAPI exposes standard OpenAI-compatible endpoints:
- **Base URL:** `http://localhost:3001/v1`
- **Chat Completions:** `/v1/chat/completions` (Used for synthesizing autonomous customer replies)
- **Vector Embeddings:** `/v1/embeddings` (Used for generating 1536-dimensional vectors for documents and search queries)
- **Anthropic Messages:** `/v1/messages` (Claude-compatible)
- **Responses:** `/v1/responses`

### Model Routing Modes:
- **`auto` (Recommended):** Uses the active routing chain configured in your FreeLLMAPI dashboard, automatically falling back across providers if one is rate-limited.
- **`auto:fast`:** Prioritizes response speed and lowest latency across enabled providers.

---

## 3. Configuring FreeLLMAPI in Mteja

1. Open the Operations Desk at `/mteja`.
2. Click **Settings** on the left rail.
3. Scroll down to **Card 3: FreeLLMAPI Unified AI Router & OpenAI Configuration**.
4. Configure the following fields:
   - **Enable FreeLLMAPI Router:** Toggle to **Active** (`[x] Use FreeLLMAPI Unified AI Router`).
   - **FreeLLMAPI Unified API Key:** Enter your unified key (format: `freellmapi-...`).
   - **FreeLLMAPI Base URL:** Enter your router URL (e.g., `http://localhost:3001/v1`).
   - **Embedding Model:** Set to `auto` (produces 1536-dimensional embeddings).
   - **Chat Completion Model:** Set to `auto` or `auto:fast`.
5. Click **Save All Settings**.
6. Click **Test FreeLLMAPI & AI Connection** in the top action bar to confirm 200 OK connectivity!

---

## 4. Docker Networking & "Router Unreachable" Troubleshooting

### Problem: `FreeLLMAPI router unreachable at http://localhost:3001/v1`
**Why this happens:**
If your WordPress site runs inside Docker containers (e.g., Apache/PHP inside container `wp_app`):
- `localhost` inside a Docker container refers to the **container itself**, not your host machine!
- Therefore, when WordPress attempts to connect to `http://localhost:3001/v1`, it cannot find the FreeLLMAPI service running on the host OS.

**Solutions:**

#### Solution A: Use the Docker Host Gateway IP
In `/mteja` &rarr; **Settings** &rarr; Card 3 &rarr; set **FreeLLMAPI Base URL** to:
```
http://172.17.0.1:3001/v1
```
or (if enabled in your Docker configuration):
```
http://host.docker.internal:3001/v1
```

#### Solution B: Run the Mteja Host Bridge Daemon
Mteja includes a dedicated host bridge daemon (`scratch/freellm-bridge.py`) that binds to host bridge adapters (`0.0.0.0:3001` or `172.17.0.1:3001`) and transparently relays requests to `127.0.0.1:3001`.

---

## 5. Verifying FreeLLMAPI from the Command Line

You can test your FreeLLMAPI proxy directly using `curl` from your terminal:

### 1. Test Chat Completion:
```bash
curl http://localhost:3001/v1/chat/completions \
  -H "Authorization: Bearer YOUR_FREELLMAPI_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "auto",
    "messages": [
      {"role": "user", "content": "Hello, how are you?"}
    ]
  }'
```

### 2. Test Vector Embedding (1536 Dimensions):
```bash
curl http://localhost:3001/v1/embeddings \
  -H "Authorization: Bearer YOUR_FREELLMAPI_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "auto",
    "input": "Return policy for damaged goods"
  }'
```
The response will return an array of 1536 float values under `data[0].embedding`.


---


# Agent Studio: System Prompts & RAG Threshold Tuning Guide

## 1. Overview of Agent Studio

The **Agent Studio** tab in the Operations Desk (`/mteja`) is your command center for configuring the autonomous support agent's behavior, personality, and intelligence guardrails.

Here you can:
- Define your Agent's display name and brand identity.
- Craft the core System Prompt that instructs the AI on how to interact with customers.
- Calibrate the RAG Cosine Similarity Threshold slider.
- Test queries against your knowledge base in real time with interactive similarity scoring.
- Toggle offline email capture modes.

---

## 2. Crafting an Effective System Prompt

The System Prompt is injected before every autonomous chat completion. It shapes the agent's tone, boundary conditions, and instructions for referencing the knowledge base.

### Recommended System Prompt Template:

```
You are Mteja, an autonomous, courteous customer support specialist for {SITE_NAME}. 

Your primary mission is to help customers quickly and accurately using the provided verified knowledge base documents.

Guidelines:
1. Always base your answers strictly on the knowledge base excerpts provided in the context.
2. If the knowledge base does not contain enough information to answer a question completely, do NOT fabricate facts or make guesses. Politely inform the customer that you will connect them with a human specialist who can investigate further.
3. Keep your answers concise, well-formatted, and helpful. Use bullet points for multi-step instructions.
4. When asking the customer for follow-up details (such as their order number or email address), explain why it is needed.
5. Maintain a professional, empathetic, and reassuring tone at all times.
```

---

## 3. Calibrating the RAG Similarity Threshold

The **Cosine Similarity Threshold** (ranging from `0.00` to `1.00`) controls the autonomous guardrail between AI answering and human operator escalation.

```
0.50 ────────────────── 0.70 (Recommended) ────────────────── 0.85
Permissive (Risky)       Balanced Guardrail                  Strict (Conservative)
Attempts answers on      Answers only verified queries;     Escalates frequently;
weak semantic matches    escalates when uncertain           zero hallucinations
```

### Threshold Guidelines:

| Threshold | Profile | Behavior | Best Used For |
| :---: | :--- | :--- | :--- |
| **0.80 – 0.90** | **Strict** | Requires near-exact semantic matches. Escalates to human operators at the slightest ambiguity. | Banking, healthcare, or legally binding contract support where accuracy is non-negotiable. |
| **0.70 – 0.75** | **Balanced (Recommended)** | Standard default. Provides strong autonomous resolution for FAQs while safely transferring edge cases to staff. | E-commerce stores, SaaS platforms, corporate websites. |
| **0.55 – 0.65** | **Permissive** | Attempts answers even when knowledge matches are loose. Increased risk of minor hallucinations. | High-volume informal sites where staff availability is scarce. |

---

## 4. The Studio Interactive Playground

The Agent Studio includes an interactive testing playground on the right side:
1. Enter a sample customer inquiry in the **Test Query** input (e.g., *"Can I return a opened product?"*).
2. Click **Test Retrieval**.
3. Mteja vectors your test query and queries `match_chunks` in Supabase.
4. The playground displays:
   - The top matched knowledge base chunk.
   - The exact **Cosine Similarity Score** (e.g., `Similarity: 0.78`).
   - A visual badge indicating whether the query passes your threshold:
     - 🟢 **Passes Guardrail:** AI will answer autonomously.
     - 🔴 **Below Threshold:** AI will escalate to human queue (`human_waiting`).


---


# WooCommerce Integration & Customer Context Telemetry Guide

## 1. Native WooCommerce Integration Overview

Mteja includes deep, native integration with [WooCommerce](https://woocommerce.com/). When installed on a WooCommerce store, Mteja automatically detects the active store without requiring any API keys or webhook setup.

### What Telemetry is Provided:
- **Customer Account Binding:** Matches the customer's chat session to their registered WooCommerce user account or billing email address.
- **Order History:** Lists the customer's recent orders directly inside the Operations Desk (`/mteja`) in the right-hand **Context Rail**.
- **Real-Time Order Details:**
  - Order Number & ID (e.g., `#1042`)
  - Formatted Order Total & Currency (e.g., `$124.50`)
  - Order Status (e.g., `Completed`, `Processing`, `On Hold`, `Refunded`)
  - Order Date & Time

---

## 2. How Customer Identification Works

Mteja uses a dual-method identification pipeline:

### Method 1: Authenticated WordPress Users
If a customer is logged into their WordPress / WooCommerce account when opening the chat widget, Mteja automatically links their `user_id` and registered account email address.

### Method 2: Guest Visitors & In-Chat Email Extraction
If an anonymous guest opens the chat:
1. The guest communicates with the AI or support agent.
2. If the customer mentions an email address in any message (e.g., *"My order was under sarah@example.com"*):
   - Mteja's server-side regex extracts the email address.
   - It saves `visitor_email` to `wp_mteja_conversations`.
   - The REST endpoint `/wp-json/mteja/v1/conversations/{id}/context` queries WooCommerce orders matching `billing_email = 'sarah@example.com'`.
   - The right-hand Context Rail immediately renders their order cards for the operator!

---

## 3. How Agents Use Context Telemetry in Daily Support

Having live WooCommerce telemetry inside the thread interface eliminates the need for support staff to switch tabs into WooCommerce admin:

1. **Verify Order Status Instantly:** When a customer asks *"Where is my order?"*, the agent immediately sees whether the order is `Processing` or `Completed` without asking the customer for an order number.
2. **Instant Refund Handling:** If a customer requests a refund, the agent can check the order date and total, click the `/refund` snippet macro under the composer, and respond in seconds.
3. **Prevent Fraud:** Operators can verify that the email address provided in chat matches the actual billing email on file before discussing order details.

---

## 4. GeoIP Location Telemetry

Along with WooCommerce data, Mteja resolves the visitor's approximate geographical location (City and Country) from their network IP address:
- Displayed on every ticket card in the **Queue Feed** (e.g., *"Dar es Salaam, Tanzania"* or *"London, United Kingdom"*).
- Displayed in the customer profile card in the Context Rail.
- Helps support agents provide locale-aware assistance, appropriate timezone greetings, and shipping time estimates.


---


# Mteja Master Troubleshooting & FAQ Handbook

## 1. Chat Widget FAQs & Troubleshooting

### Q1: How do I embed the chat widget on my WordPress site?
**Answer:** The easiest method is to log in to `/mteja` as an Administrator, go to **Settings** &rarr; **Card 4: Frontend Chat Widget & Realtime Gateway**, check the box labeled **"Automatically inject customer chat widget on public pages"**, and click **Save All Settings**. Alternatively, you can paste the script tag into your theme footer:
```html
<script src="https://your-domain.com/wp-content/plugins/mteja/assets/widget/widget.js" id="mteja-widget-loader" data-embed-url="https://your-domain.com/mteja/widget-embed" data-workspace-id="YOUR_WORKSPACE_UUID" async defer></script>
```

### Q2: Why is the chat widget not showing up on my website?
**Answer:** Check the following three common causes:
1. Verify that **"Automatically inject customer chat widget..."** is checked in `/mteja` Settings.
2. If using caching plugins (LiteSpeed, WP Rocket, W3 Total Cache) or Cloudflare, purge the cache completely so the updated page footer loads.
3. Check your theme's `footer.php` to ensure it contains the standard WordPress `<?php wp_footer(); ?>` call before the closing `</body>` tag.

### Q3: How do I start a clean new chat session in the widget?
**Answer:** Click the **"New Chat"** button located in the top-right header of the widget. This clears your previous conversation session and immediately provisions a fresh ticket in `ai_handling` status.

### Q4: Why did my resolved ticket reopen when I sent a message?
**Answer:** Mteja features an automated customer safeguarding system. Whenever a customer sends a message on a ticket that was previously marked `resolved`, the system automatically reopens it so your inquiry is never ignored. If an agent was assigned to your ticket, it reopens to `human_waiting` to alert the specialist. If no agent was assigned, it reopens to `ai_handling` so the autonomous AI can answer you immediately.

---

## 2. Operations Desk & Agent Workflow FAQs

### Q5: How do I access the Operations Desk?
**Answer:** Go to `http://your-site.com/mteja` in your browser. You must be logged into WordPress with an account that has `edit_posts` (Support Agent) or `manage_options` (Administrator) permissions.

### Q6: How does the "Insert Draft" button work?
**Answer:** When viewing a customer thread in the Operations Desk, an **AI Suggested Draft** box is rendered above the composer. Clicking the **"Insert Draft"** button cleanly strips any outer quotes, inserts the generated draft text into the composer textarea, synchronizes the reactive state, and focuses the textarea for immediate editing.

### Q7: How do I resolve a ticket?
**Answer:** In the conversation header at `/mteja`, click the green **"Resolve Ticket"** button. The ticket status updates to `resolved`, moving the ticket to the **Resolved** filter tab.

### Q8: How do I reopen a resolved ticket as an operator?
**Answer:** Select the conversation from the **Resolved** or **All** filter tab. In the conversation header, click the **"Reopen Ticket"** button. The ticket will immediately return to active queues.

### Q9: What is an Internal Whisper Note and can customers see it?
**Answer:** Whisper Notes are internal staff annotations. When you switch composer mode to **Internal Whisper Note**, the composer highlights in amber. Whisper notes are saved only for staff visibility inside `/mteja` and are never displayed to customers in their chat widget.

### Q10: Are there keyboard shortcuts to send messages in the Operations Desk?
**Answer:** Yes! Press **`Cmd + Enter`** (Mac) or **`Ctrl + Enter`** (Windows/Linux) while typing in the composer to immediately send your message or save your whisper note.

---

## 3. Supabase & Database FAQs

### Q11: What is the difference between Direct connection, Session Pooler, and Transaction Pooler in Supabase?
**Answer:**
- **Direct Connection (`db.[REF].supabase.co:5432`):** Ideal for persistent, long-lived connections on virtual machines with native IPv6 support. Username is `postgres`.
- **Session Pooler (`aws-0-[REGION].pooler.supabase.com:5432`):** Recommended for IPv4 networks and standard hosting environments. Username is `postgres.[REF]`.
- **Transaction Pooler (`aws-0-[REGION].pooler.supabase.com:6543`):** Ideal for stateless serverless functions. Username is `postgres.[REF]`.

### Q12: Why does the Supabase connection test fail with `cURL error 28: Operation timed out`?
**Answer:** Free-tier Supabase projects automatically sleep when idle. Waking up PostgreSQL during a "cold start" can take 10–15 seconds. Mteja includes an expanded 20-second timeout. Click **"Test Supabase Connection"** again to complete the check once the database is awake. Also ensure your host network resolves IPv4 via the Session Pooler if IPv6 direct connection is unreachable.

### Q13: How do I run the Supabase pgvector SQL migration script?
**Answer:** Open your [Supabase Dashboard](https://supabase.com/dashboard) &rarr; Click your project &rarr; Open **SQL Editor** from the left sidebar &rarr; Click **New Query** &rarr; Paste the SQL migration script from Settings Card 5 &rarr; Click **Run**. This creates the `vector` extension, the `document_chunks` table, and the `match_chunks` function.

---

## 4. FreeLLMAPI & AI Routing FAQs

### Q14: What is FreeLLMAPI and where do I get my API key?
**Answer:** FreeLLMAPI (https://freellmapi.co/) provides a unified AI proxy router connecting to Google AI Studio, Groq, Mistral, OpenRouter, and Cloudflare. You obtain your unified API key (`freellmapi-...`) from your FreeLLMAPI dashboard.

### Q15: Why does Mteja say `FreeLLMAPI router unreachable at http://localhost:3001/v1`?
**Answer:** If WordPress is running inside Docker containers, `localhost` inside the container refers to the container itself, not the host machine where the FreeLLMAPI router is listening on port 3001. Set the **FreeLLMAPI Base URL** in `/mteja` Settings to your Docker host IP: `http://172.17.0.1:3001/v1` or use `http://host.docker.internal:3001/v1`.

### Q16: What models should I choose in FreeLLMAPI configuration?
**Answer:**
- **Embedding Model:** Set to `auto` (generates 1536-dimensional embeddings required by Supabase pgvector).
- **Chat Completion Model:** Set to `auto` (uses active routing chain) or `auto:fast` (prioritizes lowest latency).

---

## 5. Knowledge Base & Agent Studio FAQs

### Q17: Can I upload multiple files to the Knowledge Base, and what formats are supported?
**Answer:** Yes! Mteja allows selecting and dragging-and-dropping multiple files simultaneously. Supported formats include `.md` (Markdown), `.pdf` (PDF documents), `.txt` (Plain text), `.html` / `.htm` (Web pages), `.json` (Structured knowledge), and `.csv` (Tabular data) up to 15MB per file. During ingestion, Mteja displays real-time progress bars tracking upload bytes, text extraction, semantic chunking, and Supabase pgvector embedding generation per file as well as an overall batch progress bar.

### Q18: What is the Cosine Similarity Threshold in Agent Studio and what should it be set to?
**Answer:** The similarity threshold (slider from 0.00 to 1.00) defines how strictly the retrieved knowledge must match a customer prompt before the AI answers. The recommended setting is **`0.70`**. Higher values (e.g. 0.80) are more conservative and escalate to human staff more frequently; lower values (e.g. 0.60) attempt answers on looser semantic associations.

### Q19: How do I test if my uploaded knowledge matches customer questions?
**Answer:** Open `/mteja` &rarr; click **Studio** &rarr; enter your query in the **Test Query** box &rarr; click **Test Retrieval**. The interactive playground shows the exact similarity score and whether the query passes the autonomous threshold!
