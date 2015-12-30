<?php
/**
 *
 */
class eWayRapidBridge
{
	const MODE_SANDBOX = 'sandbox';
	const MODE_PRODUCTION = 'production';

	const API_DIRECT = 'Direct';
	const API_RESPONSIVE_SHARED = 'ResponsiveShared';
	const API_TRANSPARENT_REDIRECT = 'TransparentRedirect';
	const API_WALLET = 'Wallet';
	const API_AUTHORISATION = 'Authorisation';

	const TRANSACTION_PURCHASE = 'Purchase';
	const TRANSACTION_RECURRING = 'Recurring';
	const TRANSACTION_MOTO = 'MOTO';

	const SHIPPING_UNKNOWN = 'Unknown';
	const SHIPPING_LOW_COST = 'LowCost';
	const SHIPPING_DESIGNATED_BY_CUSTOMER = 'DesignatedByCustomer';
	const SHIPPING_INTERNATIONAL = 'International';
	const SHIPPING_MILITARY = 'Military';
	const SHIPPING_NEXT_DAY = 'NextDay';
	const SHIPPING_STORE_PICKUP = 'StorePickup';
	const SHIPPING_TWO_DAY_SERVICE = 'TwoDayService';
	const SHIPPING_THREE_DAY_SERVICE = 'ThreeDayService';
	const SHIPPING_OTHER = 'Other';

	/**
	 *
	 */
	public static function init() {
		include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'ewayrapid_lib' . DIRECTORY_SEPARATOR . 'init.php';
		include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'ewayrapid_lib' . DIRECTORY_SEPARATOR . 'Rapid.php';
	}

	/**
	 *
	 */
	public static function createClient($key, $pwd, $endPoint) {
		if($endPoint == self::MODE_PRODUCTION)
			$mode = \Eway\Rapid\Client::MODE_PRODUCTION;
		else
			$mode = \Eway\Rapid\Client::MODE_SANDBOX;

		return $client = \Eway\Rapid::createClient($key, $pwd, $mode);
	}

	/**
	 *
	 */
	public static function getAPI($name) {
		switch($name) {
			case self::API_DIRECT:
				return \Eway\Rapid\Enum\ApiMethod::DIRECT;
			case self::API_RESPONSIVE_SHARED:
				return \Eway\Rapid\Enum\ApiMethod::RESPONSIVE_SHARED;
			case self::API_TRANSPARENT_REDIRECT:
				return \Eway\Rapid\Enum\ApiMethod::TRANSPARENT_REDIRECT;
			case self::API_WALLET:
				return \Eway\Rapid\Enum\ApiMethod::WALLET;
			case self::API_AUTHORISATION:
				return \Eway\Rapid\Enum\ApiMethod::AUTHORISATION;
		}
		return null;
	}

	/**
	 *
	 */
	public static function getTransaction($name) {
		switch($name) {
			case self::TRANSACTION_PURCHASE:
				return \Eway\Rapid\Enum\TransactionType::PURCHASE;
			case self::TRANSACTION_RECURRING:
				return \Eway\Rapid\Enum\TransactionType::RECURRING;
			case self::TRANSACTION_MOTO:
				return \Eway\Rapid\Enum\TransactionType::MOTO;
		}
		return null;
	}

	/**
	 *
	 */
	public static function getShipping($name) {
		switch($name) {
			case self::SHIPPING_UNKNOWN:
				return \Eway\Rapid\Enum\ShippingMethod::UNKNOWN;
			case self::SHIPPING_LOW_COST:
				return \Eway\Rapid\Enum\ShippingMethod::LOW_COST;
			case self::SHIPPING_DESIGNATED_BY_CUSTOMER:
				return \Eway\Rapid\Enum\ShippingMethod::DESIGNATED_BY_CUSTOMER;
			case self::SHIPPING_INTERNATIONAL:
				return \Eway\Rapid\Enum\ShippingMethod::INTERNATIONAL;
			case self::SHIPPING_MILITARY:
				return \Eway\Rapid\Enum\ShippingMethod::MILITARY;
			case self::SHIPPING_NEXT_DAY:
				return \Eway\Rapid\Enum\ShippingMethod::NEXT_DAY;
			case self::SHIPPING_STORE_PICKUP:
				return \Eway\Rapid\Enum\ShippingMethod::STORE_PICKUP;
			case self::SHIPPING_TWO_DAY_SERVICE:
				return \Eway\Rapid\Enum\ShippingMethod::TWO_DAY_SERVICE;
			case self::SHIPPING_THREE_DAY_SERVICE:
				return \Eway\Rapid\Enum\ShippingMethod::THREE_DAY_SERVICE;
			case self::SHIPPING_OTHER:
				return \Eway\Rapid\Enum\ShippingMethod::OTHER;
		}
		return null;
	}

	/**
	 *
	 */
	public static function getErrorMessage($err) {
		return \Eway\Rapid::getMessage($err);
	}
}