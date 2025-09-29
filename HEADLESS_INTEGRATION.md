# Tapbuy Redirect Tracking - Headless Frontend Integration

## Overview

This module now supports headless frontend integration through pixel-based cookie management. The `tapbuyRedirect` GraphQL query returns a `pixel_url` that headless frontends can use to synchronize cookies with the Magento backend.

## GraphQL API

### Query: `tapbuyRedirect`

```graphql
query TapbuyRedirect($input: TapbuyRedirectInput!) {
  tapbuyRedirect(input: $input) {
    redirect
    redirect_url
    message
    pixel_url  # New field for headless integration
  }
}
```

**Input:**
```graphql
input TapbuyRedirectInput {
  cart_id: String!
  force_redirect: String
}
```

**Response:**
```graphql
type TapbuyRedirectResult {
  redirect: Boolean!
  redirect_url: String
  message: String
  pixel_url: String  # URL for pixel tracking in headless frontends
}
```

## Pixel Endpoint

The module provides a pixel tracking endpoint at:

```
GET /tapbuy/pixel/track?data={base64_encoded_json}
```

**Returns:** 1x1 transparent GIF with appropriate cookies set


## Headless Integration Flow

### 1. Frontend calls GraphQL
```javascript
const response = await graphql(TAPBUY_REDIRECT_QUERY, {
  input: { cart_id: "masked_cart_id" }
});
```

### 2. Response includes pixel URL
```json
{
  "data": {
    "tapbuyRedirect": {
      "redirect": false,
      "redirect_url": "/checkout",
      "message": "Redirect to standard checkout.",
      "pixel_url": "https://store.com/tapbuy/pixel/track?data=eyJ..."
    }
  }
}
```

### 3. Frontend fires pixel using the NPM module (with cookies)
```javascript
import { TapbuyPixelTracker } from '@tapbuy/pixel-tracker';

const tracker = new TapbuyPixelTracker({
  cookies: { keys: ['tb-abtest-id', '_ga', '_pcid'] } // or 'all' for all cookies
});
await tracker.firePixel(response.data.tapbuyRedirect.pixel_url);
```

**How it works:**
- The NPM module will automatically collect the specified cookies from the browser and append them as query parameters to the pixel URL, e.g.:

```
https://store.com/tapbuy/pixel/track?data=...&cookie_tb-abtest-id=abc123&cookie_sessionId=xyz456
```

### 4. Magento pixel endpoint receives cookies
- The backend extracts all query parameters starting with `cookie_` and can use them for tracking, A/B test state, or analytics.
- Example: If `cookie_tb-abtest-id` is present, it will be set as the A/B test cookie on the backend.

### 5. Pixel request sets cookies (if needed)
- The backend can still set or update cookies (e.g. `tb-abtest-id`) in the response, ensuring compatibility with both traditional and headless flows.


## Pixel Data Structure

The pixel URL contains base64-encoded JSON with:

```json
{
  "cart_id": "masked_cart_id",
  "test_id": "abtest_variation_id",
  "action": "redirect_check",
  "timestamp": 1234567890,
  "variation_id": "variation_123",
  "remove_test_cookie": false
}
```

Additionally, cookies are sent as query parameters with the prefix `cookie_`:

```
https://store.com/tapbuy/pixel/track?data=...&cookie_tb-abtest-id=abc123&cookie_sessionId=xyz456
```


## Benefits for Headless Frontends

- **Cookie Synchronization:** Maintains A/B test state across domains, even if set client-side
- **Analytics Continuity:** Preserves tracking data between frontend and backend
- **Cross-Domain Support:** Works with different frontend domains
- **Lightweight:** Minimal impact on frontend performance
- **Framework Agnostic:** Works with any headless technology
- **Flexible Cookie Collection:** Choose which cookies to send with each pixel request

## Configuration

No additional configuration required. The pixel functionality is automatically available when the Tapbuy Redirect Tracking module is enabled.

## Error Handling

- **Invalid pixel data:** Logs error, returns blank pixel
- **Missing test ID:** Cookie is removed/not set
- **API failures:** Graceful fallback with appropriate logging

## Security

- **HTTPS Only:** Cookies use secure flag in production
- **HTTP-Only:** Prevents JavaScript access to sensitive cookies
- **Base64 Encoding:** Simple obfuscation of pixel data
- **Domain Validation:** Cookies respect domain policies