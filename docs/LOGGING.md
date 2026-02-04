# Tapbuy Centralized Logging System

This document describes the centralized logging system for Tapbuy Magento modules.

## Overview

The Tapbuy logging system provides a centralized, structured logging mechanism that:

- Writes JSON-formatted logs to a rotating log file in Magento
- Captures full stack traces for errors and exceptions
- Exposes logs via a GraphQL mutation for retrieval
- Integrates with tapbuy-api for forwarding to Sentry

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         MAGENTO STORE                               │
│                                                                     │
│  ┌──────────────┐    ┌──────────────┐    ┌───────────────────────┐  │
│  │ Tapbuy       │    │ TapbuyLogger │    │ var/log/              │  │
│  │ Modules      │───▶│ + Handler    │───▶│ tapbuy-checkout.log   │  │
│  │ (adyen,alma) │    │              │    │ (JSON, rotating)      │  │
│  └──────────────┘    └──────────────┘    └───────────────────────┘  │
│                                                   │                 │
│                                                   ▼                 │
│                                          ┌───────────────────┐      │
│                                          │ GraphQL Mutation  │      │
│                                          │ tapbuyFetchAnd    │      │
│                                          │ ClearLogs         │      │
│                                          └───────────────────┘      │
└─────────────────────────────────────────────────────────────────────┘
                                                   │
                                                   │ Poll (every 5 min)
                                                   │ + on-error fetch
                                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          TAPBUY API                                 │
│                                                                     │
│  ┌──────────────────────┐    ┌──────────────┐    ┌───────────────┐  │
│  │ MagentoGraphql       │    │ LogFetch     │    │               │  │
│  │ Webservice           │───▶│ RateLimiter  │───▶│    Sentry     │  │
│  │ fetchTapbuyLogs()    │    │ (60s cooldown│    │               │  │
│  └──────────────────────┘    └──────────────┘    └───────────────┘  │
│                                                                     │
│  ┌──────────────────────┐                                           │
│  │ Cron Command         │                                           │
│  │ tapbuy:magento:      │                                           │
│  │ fetch-logs           │                                           │
│  └──────────────────────┘                                           │
└─────────────────────────────────────────────────────────────────────┘
```

## Usage in Magento Modules

### 1. Add Dependency

In your module's `composer.json`:

```json
{
    "require": {
        "tapbuy/redirect-tracking": "^1.5"
    }
}
```

### 2. Inject the Logger

In your module's `etc/di.xml`:

```xml
<type name="Your\Module\YourClass">
    <arguments>
        <argument name="logger" xsi:type="object">Tapbuy\RedirectTracking\Logger\TapbuyLogger</argument>
    </arguments>
</type>
```

### 3. Use in Your Class

```php
<?php

namespace Your\Module;

use Tapbuy\RedirectTracking\Logger\TapbuyLogger;

class YourClass
{
    private TapbuyLogger $logger;

    public function __construct(TapbuyLogger $logger)
    {
        $this->logger = $logger;
    }

    public function doSomething(): void
    {
        // Basic logging
        $this->logger->info('Processing started', ['order_id' => 123]);
        $this->logger->warning('Low stock detected', ['sku' => 'ABC123']);
        $this->logger->error('Payment failed', ['error_code' => 'E001']);

        // Exception logging with full stacktrace
        try {
            $this->riskyOperation();
        } catch (\Exception $e) {
            $this->logger->logException('Payment processing failed', $e, [
                'cart_id' => $cartId,
                'amount' => $amount,
            ]);
        }
    }
}
```

## Log Levels

| Level | Value | Use Case |
|-------|-------|----------|
| `DEBUG` | 100 | Detailed debug information |
| `INFO` | 200 | Interesting events (user logs in, SQL logs) |
| `NOTICE` | 250 | Normal but significant events |
| `WARNING` | 300 | Exceptional occurrences that are not errors |
| `ERROR` | 400 | Runtime errors that do not require immediate action |
| `CRITICAL` | 500 | Critical conditions (component unavailable) |
| `ALERT` | 550 | Action must be taken immediately |
| `EMERGENCY` | 600 | System is unusable |

## Log File Location

Logs are written to:
```
var/log/tapbuy-checkout.log
```

### Log Rotation

- Maximum 3 rotated files are kept
- Files are named: `tapbuy-checkout.log`, `tapbuy-checkout-2026-02-03.log`, etc.
- Older files are automatically deleted

### Log Format

Each log entry is a JSON object on a single line:

```json
{
    "message": "Payment processing failed",
    "context": {
        "cart_id": "abc123",
        "exception": {
            "class": "Magento\\Framework\\Exception\\LocalizedException",
            "message": "Card declined",
            "code": 0,
            "file": "/var/www/app/code/Tapbuy/Adyen/Model/Payment.php",
            "line": 142,
            "stacktrace": "#0 /var/www/vendor/magento/..."
        }
    },
    "level": 400,
    "level_name": "ERROR",
    "channel": "tapbuy",
    "datetime": "2026-02-03T14:30:00.123456+00:00"
}
```

## GraphQL API

### Fetch and Clear Logs

The `tapbuyFetchAndClearLogs` mutation retrieves all log entries and clears them atomically.

**Requirements:**
- Integration token authorization (Bearer token)

**Request:**
```graphql
mutation {
    tapbuyFetchAndClearLogs(limit: 100) {
        logs {
            message
            level
            level_name
            context
            stacktrace
            error_details
            datetime
            channel
        }
    }
}
```

**Response:**
```json
{
    "data": {
        "tapbuyFetchAndClearLogs": {
            "logs": [
                {
                    "message": "Payment processing failed",
                    "level": 400,
                    "level_name": "ERROR",
                    "context": "{\"cart_id\":\"abc123\"}",
                    "stacktrace": "#0 /var/www/...",
                    "error_details": "{\"class\":\"Exception\",\"message\":\"...\"}",
                    "datetime": "2026-02-03T14:30:00+00:00",
                    "channel": "tapbuy"
                }
            ]
        }
    }
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `message` | String! | The log message |
| `level` | Int! | Numeric log level (100-600) |
| `level_name` | String! | Human-readable level (DEBUG, INFO, ERROR, etc.) |
| `context` | String | JSON-encoded context data |
| `stacktrace` | String | Stack trace for errors/exceptions |
| `error_details` | String | JSON-encoded exception details |
| `datetime` | String! | ISO 8601 formatted timestamp |
| `channel` | String | Logger channel name |

## Tapbuy API Integration

### Automatic Log Fetching

The tapbuy-api automatically fetches logs:

1. **On Cron** (every 5 minutes):
   ```bash
   php bin/console tapbuy:magento:fetch-logs
   ```

2. **On Error** (with rate limiting):
   - When GraphQL errors occur, logs are fetched with a 60-second cooldown per retailer
   - Prevents excessive API calls during error storms

### Manual Log Fetching

```bash
# Fetch from all MagentoGraphql retailers
php bin/console tapbuy:magento:fetch-logs

# Fetch from specific retailer
php bin/console tapbuy:magento:fetch-logs 123

# Limit entries per retailer
php bin/console tapbuy:magento:fetch-logs --limit=50

# Dry run (show what would be fetched)
php bin/console tapbuy:magento:fetch-logs --dry-run
```

### Sentry Integration

Logs are automatically forwarded to Sentry with:
- Original message prefixed with `[Magento]`
- Full context preserved
- Stacktrace attached
- Tags: `source: magento`, `magento_channel: tapbuy`

## Best Practices

### 1. Always Include Context

```php
// ❌ Bad
$this->logger->error('Something failed');

// ✅ Good
$this->logger->error('Payment failed', [
    'order_id' => $orderId,
    'payment_method' => $method,
    'error_code' => $errorCode,
]);
```

### 2. Use `logException()` for Exceptions

```php
// ❌ Bad - loses stacktrace
$this->logger->error('Error: ' . $e->getMessage());

// ✅ Good - captures full exception details
$this->logger->logException('Payment processing failed', $e, [
    'order_id' => $orderId,
]);
```

### 3. Choose Appropriate Log Levels

```php
// Debug: temporary debugging
$this->logger->debug('Cart contents', ['items' => $items]);

// Info: business events
$this->logger->info('Order placed', ['order_id' => $orderId]);

// Warning: recoverable issues
$this->logger->warning('Retry attempt', ['attempt' => 2]);

// Error: failures requiring attention
$this->logger->error('Payment declined', ['reason' => $reason]);

// Critical: system-level failures
$this->logger->critical('Database connection lost');
```

### 4. Don't Log Sensitive Data

```php
// ❌ Never log
$this->logger->info('Card', ['number' => $cardNumber]); // PCI violation!
$this->logger->info('User', ['password' => $password]);

// ✅ Safe to log
$this->logger->info('Payment', ['last_four' => $lastFour]);
```

## Troubleshooting

### Logs Not Appearing

1. Check file permissions on `var/log/`:
   ```bash
   ls -la var/log/tapbuy-checkout*.log
   ```

2. Verify logger is properly injected in `di.xml`

3. Check Magento logs for DI errors:
   ```bash
   tail -f var/log/system.log
   ```

### GraphQL Returns Empty Logs

1. Verify integration token is valid
2. Check if logs exist:
   ```bash
   cat var/log/tapbuy-checkout.log | head -10
   ```

3. Verify the Authorization header is sent correctly

### Rate Limiting Issues

If logs aren't being fetched from tapbuy-api:
- Rate limiter has a 60-second cooldown per retailer
- Check cache for rate limit keys
- Use `--dry-run` to verify retailers are detected

## Module Dependencies

```
redirect-tracking (base - owns the logger)
    ↑
    ├── checkout-graphql
    ├── adyen
    ├── alma
    └── magento2-forter
```

All modules depend on `redirect-tracking` for the centralized logger.
