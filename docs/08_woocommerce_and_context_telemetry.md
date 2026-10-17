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
