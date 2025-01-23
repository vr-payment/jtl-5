<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment;

use JTL\Shop;
use VRPayment\Sdk\ApiClient;
use JTL\Plugin\Helper as PluginHelper;

/**
 * Class VRPaymentApiClient
 * @package Plugin\jtl_vrpayment
 */
class VRPaymentApiClient
{
	/**
	 * @var ApiClient $apiClient
	 */
	protected $apiClient;
	
	
	const SHOP_SYSTEM = 'x-meta-shop-system';
	const SHOP_SYSTEM_VERSION = 'x-meta-shop-system-version';
	const SHOP_SYSTEM_AND_VERSION = 'x-meta-shop-system-and-version';
	
	public function __construct(int $pluginId)
	{
		if (!$this->getApiClient()) {
			$config = VRPaymentHelper::getConfigByID($pluginId);
			$userId = $config[VRPaymentHelper::USER_ID] ?? null;
			$applicationKey = $config[VRPaymentHelper::APPLICATION_KEY] ?? null;
			$plugin = PluginHelper::getLoaderByPluginID($pluginId)->init($pluginId);
			
			if (empty($userId) || empty($applicationKey)) {
				if (isset($_POST['Setting'])) {
					$translations = VRPaymentHelper::getTranslations($plugin->getLocalization(), [
					  'jtl_vrpayment_incorrect_user_id_or_application_key',
					]);
					Shop::Container()->getAlertService()->addDanger(
					  $translations['jtl_vrpayment_incorrect_user_id_or_application_key'],
					  'getApiClient'
					);
				}
				return null;
			}
			
			try {
				$apiClient = new ApiClient($userId, $applicationKey);
				$apiClientBasePath = getenv('VRPAYMENT_API_BASE_PATH') ? getenv('VRPAYMENT_API_BASE_PATH') : $apiClient->getBasePath();
				$apiClient->setBasePath($apiClientBasePath);
				foreach (self::getDefaultHeaderData() as $key => $value) {
					$apiClient->addDefaultHeader($key, $value);
				}
				$this->apiClient = $apiClient;
			} catch (\Exception $exception) {
				$translations = VRPaymentHelper::getTranslations($plugin->getLocalization(), [
				  'jtl_vrpayment_incorrect_user_id_or_application_key',
				]);
				Shop::Container()->getAlertService()->addDanger(
				  $translations['jtl_vrpayment_incorrect_user_id_or_application_key'],
				  'getApiClient'
				);
				return null;
			}
		}
	}
	
	/**
	 * @return array
	 */
	protected static function getDefaultHeaderData(): array
	{
		$shop_version = APPLICATION_VERSION;
		[$major_version, $minor_version, $_] = explode('.', $shop_version, 3);
		return [
		  self::SHOP_SYSTEM => 'jtl',
		  self::SHOP_SYSTEM_VERSION => $shop_version,
		  self::SHOP_SYSTEM_AND_VERSION => 'jtl-' . $major_version . '.' . $minor_version,
		];
	}
	
	/**
	 * @return ApiClient|null
	 */
	public function getApiClient(): ?ApiClient
	{
		return $this->apiClient;
	}
}

