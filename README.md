# Mteja 🤖

> **Autonomous Customer Support & Live Operations Desk for WordPress**  
> Powered by Supabase `pgvector` RAG, FreeLLMAPI multi-model inference, and a dedicated fullscreen workspace with zero `wp-admin` dependency.

---

## 🌟 Highlights

- **Standalone Operations Desk (`/mteja`):** A fast, dedicated 3-pane support workstation decoupled from `wp-admin` styling and overhead.
- **Isolated Client Chat Widget:** Renders inside an isolated iframe (`/mteja/widget-embed`) to guarantee zero CSS/JS conflicts with WordPress themes, Gutenberg, or page builders.
- **Supabase pgvector RAG Engine:** Stores 1536-dimensional embeddings in PostgreSQL with IVFFlat indexing for sub-second semantic document search.
- **Multi-Tier Autonomous Guardrails:**
  1. *Greetings & Small Talk:* Welcomes visitors warmly without false human transfers.
  2. *Strict Topic Deflection:* Politely declines off-topic inquiries (food, weather, trivia) and steers users back to store support.
  3. *Grounded Answering:* Strictly synthesizes verified answers from uploaded knowledge base chunks.
  4. *Human Escalation Protocol:* Detects escalation intent, transitions tickets to `human_waiting`, and emits `[ESCALATE_TO_HUMAN]`.
- **Seamless `/aisupport` Handover:** Visitors and human operators can instantly transition any chat back to AI by typing `/aisupport` or clicking the "Handover to AI" button.
- **Multi-File Batch Knowledge Hub:** Upload multiple documents (`.md`, `.pdf`, `.txt`, `.html`, `.json`, `.csv`) simultaneously with live per-file and batch progress bars.
- **FreeLLMAPI Unified AI Router:** Single unified API key supporting Google AI Studio, Groq, Mistral, OpenRouter, and Cloudflare.
- **Live Context Telemetry:** Real-time visitor GeoIP location and WooCommerce order history in the agent inspector pane.

---

## 🏛️ System Architecture

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

## 📂 Repository Structure

```
mteja/
├── assets/
│   ├── portal/
│   │   └── portal-app.js        # Operations Desk SPA application logic
│   ├── widget/
│   │   ├── widget.js            # Public floating chat bubble loader
│   │   └── widget-embed.js      # Sandboxed iframe chat client
│   ├── tokens.css               # Monetry design tokens & CSS variables
│   └── theme.json               # WordPress theme and block styles
├── includes/
│   ├── class-mteja-installer.php       # DB tables & schema migrations
│   ├── class-mteja-patterns.php        # Gutenberg block templates & patterns
│   ├── class-mteja-rag.php             # Supabase & FreeLLMAPI RAG engine
│   ├── class-mteja-rest-controller.php # REST API controller & endpoints
│   ├── class-mteja-router.php          # Custom URL routing (/mteja)
│   └── class-mteja-widget.php          # Widget injection & scripts loader
├── patterns/
│   ├── landing-page.json        # Gutenberg block pattern for landing page
│   └── login-page.json          # Gutenberg block pattern for staff login
├── docs/                        # Modular knowledge base & guides
│   ├── 01_mteja_architecture_and_core_concepts.md
│   ├── 02_chat_widget_embedding_and_troubleshooting.md
│   ├── 03_operations_desk_and_agent_workflow.md
│   ├── 04_ticket_lifecycle_resolution_and_reopening.md
│   ├── 05_supabase_pgvector_setup_and_sql_migrations.md
│   ├── 06_freellmapi_unified_key_and_router_config.md
│   ├── 07_agent_studio_prompts_and_rag_thresholds.md
│   ├── 08_woocommerce_and_context_telemetry.md
│   ├── 09_complete_troubleshooting_and_faq_handbook.md
│   └── Mteja_Complete_Knowledge_Base_and_Troubleshooting_Guide.md
├── mteja.php                    # Plugin bootstrap
└── README.md
```

---

## 🚀 Quickstart & Setup

### 1. Installation
Clone or copy this folder into your WordPress plugins directory:
```bash
cd wp-content/plugins/
git clone https://github.com/joel-mwakasege/mteja.git
```
Activate the plugin in WordPress:
```bash
wp plugin activate mteja
```

### 2. Access the Operations Desk
Log into your WordPress site and navigate to:
```
http://your-site.com/mteja
```
*(Requires `edit_posts` or `manage_options` permissions).*

### 3. Configure Supabase & FreeLLMAPI
In `/mteja`, click the **Settings** tab:
1. **Supabase Connection:**
   - Enter your `SUPABASE_URL` and `SUPABASE_SECRET_KEY` (Service Role Key).
   - Enter your PostgreSQL connection credentials.
   - Run the provided SQL migration script in your Supabase SQL Editor.
2. **FreeLLMAPI Unified Router:**
   - Enter your Unified API Key (`freellmapi-...`).
   - Set the Base URL (defaults to `http://localhost:3001/v1` or Docker gateway `http://172.17.0.1:3001/v1`).
3. Click **Save All Settings** and **Test Connection**.

### 4. Upload Knowledge Documents
Navigate to the **Knowledge Base** tab:
- Drag-and-drop or select multiple documents (`.md`, `.pdf`, `.txt`, `.html`, `.json`, `.csv`).
- Watch real-time progress bars as text is extracted, chunked, and embedded into Supabase `pgvector`.

---

## 📡 REST API Reference

| Endpoint | Method | Access | Description |
| :--- | :--- | :--- | :--- |
| `/wp-json/mteja/v1/visitor/session` | `POST` | Public | Initiates visitor token session (`tok_...`) |
| `/wp-json/mteja/v1/visitor/messages` | `GET` | Public | Fetches conversation messages |
| `/wp-json/mteja/v1/visitor/messages` | `POST` | Public | Submits visitor message, runs RAG, executes AI completion |
| `/wp-json/mteja/v1/conversations` | `GET` | Agent | Lists support tickets with queue filtering |
| `/wp-json/mteja/v1/conversations/{id}/takeover` | `POST` | Agent | Transitions ticket to `human_active` |
| `/wp-json/mteja/v1/conversations/{id}/handover-ai` | `POST` | Agent | Returns ticket to `ai_handling` and triggers AI response |
| `/wp-json/mteja/v1/conversations/{id}/resolve` | `POST` | Agent | Marks ticket as `resolved` |
| `/wp-json/mteja/v1/conversations/{id}/reopen` | `POST` | Agent | Reopens ticket back to active queue |
| `/wp-json/mteja/v1/knowledge` | `GET` | Agent | Lists ingested knowledge documents |
| `/wp-json/mteja/v1/knowledge` | `POST` | Admin | Multi-file batch document upload and vectorization |
| `/wp-json/mteja/v1/knowledge/{id}` | `DELETE` | Admin | Deletes document and Supabase chunks |
| `/wp-json/mteja/v1/studio/test` | `POST` | Agent | Interactive RAG & similarity test playground |
| `/wp-json/mteja/v1/supabase/test` | `POST` | Admin | Tests Supabase REST & pgvector RPC connectivity |

---

## 📄 License
GPLv2 or later. Built with ❤️ for autonomous WordPress customer operations.
