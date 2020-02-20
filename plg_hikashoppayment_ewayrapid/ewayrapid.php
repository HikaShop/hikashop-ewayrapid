<?php
/**
 *
 */
class plgHikashoppaymentEwayrapid extends hikashopPaymentPlugin
{
	public $accepted_currencies = array(
		'AUD', 'NZD', 'GBP',
		// 'EUR', 'USD', 'JPY'
	);
	public $multiple = true;
	public $name = 'ewayrapid';
	public $doc_form = 'ewayrapid';

	var $pluginConfig = array(
		'api_key' => array('API Key', 'input'),
		'api_password' => array('API Password', 'input'),
		'payment_mode' => array('Payment Mode', 'list', array(
			'web' => 'Reponsive Shared Page',
		//	'redirect' => 'Transparent Redirect',
		//	'direct' => 'Direct connection',
		//	'iframe' => 'IFrame',
		)),
		'currency' => array('CURRENCY', 'list', array(
			'AUD' => 'AUD',
			'NZD' => 'NZD',
			'GBP' => 'GBP',
		)),
		'send_customer_details' => array('Send customer details', 'boolean', '1'),
		'customer_read_only' => array('Customer Read Only', 'boolean', '0'),
		'send_shipping_details' => array('Send shipping details', 'boolean', '1'),
		'invoice_description' => array('Invoice description field', 'input'),
		'sandbox' => array('SANDBOX', 'boolean','0'),
		'debug' => array('DEBUG', 'boolean','0'),
		'cancel_url' => array('CANCEL_URL', 'input'),
		'return_url' => array('RETURN_URL', 'input'),
		'invalid_status' => array('INVALID_STATUS', 'orderstatus', 'cancelled'),
		'pending_status' => array('PENDING_STATUS', 'orderstatus', 'created'),
		'verified_status' => array('VERIFIED_STATUS', 'orderstatus', 'confirmed'),
	);

	/**
	 *
	 */
	public function getClient() {
		if(version_compare(PHP_VERSION, '5.4', '<'))
			return false;

		if(!class_exists('eWayRapidBridge')) {
			include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'ewayrapid_bridge.php';
			eWayRapidBridge::init();
		}

		if(empty($this->payment_params->sandbox))
			$end_point = eWayRapidBridge::MODE_PRODUCTION;
		else
			$end_point = eWayRapidBridge::MODE_SANDBOX;

		return eWayRapidBridge::createClient($this->payment_params->api_key, $this->payment_params->api_password, $end_point);
	}

	/**
	 *
	 */
	public function checkPaymentDisplay(&$method, &$order) {
		if(version_compare(PHP_VERSION, '5.4', '<'))
			return false;

		// Check the internal currency
		if(!empty($this->currency) && !empty($this->payment_params->currency) && $this->currency[$this->currency_id]->currency_code != $this->payment_params->currency)
			return false;

		return true;
	}

	/**
	 *
	 * @param object $order
	 * @param array $methods
	 * @param integer $method_id
	 */
	public function onAfterOrderConfirm(&$order, &$methods, $method_id) {
		parent::onAfterOrderConfirm($order, $methods, $method_id);

		//
		//
		$this->response = $this->generateTransaction($order, $method_id);

		// Store the Access Code directly in the order object
		//
		if(!$this->response->getErrors()) {
			$update_order = new stdClass;
			$update_order->order_id = (int)$order->order_id;
			$update_order->order_payment_params = @$order->order_payment_params;

			if(!empty($update_order->order_payment_params) && is_string($update_order->order_payment_params))
				$update_order->order_payment_params = unserialize($update_order->order_payment_params);
			if(empty($update_order->order_payment_params))
				$update_order->order_payment_params = new stdClass;

			$update_order->order_payment_params->eway_accesscode = $this->response->AccessCode;

			$orderClass = hikashop_get('class.order');
			$orderClass->save($update_order);
		} else {
			$app = JFactory::getApplication();
			$error_msg = array();
			foreach($this->response->getErrors() as $error) {
				$msg = eWayRapidBridge::getErrorMessage(trim($error));
				if(!(empty($msg) && is_string($msg))
					$error_mgs[] = $msg;
			}
			$msg = 'eWay Errors<br/>';
			if(count($error_msg))
				$msg .= implode('<br/>', $error_msgs);
			$this->app->enqueueMessage($msg, 'error');
		}

		return $this->showPage('end');
	}

	/**
	 *
	 */
	public function onPaymentNotification(&$statuses) {
		$order_id = (int)@$_GET['order_id'];
		$access_code = @$_GET['AccessCode'];

		if(empty($order_id) || empty($access_code))
			return false;

		// load order
		//
		$dbOrder = $this->getOrder((int)$order_id);
		$this->loadPaymentParams($dbOrder);
		if(empty($this->payment_params))
			return false;
		$this->loadOrderData($dbOrder);

		$cancel_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id=' . $dbOrder->order_id . $this->url_itemid;
		$confirm_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id=' . $dbOrder->order_id . $this->url_itemid;

		$client = $this->getClient();
		$response = $client->queryTransaction($access_code);
		$transactionResponse = $response->Transactions[0];

		// Debug
		if(!empty($this->payment_params->debug)) {
			$this->writeToLog('eWay notification:'."\r\n".print_r($transactionResponse, true));
		}

		$order_status = $this->payment_params->invalid_status;
		$email = false;
		$history = new stdClass();
		$history->notified = 0;
		$history->amount = $dbOrder->order_full_price;
		$history->data = '';

		//
		//
		if($transactionResponse->TransactionStatus) {
			$history->data = 'Payment ID: '.$transactionResponse->TransactionID;
			$history->notified = 1;
			$email = true;
			$order_status = $this->payment_params->verified_status;

			$this->modifyOrder($order_id, $order_status, $history, $email);
			$this->app->redirect($confirm_url);
			return true;
		}

		//
		//
		$errors = explode(',', $transactionResponse->ResponseMessage);
		if(count($errors) == 1 && $errors[0] == 'D4406' && isset($_GET['user_cancel'])) {
			// User cancelled
		} else {
			$error_msg = array();
			foreach($errors as $error) {
				$error_mgs[] = eWayRapidBridge::getErrorMessage(trim($error));
			}
			$this->app->enqueueMessage(implode('<br/>', $error_msgs), 'error');
		}

		$this->modifyOrder($order_id, $order_status, $history, $email);
		$this->app->redirect($cancel_url);
		return true;
	}

	/**
	 *
	 */
	private function generateTransaction($order, $method_id, $options = array()) {
		$client = $this->getClient();

		$notify_url = HIKASHOP_LIVE.$this->name.'_'.$method_id.'.php?order_id='.$order->order_id.$this->url_itemid;
		$notify_cancel_url = HIKASHOP_LIVE.$this->name.'_'.$method_id.'.php?order_id='.$order->order_id.'&user_cancel=1'.$this->url_itemid;

		if($this->currency->currency_locale['int_frac_digits'] > 2)
			$this->currency->currency_locale['int_frac_digits'] = 2;

		$amount = round($order->cart->full_total->prices[0]->price_value_with_tax, (int)$this->currency->currency_locale['int_frac_digits']);
		$amount *= pow(10, (int)$this->currency->currency_locale['int_frac_digits']);

		$transaction = array(
			'Method' => 'ProcessPayment',
			'TransactionType' => eWayRapidBridge::getTransaction( eWayRapidBridge::TRANSACTION_PURCHASE ),
			'RedirectUrl' => $notify_url,
			'Payment' => array(
				'TotalAmount' => (int)$amount,
				'CurrencyCode' => $this->payment_params->currency,
				'InvoiceReference' => $order->order_number,
			),
			'Customer' => array(
				'Title' => @$order->cart->billing_address->address_title,
				'FirstName' => @$order->cart->billing_address->address_firstname,
				'LastName' => @$order->cart->billing_address->address_lastname,
				'CompanyName' => '',
				'Street1' => @$order->cart->billing_address->address_street,
				'Street2' => @$order->cart->billing_address->address_street2,
				'City' => @$order->cart->billing_address->address_city,
				'State' => @$order->cart->billing_address->address_state->zone_code_3,
				'PostalCode' => @$order->cart->billing_address->address_post_code,
				'Country' => strtolower(@$order->cart->billing_address->address_country->zone_code_2),
				'Phone' => @$order->cart->billing_address->address_telephone,
				'Email' => $this->user->user_email,
			),
			'ShippingAddress' => array(
				'ShippingMethod' => eWayRapidBridge::getShipping( eWayRapidBridge::SHIPPING_UNKNOWN ),
				'FirstName' => @$order->cart->shipping_address->address_firstname,
				'LastName' => @$order->cart->shipping_address->address_lastname,
				'Street1' => @$order->cart->shipping_address->address_street,
				'Street2' => @$order->cart->shipping_address->address_street2,
				'City' => @$order->cart->shipping_address->address_city,
				'State' => @$order->cart->shipping_address->address_state->zone_code_3,
				'PostalCode' => @$order->cart->shipping_address->address_post_code,
				'Country' => strtolower(@$order->cart->shipping_address->address_country->zone_code_2),
				'Phone' => @$order->cart->shipping_address->address_telephone,
			),
			'CustomerReadOnly' => !empty($this->payment_params->customer_read_only),
		//	'Items' => array(),
		//	'LogoUrl' => 'https://mysite.com/images/logo4eway.jpg',
		);

		if(empty($this->payment_params->send_customer_details))
			unset($transaction['Customer']);
		if(empty($this->payment_params->send_shipping_details))
			unset($transaction['ShippingAddress']);

		if(!empty($order->order_invoice_id) && !empty($order->order_invoice_number))
			$transaction['Payment']['InvoiceNumber'] = $order->order_invoice_number;

		if(!empty($this->payment_params->invoice_description)) {
			$text = JText::_($this->payment_params->invoice_description);
			$transaction['Payment']['InvoiceDescription'] = substr($text, 0, 64);
		}

		//
		//
		switch($this->payment_params->payment_mode) {
			/**
			 *
			 */
			case 'direct':
				$api = eWayRapidBridge::API_DIRECT;
				// Mode not supported yet
				break;
			/**
			 *
			 */
			case 'redirect':
				$api = eWayRapidBridge::API_TRANSPARENT_REDIRECT;
				// Mode not supported yet
				break;
			/**
			 *
			 */
			case 'web':
			case 'iframe':
			default:
				$api = eWayRapidBridge::API_RESPONSIVE_SHARED;
				$transaction['CancelUrl'] = $notify_cancel_url;
				break;
		}

		//
		//
		$api_mode = eWayRapidBridge::getAPI( $api );
		$response = $client->createTransaction($api_mode, $transaction);

		// Debug
		if(!empty($this->payment_params->debug)) {
			$this->writeToLog('eWay transaction:'."\r\n".print_r($transaction, true)."\r\n\r\n".'eWay response:'."\r\n".print_r($response, true));
		}

		return $response;
	}

	/**
	 *
	 */
	public function onPaymentConfiguration(&$element) {
		parent::onPaymentConfiguration($element);

		if(version_compare(PHP_VERSION, '5.4', '<')) {
			$app = JFactory::getApplication();
			$app->enqueueMessage('To work correctly, eWay Rapid API requires PHP 5.4 or higher', 'error');
		}
	}

	/**
	 *
	 */
	public function getPaymentDefaultValues(&$element) {
		$element->payment_name = 'eWay Rapid';
		$element->payment_description = '';
		$element->payment_images = 'MasterCard,VISA,Credit_card';

		$element->payment_params->api_key = '';
		$element->payment_params->api_password = '';
		$element->payment_params->currency = 'AUD';
		$element->payment_params->invalid_status = 'cancelled';
		$element->payment_params->pending_status = 'created';
		$element->payment_params->verified_status = 'confirmed';
	}

	/**
	 *
	 */
	public function onPaymentConfigurationSave(&$element) {
		$ret = parent::onPaymentConfigurationSave($element);

		if(empty($element->payment_params->currency))
			$element->payment_params->currency = 'AUD';

		jimport('joomla.filesystem.file');
		$lang = JFactory::getLanguage();
		$locale = strtolower(substr($lang->get('tag'), 0, 2));

		$opts = array(
			'option' => 'com_hikashop',
			'tmpl' => 'component',
			'ctrl' => 'checkout',
			'task' => 'notify',
			'notif_payment' => $this->name,
			'format' => 'html',
			'local' => $locale,
			'notif_id' => $element->payment_id,
		);
		$content = '<?php' . "\r\n";
		foreach($opts as $k => $v) {
			$v = str_replace(array('\'','\\'), '', $v);
			$content .= '$_GET[\''.$k.'\']=\''.$v.'\';'."\r\n".
						'$_REQUEST[\''.$k.'\']=\''.$v.'\';'."\r\n";
		}
		$content .= 'include(\'index.php\');'."\r\n";
		JFile::write(JPATH_ROOT.DS.$this->name.'_'.$element->payment_id.'.php', $content);

		return $ret;
	}
}
