<?php

namespace QD\commerce\emaerket\helpers;

use craft\feeds\GuzzleClient;
use yii\base\BaseObject;
use yii\web\ForbiddenHttpException;

class Verification extends BaseObject
{
	/**
	 * Checks if the exchange token is valid
	 *
	 * @param [string] $token
	 *
	 * @return void
	 */
	static public function verifyExchangeToken($params): bool
	{
		//If exchange token is not set, throw 403 error on the request
		if (!isset($params['exchange_token'])) {
			throw new ForbiddenHttpException('Request is not allowed.');
		}

		$client = new GuzzleClient();
		$request = $client->get('https://data.emaerket.dk/api/exchange_tokens?exchange_token=' . $params['exchange_token']);
		$response = json_decode($request->getBody());
		$key = $response->key;
		$iv = $response->iv;

		// if (!$key || !$iv) {
		// 	throw new ForbiddenHttpException('Request is not allowed.');
		// }

		return true;
	}
}
