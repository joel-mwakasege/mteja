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
