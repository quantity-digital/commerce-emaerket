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
			$billingAddress = ($order->billingAddress) ? $order->billingAddress : $shippingAddress;
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

			$status = $this->getEmaerkerStatus($order);

			$data = [
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
					"first_name" => $billingAddress ? $billingAddress->firstName : '',
					"last_name" => $billingAddress ? $billingAddress->lastName : '',
					"address_1" => $billingAddress ? $billingAddress->address1 : '',
					"address_2" => $billingAddress ? $billingAddress->address2 : '',
					"zip_code" => $billingAddress ? $billingAddress->zipCode : '',
					"city" => $billingAddress ? $billingAddress->city : '',
					"state" => $billingAddress ? $billingAddress->state : '',
					"country" => $billingAddress ? ($billingAddress->country ? $billingAddress->country['name'] : '') : '',
					"phone" => $billingAddress ? $billingAddress->phone : ''
				],
				"shipping_address" => [
					"first_name" => $shippingAddress ? $shippingAddress->firstName : '',
					"last_name" => $shippingAddress ? $shippingAddress->lastName : '',
					"address_1" => $shippingAddress ? $shippingAddress->address1 : '',
					"address_2" => $shippingAddress ? $shippingAddress->address2 : '',
					"zip_code" => $shippingAddress ? $shippingAddress->zipCode : '',
					"city" => $shippingAddress ? $shippingAddress->city : '',
					"state" => $shippingAddress ? $shippingAddress->state : '',
					"country" => $shippingAddress ? ($shippingAddress->country ? $shippingAddress->country['name'] : '') : '',
					"phone" => $shippingAddress ? $shippingAddress->phone : ''
				],
				"payment" => $this->getPayment($order),
				"shipping" => [
					"id" => $shippingMethod->handle,
					"name" => $shippingMethod->Name,
					"price" => $order->totalShippingCost
				],
				"order_lines" => $lineItems
			];

			//If order status is closed (meaning it has been shipped), add shipment
			if ($status === 'closed') {
				$data['shipping']['shipments'] = [
					'courier' => $shippingMethod->Name,
					'tracking' => '-',
					'date' => $order->histories[0]->dateCreated->getTimestamp()
				];
			}

			$orders[] = $data;
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
