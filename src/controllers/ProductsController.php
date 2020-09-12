<?php

namespace QD\commerce\emaerket\controllers;

use Craft;
use craft\commerce\controllers\BaseController;
use craft\commerce\elements\Product;
use QD\commerce\emaerket\helpers\Verification;

class ProductsController extends BaseController
{

	/**
	 * @inheritdoc
	 */
	public $allowAnonymous = [
		'products' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE,
	];

	public function actionProducts()
	{

		//Get request query params
		$params = Craft::$app->getRequest()->getQueryParams();
		Verification::verifyExchangeToken($params);

		//To minimize load on the system, orders gets fetched paginated
		$offset = 15 * ($params['page'] - 1);

		//Query orders
		$productsQuery = Product::find()
			->dateUpdated('>= ' . date('Y-m-d', $params['since']))
			->offset($offset)
			->limit(15)
			->all();

		$products = [];
		foreach ($productsQuery as $key => $product) {

			//Get all variants
			$variants = $product->getVariants();

			foreach ($variants as $variant) {
				$products[] = [
					"sku" => $variant->sku,
					"name" => $variant->title,
					"url" => $product->url,
					"price" => $variant->price,
					"currency" => $variant->defaultCurrency,
					"sku" => $variant->sku,
					"stock" => $this->getStock($variant)
				];
			}
		}

		$productsArray = [
			'products' => $products
		];

		//Return order array
		return \json_encode($productsArray);
	}

	public function getStock($variant)
	{
		if ($variant->hasUnlimitedStock) {
			return 999;
		}

		return $variant->stock;
	}
}
