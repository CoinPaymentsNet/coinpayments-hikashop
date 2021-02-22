<?php

use Joomla\CMS\Factory;

class plgHikashoppaymentCoinpayments extends hikashopPaymentPlugin
{
	var $name = 'coinpayments';
	var $multiple = true;

	var $pluginConfig = array(
		'client_id' => array("Client ID", 'input'),
		'webhooks' => array('Webhooks (use to receive payment notifications)', 'boolean', '0'),
		'client_secret' => array("Client Secret", 'input'),
		'invalid_status' => array('INVALID_STATUS', 'orderstatus'),
		'verified_status' => array('VERIFIED_STATUS', 'orderstatus'),
		'allow_zero_confirm' => array('Enable zero-confirmation payments (recommended for digital downloads only)', 'boolean', '0'),
	);
	/**
	 * @var CoinpaymentsApi
	 */
	protected $api;

	/**
	 * plgHikashoppaymentCoinpayments constructor.
	 * @param $subject
	 * @param $config
	 */
	public function __construct(&$subject, $config)
	{

		$this->api = new CoinpaymentsApi();

		return parent::__construct($subject, $config);
	}

	/**
	 * @param $element
	 */
	public function onPaymentConfiguration(&$element)
	{
		$document = Factory::getDocument();
		$base = (hikashop_isClient('administrator')) ? '..' : JURI::base(true);
		$document->addScript($base . '/plugins/hikashoppayment/coinpayments/coinpayments_admin.js');
		parent::onPaymentConfiguration($element);
	}

	/**
	 * @param $element
	 * @return bool
	 * @throws Exception
	 */
	public function onPaymentConfigurationSave(&$element)
	{

		if (!isset($this->app)) {
			$this->app = JFactory::getApplication();
		}

		$formData = hikaInput::get()->get('data', array(), 'array');
		if (!isset($formData['payment']['payment_params']))
			return true;


		$client_id = $formData["payment"]["payment_params"]["client_id"];
		$client_secret = $formData["payment"]["payment_params"]["client_secret"];
		$webhooks = $formData["payment"]["payment_params"]["webhooks"];
		try {
			if (!empty($webhooks)) {


				$webhooks_list = $this->api->getWebhooksList($client_id, $client_secret);
				if (!empty($webhooks_list)) {

					$webhooks_urls_list = array();
					if (!empty($webhooks_list['items'])) {
						$webhooks_urls_list = array_map(function ($webHook) {
							return $webHook['notificationsUrl'];
						}, $webhooks_list['items']);
					}

					if (!in_array($this->api->getNotificationUrl($client_id, CoinpaymentsApi::CANCELLED_EVENT), $webhooks_urls_list)) {
						$this->api->createWebHook($client_id, $client_secret, $this->api->getNotificationUrl($client_id, CoinpaymentsApi::CANCELLED_EVENT), CoinpaymentsApi::CANCELLED_EVENT);
					}
                    if (!in_array($this->api->getNotificationUrl($client_id, CoinpaymentsApi::PAID_EVENT), $webhooks_urls_list)) {
                        $this->api->createWebHook($client_id, $client_secret, $this->api->getNotificationUrl($client_id, CoinpaymentsApi::PAID_EVENT), CoinpaymentsApi::PAID_EVENT);
                    }
				} else {
					$this->app->enqueueMessage('You have an error in your CoinPayments credentials!', 'error');
				}
			} else {

				$invoice = $this->api->createSimpleInvoice($client_id);

				if (empty($invoice['id'])) {
					$this->app->enqueueMessage('You have an error in your CoinPayments credentials!', 'error');
				}

			}

		} catch (Exception $e) {
			$this->app->enqueueMessage($e->getMessage(), 'error');
		}
		return false;
	}

	/**
	 * @param $order
	 * @param $methods
	 * @param $method_id
	 * @return bool|void
	 */
	public function onAfterOrderConfirm(&$order, &$methods, $method_id)
	{
		parent::onAfterOrderConfirm($order, $methods, $method_id); // This is a mandatory line in order to initialize the attributes of the payment method


		$success_url = HIKASHOP_LIVE . "index.php?option=com_hikashop&ctrl=checkout&task=after_end";
		$cancel_url = HIKASHOP_LIVE . "index.php?option=com_hikashop&ctrl=order&task=cancel_order";

		$error = false;
		if (empty($this->payment_params->webhooks) && empty($this->payment_params->client_id)) {
			$error = 'You have to enter your CoinPayments Client ID first : check your plugin\'s parameters, on your website backend';
		} elseif (!empty($this->payment_params->webhooks)) {
			if (empty($this->payment_params->client_id)) {
				$error = 'You have to enter your CoinPayments Client ID first : check your plugin\'s parameters, on your website backend';
			}
			if (empty($this->payment_params->client_secret)) {
				$error .= 'You have to enter your CoinPayments Client Secret first : check your plugin\'s parameters, on your website backend';
			}

		}

		if (!$error) {

			$client_id = $this->payment_params->client_id;
			$client_secret = $this->payment_params->client_secret;
			$invoice_id = sprintf('%s|%s', md5(HIKASHOP_LIVE), $order->order_id);
			try {

				$currency_code = $this->currency->currency_code;
				$coin_currency = $this->api->getCoinCurrency($currency_code);

				$amount = number_format($order->cart->full_total->prices[0]->price_value_with_tax, $coin_currency['decimalPlaces'], '', '');;
				$display_value = $order->cart->full_total->prices[0]->price_value_with_tax;

                $invoice_params = array(
                    'invoice_id' => $invoice_id,
                    'currency_id' => $coin_currency['id'],
                    'amount' => $amount,
                    'display_value' => $display_value,
                    'billing_data' => $order->cart->billing_address,
                    'notes_link' => HIKASHOP_LIVE.'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id='.$order->order_id,
                );

				if ($this->payment_params->webhooks) {
					$resp = $this->api->createMerchantInvoice($client_id, $client_secret, $invoice_params);
					$invoice = array_shift($resp['invoices']);
				} else {
					$invoice = $this->api->createSimpleInvoice($client_id, $invoice_params);
				}

				$this->vars = array(
					'success-url' => $success_url,
					'cancel-url' => $cancel_url,
					'invoice-id' => $invoice['id'],
				);
			} catch (Exception $e) {
				$this->app->enqueueMessage($e->getMessage(), 'error');
				return false;
			}
		} else {
			$this->app->enqueueMessage($error, 'error');
			return false;
		}

		return $this->showPage('end');
	}

	/**
	 * @param $element
	 */
	public function getPaymentDefaultValues(&$element)
	{
		$element->payment_name = 'CoinPayments';
		$element->payment_description = 'You can pay with Bitcoin or other cryptocurrencies via CoinPayments';
		$element->payment_images = 'CoinPayments';
		$element->payment_params->address_type = "shipping";
		$element->payment_params->notification = 1;
		$element->payment_params->invalid_status = 'cancelled';
		$element->payment_params->verified_status = 'confirmed';
	}

	/**
	 * @param $statuses
	 * @return false|void
	 */
	public function onPaymentNotification(&$statuses)
	{


		$signature = JRequest::getString('HTTP_X_COINPAYMENTS_SIGNATURE', '', 'SERVER');
		$content = file_get_contents('php://input');
		$request_data = json_decode($content, true);


		$invoice_str = $request_data['invoice']['invoiceId'];
		$invoice_str = explode('|', $invoice_str);
		$host_hash = array_shift($invoice_str);
		$invoice_id = array_shift($invoice_str);

		if ($host_hash == md5(HIKASHOP_LIVE) && $invoice_id) {
			$dbOrder = $this->getOrder($invoice_id);
			$this->loadPaymentParams($dbOrder);
			if (empty($this->payment_params))
				die("Error loading payment params!");
			$this->loadOrderData($dbOrder);
			if ($this->payment_params->webhooks && !empty($signature)) {

				if ($this->checkDataSignature($signature, $content, $request_data['invoice']['status'])) {
					$status = $request_data['invoice']['status'];
					if ($status == CoinpaymentsApi::PAID_EVENT) {
						$this->modifyOrder($invoice_id, $this->payment_params->verified_status, true, true);
					} elseif ($status == CoinpaymentsApi::CANCELLED_EVENT) {
						$this->modifyOrder($invoice_id, $this->payment_params->invalid_status, true, true);
					}
				}
			}
		}

		return false;
	}

	/**
	 * @param $signature
	 * @param $content
	 * @return bool
	 */
	protected function checkDataSignature($signature, $content, $event)
	{

		$request_url = $this->api->getNotificationUrl($this->payment_params->client_id, $event);
		$client_secret = $this->payment_params->client_secret;
		$signature_string = sprintf('%s%s', $request_url, $content);
		$encoded_pure = $this->api->encodeSignatureString($signature_string, $client_secret);
		return $signature == $encoded_pure;
	}

}

spl_autoload_register(function ($classname) {
	switch ($classname) {
		case 'CoinpaymentsApi':
			require_once dirname(__FILE__) . DS . 'coinpayments_api.php';
			break;
	}
});
