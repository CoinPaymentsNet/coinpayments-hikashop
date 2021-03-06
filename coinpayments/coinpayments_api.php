<?php

/**
 * Class CoinpaymentsApi
 */
class CoinpaymentsApi
{

	const API_URL = 'https://api.coinpayments.net';
	const CHECKOUT_URL = 'https://checkout.coinpayments.net';
	const API_VERSION = '1';

	const API_SIMPLE_INVOICE_ACTION = 'invoices';
	const API_WEBHOOK_ACTION = 'merchant/clients/%s/webhooks';
	const API_MERCHANT_INVOICE_ACTION = 'merchant/invoices';
	const API_CURRENCIES_ACTION = 'currencies';
	const API_CHECKOUT_ACTION = 'checkout';
	const FIAT_TYPE = 'fiat';

    const PAID_EVENT = 'Paid';
    const CANCELLED_EVENT = 'Cancelled';

	const WEBHOOK_NOTIFICATION_URL = 'extension/payment/coinpayments/callback';

	/**
	 * @param $client_id
	 * @param $client_secret
	 * @param $notification_url
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function createWebHook($client_id, $client_secret, $notification_url, $event)
	{

		$action = sprintf(self::API_WEBHOOK_ACTION, $client_id);

		$params = array(
			"notificationsUrl" => $notification_url,
			"notifications" => [
                sprintf("invoice%s", $event)
            ],
		);

		return $this->sendRequest('POST', $action, $client_id, $params, $client_secret);
	}

	/**
	 * @param $client_id
	 * @param $invoice_params
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function createSimpleInvoice($client_id, $invoice_params)
	{

		$action = self::API_SIMPLE_INVOICE_ACTION;

		$params = array(
			'clientId' => $client_id,
			'invoiceId' => $invoice_params['invoice_id'],
			'amount' => array(
				'currencyId' => $invoice_params['currency_id'],
				"displayValue" => $invoice_params['display_value'],
				'value' => $invoice_params['amount']
			),
            "notesToRecipient" => $invoice_params['notes_link']
		);

        $params = $this->append_billing_data($params, $invoice_params['billing_data'], $invoice_params['email']);
		$params = $this->appendInvoiceMetadata($params);
		return $this->sendRequest('POST', $action, $client_id, $params);
	}

	/**
	 * @param $client_id
	 * @param $client_secret
	 * @param $invoice_params
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function createMerchantInvoice($client_id, $client_secret, $invoice_params)
	{

		$action = self::API_MERCHANT_INVOICE_ACTION;

		$params = array(
			"invoiceId" => $invoice_params['invoice_id'],
			"amount" => array(
				"currencyId" => $invoice_params['currency_id'],
				"displayValue" => $invoice_params['display_value'],
				"value" => $invoice_params['amount']
			),
            "notesToRecipient" => $invoice_params['notes_link']
		);

        $params = $this->append_billing_data($params, $invoice_params['billing_data'], $invoice_params['email']);
		$params = $this->appendInvoiceMetadata($params);
		return $this->sendRequest('POST', $action, $client_id, $params, $client_secret);
	}

    /**
     * @param $request_data
     * @param $billing_data
     * @param $email
     * @return array
     */
    function append_billing_data($request_data, $billing_data, $email)
    {
        $request_data['buyer'] = array(
            "name" => array(
                "firstName" => $billing_data->address_firstname,
                "lastName" => $billing_data->address_lastname
            ),
            "phoneNumber" => $billing_data->address_telephone,
            "emailAddress" => $email
        );
        if (preg_match('/^([A-Z]{2})$/', $billing_data->address_country->zone_code_2)
            && !empty($billing_data->address_street)
            && !empty($billing_data->address_city)
        ) {
            $request_data['buyer']['address'] = array(
                'address1' => $billing_data->address_street,
                'provinceOrState' => $billing_data->address_state->zone_name_english,
                'city' => $billing_data->address_city,
                'countryCode' => $billing_data->address_country->zone_code_2,
                'postalCode' => $billing_data->address_post_code,
            );
        }
        return $request_data;
    }


    /**
	 * @param string $name
	 * @return mixed
	 * @throws Exception
	 */
	public function getCoinCurrency($name)
	{

		$params = array(
			'types' => self::FIAT_TYPE,
			'q' => $name,
		);
		$items = array();

		$listData = $this->getCoinCurrencies($params);
		if (!empty($listData['items'])) {
			$items = $listData['items'];
		}

		return array_shift($items);
	}

	/**
	 * @param array $params
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function getCoinCurrencies($params = array())
	{
		return $this->sendRequest('GET', self::API_CURRENCIES_ACTION, false, $params);
	}

	/**
	 * @param $client_id
	 * @param $client_secret
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function getWebhooksList($client_id, $client_secret)
	{

		$action = sprintf(self::API_WEBHOOK_ACTION, $client_id);

		return $this->sendRequest('GET', $action, $client_id, null, $client_secret);
	}

	/**
	 * @return string
	 */
	public function getNotificationUrl($client_id,$event)
	{
		return HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=coinpayments&tmpl=component' . '&clientId='.$client_id . '&event='.$event;
	}

	/**
	 * @param $signature_string
	 * @param $client_secret
	 * @return string
	 */
	public function encodeSignatureString($signature_string, $client_secret)
	{
		return base64_encode(hash_hmac('sha256', $signature_string, $client_secret, true));
	}

	/**
	 * @param $request_data
	 * @return mixed
	 */
	protected function appendInvoiceMetadata($request_data)
	{

		$request_data['metadata'] = array(
			"integration" => sprintf("Hikashop %s", HIKASHOP_VERSION),
			"hostname" => HIKASHOP_LIVE,
		);

		return $request_data;
	}

	/**
	 * @param $method
	 * @param $api_url
	 * @param $client_id
	 * @param $date
	 * @param $client_secret
	 * @param $params
	 * @return string
	 */
	protected function createSignature($method, $api_url, $client_id, $date, $client_secret, $params)
	{

		if (!empty($params)) {
			$params = json_encode($params);
		}

		$signature_data = array(
			chr(239),
			chr(187),
			chr(191),
			$method,
			$api_url,
			$client_id,
			$date->format('c'),
			$params
		);

		$signature_string = implode('', $signature_data);

		return $this->encodeSignatureString($signature_string, $client_secret);
	}

	/**
	 * @param $action
	 * @return string
	 */
	protected function getApiUrl($action)
	{
		return sprintf('%s/api/v%s/%s', self::API_URL, self::API_VERSION, $action);
	}

	/**
	 * @param $method
	 * @param $api_action
	 * @param $client_id
	 * @param null $params
	 * @param null $client_secret
	 * @return bool|mixed
	 * @throws Exception
	 */
	protected function sendRequest($method, $api_action, $client_id, $params = null, $client_secret = null)
	{

		$response = false;

		$api_url = $this->getApiUrl($api_action);
		$date = new \Datetime();
		try {

			$curl = curl_init();

			$options = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
			);

			$headers = array(
				'Content-Type: application/json',
			);

			if ($client_secret) {
				$signature = $this->createSignature($method, $api_url, $client_id, $date, $client_secret, $params);
				$headers[] = 'X-CoinPayments-Client: ' . $client_id;
				$headers[] = 'X-CoinPayments-Timestamp: ' . $date->format('c');
				$headers[] = 'X-CoinPayments-Signature: ' . $signature;

			}

			$options[CURLOPT_HTTPHEADER] = $headers;

			if ($method == 'POST') {
				$options[CURLOPT_POST] = true;
				$options[CURLOPT_POSTFIELDS] = json_encode($params);
			} elseif ($method == 'GET' && !empty($params)) {
				$api_url .= '?' . http_build_query($params);
			}

			$options[CURLOPT_URL] = $api_url;

			curl_setopt_array($curl, $options);

			$response = json_decode(curl_exec($curl), true);

			curl_close($curl);

		} catch (Exception $e) {

		}
		return $response;
	}

}
