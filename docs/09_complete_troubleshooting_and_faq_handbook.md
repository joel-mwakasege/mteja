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

### Q20: How does Mteja handle greetings (like "hi" or "hello") without falsely escalating to human staff?
**Answer:** Mteja employs a two-tier guardrail system. For polite greetings and conversational inquiries, the AI immediately welcomes the visitor warmly, introduces itself as the support assistant for the store, and asks how it can help. The AI remains in `ai_handling` status and will never falsely transfer to human staff or ask for an email address solely for greetings.

### Q21: Why does Mteja deflect off-topic questions (e.g., waffles or weather)?
**Answer:** Mteja is an autonomous support desk for your store and business. Strict operational guardrails ensure that off-topic inquiries (such as food orders, cooking recipes, weather forecasts, general trivia, or homework) are politely declined and steered back to your store's products, orders, shipping, and support services.

### Q22: How can a customer or operator hand a conversation back to AI using `/aisupport`?
**Answer:** Both visitors and human operators can instantly return any active or waiting conversation to autonomous AI support by typing `/aisupport` (or `/ai`) in the chat. The system immediately sets status to `ai_handling`, unassigns any human operator, and the AI actively answers future inquiries based on your knowledge base. Operators can also click the **"Handover to AI"** button in the Operations Desk header.

