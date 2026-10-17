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
1. GREETINGS & SMALL TALK: Respond warmly to greetings ("hi", "hello", "how are you") and introduce yourself. Never escalate to human on greetings.
2. STRICT TOPIC GUARDRAILS: Deflect off-topic inquiries (e.g., waffles, recipes, weather, trivia) and steer customers back to store products and support.
3. GROUNDED ANSWERING: Base your answers strictly on the knowledge base excerpts provided in the context. If unknown, offer to connect with human support.
4. HUMAN ESCALATION: When the visitor asks for a human agent, acknowledge politely and append [ESCALATE_TO_HUMAN].
```

---

## 3. Autonomous Guardrail Directives

| Guardrail Tier | Trigger Condition | Autonomous Action | Escalation Status |
| :--- | :--- | :--- | :--- |
| **Tier 1: Greetings & Intro** | "hi", "hello", "good morning" | Warm welcome, introduces store assistant, asks how to assist | Stays in `ai_handling` |
| **Tier 2: Off-Topic Deflection** | "I want waffles", "what's the weather" | Polite refusal, redirects to store products/services | Stays in `ai_handling` |
| **Tier 3: Grounded FAQ & Knowledge** | Policies, shipping, returns, troubleshooting | Synthesizes verified answer from Supabase pgvector chunks | Stays in `ai_handling` |
| **Tier 4: Operator Escalation** | "human please", "agent", complex edge cases | Empathetic transfer notice + emits `[ESCALATE_TO_HUMAN]` | Transitions to `human_waiting` |

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
