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
