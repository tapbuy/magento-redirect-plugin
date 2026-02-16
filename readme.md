# Tapbuy Redirect and Tracking for Magento 2

This module integrates Tapbuy's checkout experience with Magento 2, enabling A/B testing capabilities for checkout flows and transaction tracking through GraphQL API.

## Features

- A/B testing for checkout experiences via GraphQL
- Customizable redirection settings for mobile and desktop devices
- Transaction tracking with order placement events
- Secure communication with Tapbuy API using AES encryption
- Seamless integration with Magento's checkout flow
- Cookie-based session management for A/B test tracking
- Headless integration

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
- **Is Mobile Redirection Enabled**: Enable or disable redirection for mobile devices
- **Is Desktop Redirection Enabled**: Enable or disable redirection for desktop devices

### API Settings
- **API URL for Tapbuy**: Your Tapbuy API endpoint URL (default: https://api.tapbuy.io)
- **API Key for Tapbuy**: Your Tapbuy API key (encrypted)
- **Encryption Key for Tapbuy**: Your Tapbuy AES encryption key (encrypted)

### Gifting Settings
- **Is Gifting Enabled**: Enable or disable the gifting feature
- **Gifting URL**: Your Tapbuy gifting URL

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
   - API requests include proper headers and authentication
   - Sensitive configuration values are encrypted in the database

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

## Development Mode

In development mode, SSL verification is disabled for API requests to facilitate testing with local/staging environments.

## Support

For support issues, please contact Tapbuy support or create an issue in the repository.

## License

This module is licensed under the Open Software License v. 3.0 (OSL-3.0).