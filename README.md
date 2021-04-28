# Tamara Mangeto extension

This extension allows you to use tamara as a payment gateway in your Magento 2.3 store.

# Installation steps

### Installation Using Composer (Recommended)

composer require tamara-solution/magento

php bin/magento setup:upgrade

php bin/magento setup:di:compile

php bin/magento setup:static-content:deploy

php bin/magento cache:clean

### Manual Setup
In your Magento 2.3 [ROOT]/app/code/ create folder called Tamara/Checkout.

Download and extract files from this github repository to the folder.

Open the command line interface.

Run the following command to install our php sdk:

```bash
composer require tamara-solution/php-sdk
```

Enable tamara Checkout by running below command: 

```php bin/magento module:enable Tamara_Checkout```

Magento setup upgrade: 

```bash
php bin/magento setup:upgrade
```

Magento Dependencies Injection Compile: 

```bash
php bin/magento setup:di:compile
```

Magento Static Content deployment: 

```bash
php bin/magento setup:static-content:deploy
```
Login to Magento Admin and navigate to System/Cache Management

Flush the cache storage by selecting Flush Cache Storage

### Admin Configuration
Login to your Magento Admin

Navigate to Store > Configuration > Sales > Payment Methods > Tamara Checkout

### API Configuration
Set the API URL that you get from us for example for SANDBOX https://api-sandbox.tamara.co

Set the API Token that you get from us

Set the Notification Token that you get from us

Link to tamara introduction page, this link will be attached onto the tamara payment image that's displayed next to the payment title on the checkout page. Please put https://www.tamara.co

Link login tamara for the customer, this link will be attached onto the tamara customer profile logo after redirecting back from tamara checkout for success case. Please put https://app.tamara.co

Enable trigger to Tamara, this option will allow you to automatically trigger Capture, Refund or Cancellation to tamara when you perform corresponding action with Magento or not. If disabled, you have to integrate with our API directly.

Payment Types

Please click on the Update config button after you finished the previous step with API Configuration to get the latest data from us. This will impact to the user experience on the checkout about allowing to pay with tamara or not base on the limitation.

### Checkout Order Statuses

Here you can specify which statuses you want to update for the order in case of success, failure or cancel from tamara