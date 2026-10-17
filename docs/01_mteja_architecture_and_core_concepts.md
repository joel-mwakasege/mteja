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
