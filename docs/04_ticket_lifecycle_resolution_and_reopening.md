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
