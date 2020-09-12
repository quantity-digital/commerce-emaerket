<?php

namespace QD\commerce\emaerket;

use Craft;
use craft\commerce\Plugin as CommercePlugin;
use craft\events\PluginEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Plugins;
use craft\web\UrlManager;
use QD\commerce\emaerket\models\Settings;
use yii\base\Event;

class Emaerket extends \craft\base\Plugin
{
	// Static Properties
	// =========================================================================

	public static $plugin;

	/**
	 * @var bool
	 */
	public static $commerceInstalled = false;

	// Public Properties
	// =========================================================================

	/**
	 * @inheritDoc
	 */
	public $schemaVersion = '1.0';
	public $hasCpSettings = true;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		self::$plugin = $this;

		self::$commerceInstalled = class_exists(CommercePlugin::class);

		// Install event listeners
		$this->installEventListeners();

		Event::on(
			Plugins::class,
			Plugins::EVENT_AFTER_INSTALL_PLUGIN,
			function (PluginEvent $event) {
				if ($event->plugin === $this) {
				}
			}
		);
	}

	protected function installEventListeners()
	{

		$this->installGlobalEventListeners();
	}

	public function installGlobalEventListeners()
	{
		// Handler: Plugins::EVENT_AFTER_LOAD_PLUGINS
		Event::on(
			Plugins::class,
			Plugins::EVENT_AFTER_LOAD_PLUGINS,
			function () {
				// Install these only after all other plugins have loaded
				$request = Craft::$app->getRequest();

				if ($request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {
					$this->installSiteEventListeners();
				}

				if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {
					$this->installCpEventListeners();
				}
			}
		);

		// Redirect after plugin install
		Event::on(Plugins::class, Plugins::EVENT_AFTER_INSTALL_PLUGIN, function (PluginEvent $event) {
			if ($event->plugin === $this) {
				if (Craft::$app->getRequest()->isCpRequest) {
					Craft::$app->getResponse()->redirect(
						UrlHelper::cpUrl('settings/plugins/commerce-emaerket')
					)->send();
				}
			}
		});
	}

	protected function installSiteEventListeners()
	{
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_SITE_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				$event->rules = array_merge($event->rules, [
					'craftapi/v1/emaerket/orders' => 'commerce-emaerket/orders/orders',
					'craftapi/v1/emaerket/products' => 'commerce-emaerket/products/products',
				]);
			}
		);
	}

	protected function installCpEventListeners()
	{
	}

	protected function createSettingsModel()
	{
		return new Settings();
	}

	protected function settingsHtml()
	{
		foreach (CommercePlugin::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $status) {
			$statusOptions[] = [
				'value' => $status->id,
				'label' => $status->displayName
			];
		}

		return \Craft::$app->getView()->renderTemplate(
			'commerce-emaerket/settings',
			['settings' => $this->getSettings(), 'statusOptions' => $statusOptions]
		);
	}
}
