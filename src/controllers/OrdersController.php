<?php

namespace QD\commerce\emaerket\controllers;

use Craft;
use craft\commerce\controllers\BaseController;
use craft\commerce\elements\Order;
use QD\commerce\emaerket\Emaerket;
use QD\commerce\emaerket\helpers\Verification;

class OrdersController extends BaseController
{

	/**
	 * @inheritdoc
	 */
	public $allowAnonymous = [
		'orders' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE,
	];

	/**
	 * Returns all orders from specified date in query as json
	 *
	 * @return json
	 */
	public function actionOrders()
	{

		//Get request query params
		$params = Craft::$app->getRequest()->getQueryParams();
		Verification::verifyExchangeToken($params);


		//To minimize load on the system, orders gets fetched paginated
		$offset = 15 * ($params['page'] - 1);

		//Query orders
		$ordersQuery = Order::find()
			->isCompleted()
			->dateUpdated('>= ' . date('Y-m-d', $params['since']))
			->offset($offset)
			->limit(15)
			->all();

		//Build orders array
		$orders = [];
		foreach ($ordersQuery as $key => $order) {
			$shippingAddress = $order->shippingAddress;
			$billingAddress = $order->billingAddress;
			$shippingMethod = $order->shippingMethod;

			//Create lineitems
			$lineItems = [];
			foreach ($order->lineItems as $key => $item) {
				$lineItems[] = [
					"sku" => $item->sku,
					"name" => $item->description,
					"quantity" => $item->qty,
					"price" => $item->subtotal
				];
			}

			$orders[] = [
				"order_number" => $order->reference,
				"remote_id" => $order->id,
				"date" => $order->dateCreated->getTimestamp(),
				"status" => $this->getEmaerkerStatus($order),
				"email" => $order->email,
				"price" => (int)$order->storedTotalPrice,
				"currency" => $order->currency,
				"customer_note" => '',
				"company_note" => '',
				"ip" => $order->lastIp,
				"billing_address" => [
					"first_name" => $billingAddress->firstName,
					"last_name" => $billingAddress->lastName,
					"address_1" => $billingAddress->address1,
					"address_2" => $billingAddress->address2,
					"zip_code" => $billingAddress->zipCode,
					"city" => $billingAddress->city,
					"state" => $billingAddress->state,
					"country" => $billingAddress->country['name'],
					"phone" => $billingAddress->phone
				],
				"shipping_address" => [
					"first_name" => $shippingAddress->firstName,
					"last_name" => $shippingAddress->lastName,
					"address_1" => $shippingAddress->address1,
					"address_2" => $shippingAddress->address2,
					"zip_code" => $shippingAddress->zipCode,
					"city" => $shippingAddress->city,
					"state" => $shippingAddress->state,
					"country" => $shippingAddress->country['name'],
					"phone" => $shippingAddress->phone
				],
				"payment" => $this->getPayment($order),
				"shipping" => [
					"id" => $shippingMethod->handle,
					"name" => $shippingMethod->Name,
					"price" => $order->totalShippingCost
				],
				"order_lines" => $lineItems
			];
		}

		$ordersArray = [
			'orders' => $orders
		];

		//Return order array
		return \json_encode($ordersArray);
	}

	/**
	 * Get the orderstatus that should be returned to Emærket
	 *
	 * @param Order $order
	 *
	 * @return string
	 */
	public function getEmaerkerStatus($order)
	{
		$settings = Emaerket::getInstance()->getSettings();
		$status = $order->orderStatus->id;

		if ($status === $settings->completedStatus) {
			return 'closed';
		}

		if ($status === $settings->cancelledStatus) {
			return 'cancelled';
		}

		if ($status === $settings->refundedStatus) {
			return 'refunded';
		}

		return 'open';
	}

	/**
	 * Get paymentdata for the order
	 *
	 * @param Order $order
	 *
	 * @return array
	 */
	public function getPayment($order)
	{
		$latestTransaction = $order->getLastTransaction();

		if (!$latestTransaction) {
			return [
				"id" => '',
				"name" => '',
				"price" => ''
			];
		}

		$gateway = $latestTransaction->getGateway();

		return [
			"id" => $gateway->handle,
			"name" => $gateway->name,
			"price" => $latestTransaction->amount - $order->storedTotalPrice
		];
	}
}
