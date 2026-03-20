# Tapbuy Redirect and Tracking for Magento 2

This module integrates Tapbuy's checkout experience with Magento 2, enabling A/B testing capabilities for checkout flows and transaction tracking through GraphQL API.

## Features

- A/B testing for checkout experiences via GraphQL
- Transaction tracking with order placement events
- Secure communication with Tapbuy API using AES encryption
- Seamless integration with Magento's checkout flow
- Cookie-based session management for A/B test tracking
- Headless integration
- Centralized structured logging (JSON) with automatic PII anonymization via [tapbuy/data-scrubber](https://github.com/tapbuy/data-scrubber)

## Installation

### Via Composer

```bash
composer require tapbuy/module-redirect-tracking
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual Installation

1. Create the following directory structure in your Magento installation:
   ```
   app/code/Tapbuy/RedirectTracking/
   ```

2. Extract the module contents to this directory

3. Enable the module:
   ```bash
   bin/magento module:enable Tapbuy_RedirectTracking
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento setup:static-content:deploy -f
   bin/magento cache:flush
   ```

## Configuration

1. Log in to the Magento admin panel
2. Navigate to **Stores > Configuration > Tapbuy > Checkout Settings**
3. Configure the following settings:

### General Settings
- **Is Tapbuy Enabled**: Enable or disable the module

### API Settings
- **API URL for Tapbuy**: Your Tapbuy API endpoint URL (default: https://api.tapbuy.io)
- **Encryption Key for Tapbuy**: Your Tapbuy AES encryption key (encrypted)
- **Locale Format**: How the store locale is sent to the Tapbuy API — `Long` (e.g. `en_US`) or `Short` (e.g. `en`)

### Tracking Settings
- **Order Confirmation Mode**: Controls how order tracking is fired after placement:
  - `GraphQL Mutation` — fired by the frontend after order placement (carries variation ID from cookies)
  - `OrderSaveAfter Observer` — fired by the Magento observer on order save (no cookie context)
  - `Both` — fires both mechanisms

## GraphQL Usage

The module provides a GraphQL query to trigger A/B testing and handle redirects:

### Query

```graphql
query TapbuyRedirect($input: TapbuyRedirectInput!) {
    tapbuyRedirect(input: $input) {
        redirect
        redirect_url
        message
    }
}
```

### Input Variables

```json
{
    "input": {
        "cart_id": "string",           // Required: Guest cart ID or customer cart ID
        "force_redirect": "string"     // Optional: Force redirect parameter
    }
}
```

### Response

```json
{
    "data": {
        "tapbuyRedirect": {
            "redirect": true,                              // Boolean indicating if redirect should occur
            "redirect_url": "https://tapbuy.example.com", // URL to redirect to (or /checkout for standard)
            "message": "Redirect to Tapbuy."              // Descriptive message
        }
    }
}
```

### Example Usage

```javascript
// Frontend GraphQL query example
const TAPBUY_REDIRECT_QUERY = `
    query TapbuyRedirect($input: TapbuyRedirectInput!) {
        tapbuyRedirect(input: $input) {
            redirect
            redirect_url
            message
        }
    }
`;

const variables = {
    input: {
        cart_id: "masked_cart_id_here",
        force_redirect: null
    }
};
```

## How It Works

1. **A/B Test Trigger**: When the GraphQL query `tapbuyRedirect` is called with a cart ID, the module:
   - Validates the cart exists and belongs to the customer (if authenticated)
   - Sends tracking data, cookies, and cart information to Tapbuy API
   - Receives A/B test variation response

2. **Redirection Logic**: Based on the API response:
   - If `redirect: true` and `redirectURL` is provided, customer is redirected to Tapbuy checkout
   - If `redirect: false`, customer continues with standard Magento checkout
   - A/B test ID is stored in cookies for tracking

3. **Transaction Tracking**: When an order is placed:
   - Order data is automatically sent to Tapbuy API via the `OrderSaveAfter` observer
   - Transaction includes order ID, total, payment method, shipping method, and A/B test variation ID

4. **Security**: 
   - Cart and session data is encrypted using AES-256 encryption
   - API requests are authenticated via short-lived Admin JWT tokens (4h TTL); legacy Integration OAuth tokens are accepted during migration
   - Sensitive configuration values are encrypted in the database

## API Authentication

The module uses token-based authentication to secure all GraphQL operations when called from the Tapbuy API. Two token types are supported:

### Admin JWT tokens (recommended)

Short-lived tokens issued via `POST /rest/V1/integration/admin/token`. This is the preferred approach as tokens expire after 4 hours (configurable in **Stores > Configuration > Advanced > Admin > Security > Admin Session Lifetime**), limiting the blast radius of any leaked credential.

**Setup**

1. Create a dedicated Magento admin user (System → All Users → Add New User), e.g. `tapbuy_api`. Do **not** use a personal admin account.
2. Create a restricted role (System → User Roles → Add New Role):
   - Under **Role Resources**, select **Custom** and check `Tapbuy_RedirectTracking::tapbuy` (grants access to all Tapbuy operations). Fine-grained resources are also available under that node if needed.
3. Assign the new user to this role.
4. In the Tapbuy API console, set `api_user` / `api_key` on the retailer to the admin username / password. The API will automatically fetch and rotate tokens.

> **Important**: Run `bin/magento setup:upgrade && bin/magento cache:flush` after installing or updating the module so Magento picks up the ACL resources from `acl.xml`.

### Integration OAuth tokens (legacy — transition period only)

Static OAuth tokens created via **System → Integrations**. These never expire, which is a security risk. They remain supported during the transition period so that retailers can migrate without downtime, but **should be replaced with admin JWT tokens**.

> Revoking or deleting an integration token before updating the module on the Magento side will cause an authentication failure. Always update the module first, then rotate the credential.

### ACL resources

| Resource | Description |
|---|---|
| `Tapbuy_RedirectTracking::tapbuy` | Super-admin — grants all Tapbuy resources |
| `Tapbuy_RedirectTracking::order_view` | Read order data |
| `Tapbuy_RedirectTracking::order_edit` | Edit orders |
| `Tapbuy_RedirectTracking::order_assign` | Assign customer to order |
| `Tapbuy_RedirectTracking::cart_unlock` | Unlock a cart |
| `Tapbuy_RedirectTracking::cart_deactivate` | Deactivate a cart |
| `Tapbuy_RedirectTracking::customer_search` | Search customers |
| `Tapbuy_RedirectTracking::customer_view` | View customer data |
| `Tapbuy_RedirectTracking::modules_versions` | Read module versions |
| `Tapbuy_RedirectTracking::logs` | Fetch Tapbuy logs |

## Cookie Management

The module manages the following cookies:

- **tb-abtest-id**: Stores the A/B test variation ID (NOT HttpOnly to allow JavaScript access, Secure on HTTPS, 1-day duration)
- Tracks various analytics cookies (`_ga`, `_pcid`, etc.) for Tapbuy analysis
- Preserves Magento session cookies for seamless integration

## API Endpoints

The module communicates with these Tapbuy API endpoints:

- `POST /ab-test/variation`: Triggers A/B test and gets redirect decision
- `POST /ab-test/transaction`: Sends transaction data after order placement

### Headless Integration

This module supports headless frontends (Next.js, Vue, SPA, etc.) via a pixel-based cookie sync and tracking mechanism. For details on how to use the pixel endpoint and integrate with your frontend, see [HEADLESS_INTEGRATION.md](./HEADLESS_INTEGRATION.md).

## Requirements

- Magento 2.3.x or higher
- PHP 7.3 or higher
- phpseclib3 library for AES encryption
- GraphQL support (included in Magento 2.3+)

## Logging

This module provides the **centralized logging system** for all Tapbuy Magento modules. Logs are written to `var/log/tapbuy-checkout.log` in JSON format and can be retrieved via GraphQL for forwarding to Sentry.

For complete documentation on the logging system, including usage examples, best practices, and API integration, see **[docs/LOGGING.md](./docs/LOGGING.md)**.

### Anonymized Logging

Log entries are automatically **anonymized before being written to disk**, using the [tapbuy/data-scrubber](https://github.com/tapbuy/data-scrubber) library. This ensures that sensitive personal data (names, emails, addresses, payment details, etc.) is redacted from log files, even when full context objects are logged.

**How it works:**

- The `Handler` fetches a list of scrubbing keys from a configurable URL (set via **Stores > Configuration > Tapbuy > Checkout Settings > Scrubbing Keys URL**).
- Keys are cached locally to `var/tapbuy-scrubbing-keys.json` to avoid repeated remote fetches.
- On every log write, the full record (message, context, extra) is passed through `Anonymizer::anonymizeObject()` before formatting and writing.
- Anonymization is **fail-open**: if the scrubbing keys URL is not configured, or if initialization fails for any reason, the record is written as-is so that logging is never blocked.

**Configuration:**

| Setting | Description |
|---|---|
| **Scrubbing Keys URL** | Remote URL that returns the JSON list of fields to redact. Leave empty to disable anonymization. |

> The scrubbing keys are managed centrally by the Tapbuy API and define which field names (e.g. `email`, `firstname`, `card_number`) should be replaced with anonymized tokens in log output.

## Development Mode

In development mode, SSL verification is disabled for API requests to facilitate testing with local/staging environments.

## Support

For support issues, please contact Tapbuy support or create an issue in the repository.

## Development

### Running Tests

Tests run inside a Docker container that replicates the CI environment (PHP 8.3, Magento 2.4.7-p5). Docker must be running.

**First-time setup:**

```bash
cp auth.json.dist auth.json
# Fill in your repo.magento.com public/private keys in auth.json
```

**Run all unit tests:**

```bash
make test
```

On the first run, the Docker image is built and Magento is installed into a named volume (`tapbuy-magento-2.4.7-p5-php83`). Subsequent runs reuse the cached volume and are fast.

> Do not use `composer test` — it runs PHPUnit without the Magento bootstrap and will fail or produce misleading results.

## License

This module is licensed under the Open Software License v. 3.0 (OSL-3.0).