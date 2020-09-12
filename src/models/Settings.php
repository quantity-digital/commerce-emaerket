<?php

namespace QD\commerce\emaerket\models;

use craft\base\Model;

class Settings extends Model
{
	public $completedStatus = '';
	public $cancelledStatus = '';
	public $refundedStatus = '';

	public function rules()
	{
		return [
			[['refundedStatus', 'cancelledStatus','completedStatus'], 'required'],
			// ...
		];
	}
}
