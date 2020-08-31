# Coinpayments payment module for Joomla 2.5/3.x and HikaShop

## The module installation

1. [Download hikashop_coinpayments.zip](https://github.com/CoinPaymentsNet/coinpayments-hikashop/releases/download/hikashop-coinpayments/hikashop_coinpayments.zip)
2. Go to Joomla's administration panel
3. Go to _Manage_ page via the menu _Extensions_
4. Install the module package
  1. Open the page _Install_ and the tab _Upload Package File_
  2. Select the module package file saved at the step 1.
  3. Click _Upload & Install_ to install the module
  4. Go to _Plugins_ via the menu _Extensions_
  5. Locate the plugin _CoinPayments Payment Plugin_ and enable it

## The module configuration

1. Go to Joomla's administration panel
2. Go to HikaShop's administration panel via _Components_ -> _HikaShop_ -> _Configuration_
3. Go to the menua _System_ -> _Payment methods_
4. Click the button _New_ and select _CoinPayments Payment Plugin_
5. Configure the module
  1. Setup the payment method name
  2. Locate the block _Specific configuration_
  3. Enter the Coinpayments.Net Client ID
  4. Enable webhooks and enter  Client Secret to use Coinpayments.net webhook notifications.
  5. Click _Save & Close_
6. Publish the payment method
  1. Select the configured payment method in the list of payment methods
  2. Publish it by clicking an icon in the column _Published_