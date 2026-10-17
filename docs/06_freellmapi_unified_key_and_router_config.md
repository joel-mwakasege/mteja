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
