# Tapbuy Redirect and Tracking  for Magento 2

This module integrates Tapbuy's checkout experience with Magento 2, enabling A/B testing capabilities for checkout flows and transaction tracking.

## Features

- A/B testing for checkout experiences
- Customizable redirection settings for mobile and desktop devices
- Transaction tracking
- Secure communication with Tapbuy API
- Seamless integration with Magento's checkout flow

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
- **API URL for Tapbuy**: Your Tapbuy API endpoint URL
- **API Key for Tapbuy**: Your Tapbuy API key
- **Encryption Key for Tapbuy**: Your Tapbuy encryption key

### Gifting Settings
- **Is Gifting Enabled**: Enable or disable the gifting feature
- **Gifting URL**: Your Tapbuy gifting URL

## How It Works

1. When a customer enters the checkout flow, the module triggers an A/B test via the Tapbuy API
2. Based on the API response, the customer may be redirected to an alternative checkout experience
3. When an order is placed, transaction data is sent to Tapbuy for analysis

## Requirements

- Magento 2.3.x or higher
- PHP 7.3 or higher

## Support

For support issues, please contact Tapbuy support or create an issue in the repository.

## License

This module is licensed under the Open Software License v. 3.0 (OSL-3.0).