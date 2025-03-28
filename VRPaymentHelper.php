<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment;

use JTL\Checkout\Nummern;
use JTL\Plugin\Data\Localization;
use JTL\Plugin\Helper;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Shop;
use VRPayment\Sdk\ApiClient;

/**
 * Class VRPaymentHelper
 * @package Plugin\jtl_vrpayment
 */
class VRPaymentHelper extends Helper
{
    const EN_ISO3 = 'eng';
    const DE_ISO3 = 'ger';
    const IT_ISO3 = 'ita';
    const FR_ISO3 = 'fre';

    const PAYMENT_METHOD_PREFIX = 'vrpayment_payment';
    const USER_ID = 'jtl_vrpayment_user_id';
    const SPACE_ID = 'jtl_vrpayment_space_id';
    const APPLICATION_KEY = 'jtl_vrpayment_application_key';
    const SPACE_VIEW_ID = 'jtl_vrpayment_space_view_id';
    const SEND_AUTHORIZATION_EMAIL = 'jtl_vrpayment_send_authorization_email';
    const SEND_FULFILL_EMAIL = 'jtl_vrpayment_send_fulfill_email';
    const SEND_CONFIRMATION_EMAIL = 'jtl_vrpayment_send_confirmation_email';
    const PREVENT_FROM_DUPLICATED_ORDERS = 'jtl_vrpayment_prevent_from_duplicated_orders';
    const INTEGRATION_TYPE = 'jtl_vrpayment_integration_type';
    const INTEGRATION_TYPE_PAYMENT_PAGE = 'payment_page';


    const PAYMENT_METHOD_CONFIGURATION = 'PaymentMethodConfiguration';
    const REFUND = 'Refund';
    const TRANSACTION = 'Transaction';
    const TRANSACTION_INVOICE = 'TransactionInvoice';

    const PLUGIN_CUSTOM_PAGES = [
        'thank-you-page' => [
            self::DE_ISO3 => 'vrpayment-danke-seite',
            self::EN_ISO3 => 'vrpayment-thank-you-page',
            self::IT_ISO3 => 'vrpayment-pagina-di-ringraziamento',
            self::FR_ISO3 => 'vrpayment-page-de-remerciement',
        ],
        'payment-page' => [
            self::DE_ISO3 => 'vrpayment-zahlungsseite',
            self::EN_ISO3 => 'vrpayment-payment-page',
            self::IT_ISO3 => 'vrpayment-pagina-di-pagamento',
            self::FR_ISO3 => 'vrpayment-page-de-paiement',
        ],
        'fail-page' => [
            self::DE_ISO3 => 'vrpayment-bezahlung-fehlgeschlagen',
            self::EN_ISO3 => 'vrpayment-failed-payment',
            self::IT_ISO3 => 'vrpayment-pagamento-fallito',
            self::FR_ISO3 => 'vrpayment-paiement-echoue',
        ],
    ];


    /**
     * @param string $text
     * @param string $divider
     * @return string
     */
    public static function slugify(string $text, string $divider = '_'): string
    {
        // replace non letter or digits by divider
        $text = preg_replace('~[^\pL\d]+~u', $divider, $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, $divider);

        // remove duplicate divider
        $text = preg_replace('~-+~', $divider, $text);

        // lowercase
        $text = strtolower($text);

        return $text;
    }

    /**
     * @return string
     */
    public static function getLanguageString(): string
    {
        switch ($_SESSION['currentLanguage']->iso) {
            case self::DE_ISO3:
                return 'de_DE';

            case self::FR_ISO3:
                return 'fr_FR';

            case self::IT_ISO3:
                return 'it_IT';

            default:
                return 'en_GB';
        }
    }

    /**
     * @param $isAdmin
     * @return string
     */
    public static function getLanguageIso($isAdmin = true): string
    {
        if (!$isAdmin) {
            return $_SESSION['cISOSprache'];
        }

        $gettext = Shop::Container()->getGetText();
        $langTag = $_SESSION['AdminAccount']->language ?? $gettext->getLanguage();

        switch (substr($langTag, 0, 2)) {
            case 'de':
                return self::DE_ISO3;

            case 'en':
                return self::EN_ISO3;

            case 'fr':
                return self::FR_ISO3;

            case 'it':
                return self::IT_ISO3;
        }
    }

    /**
     * @param Localization $localization
     * @param array $keys
     * @param $isAdmin
     * @return array
     */
    public static function getTranslations(Localization $localization, array $keys, $isAdmin = true): array
    {
        $translations = [];
        foreach ($keys as $key) {
            $translations[$key] = $localization->getTranslation($key, self::getLanguageIso($isAdmin));
        }

        return $translations;
    }

    /**
     * @param Localization $localization
     * @return array
     */
    public static function getPaymentStatusWithTransations(Localization $localization): array
    {
        $translations = VRPaymentHelper::getTranslations($localization, [
            'jtl_vrpayment_order_status_cancelled',
            'jtl_vrpayment_order_status_open',
            'jtl_vrpayment_order_status_in_processing',
            'jtl_vrpayment_order_status_paid',
            'jtl_vrpayment_order_status_shipped',
            'jtl_vrpayment_order_status_partially_shipped',
        ]);

        return [
            '-1' => $translations['jtl_vrpayment_order_status_cancelled'],
            '1' => $translations['jtl_vrpayment_order_status_open'],
            '2' => $translations['jtl_vrpayment_order_status_in_processing'],
            '3' => $translations['jtl_vrpayment_order_status_paid'],
            '4' => $translations['jtl_vrpayment_order_status_shipped'],
            '5' => $translations['jtl_vrpayment_order_status_partially_shipped'],
        ];
    }

    /**
     * @param int $pluginId
     * @return ApiClient|null
     */
    public static function getApiClient(int $pluginId): ?ApiClient
    {
        if (class_exists('VRPayment\Sdk\ApiClient')) {
            $apiClient = new VRPaymentApiClient($pluginId);
            return $apiClient->getApiClient();
        } else {

            if (isset($_POST['Setting'])) {
                $plugin = PluginHelper::getLoaderByPluginID($pluginId)->init($pluginId);
                $translations = VRPaymentHelper::getTranslations($plugin->getLocalization(), [
                    'jtl_vrpayment_need_to_install_sdk',
                ]);
                Shop::Container()->getAlertService()->addDanger(
                    $translations['jtl_vrpayment_need_to_install_sdk'],
                    'getApiClient'
                );
            }
            return null;
        }
    }

    /**
     * @param $update
     * @param $lastOrderNo
     * @return array
     *
     * This is edited JTL native function.
     */
    public static function createOrderNo($update = true, $lastOrderNo = 0): array
    {
        $conf      = Shop::getSettingSection(\CONF_KAUFABWICKLUNG);
        $number    = new Nummern(\JTL_GENNUMBER_ORDERNUMBER);
        $orderNo   = 1;
        $increment = (int)($conf['bestellabschluss_bestellnummer_anfangsnummer'] ?? 1);
        if ($number) {
            $orderNo = $number->getNummer() + $increment;
            $number->setNummer($number->getNummer() + 1);
            if ($update === true) {
                // This part is used when setting Prevent From Duplicated Order No === 'NO'. We increase order number only once (even multiple orders with same nr are completed
                // Example: setting is set to No, we accept duplicated order no. Two customers are making order with no Order 1. If both finishes the payment, next order will be Order 2, not Order 2 and Order 3
                if ($lastOrderNo === 0 || $orderNo - $lastOrderNo === 0) {
                    $number->update();
                }
            }
        }

        /*
        *   %Y = -aktuelles Jahr
        *   %m = -aktueller Monat
        *   %d = -aktueller Tag
        *   %W = -aktuelle KW
        */
        $prefix = \str_replace(
          ['%Y', '%m', '%d', '%W'],
          [\date('Y'), \date('m'), \date('d'), \date('W')],
          $conf['bestellabschluss_bestellnummer_praefix']
        );
        $suffix = \str_replace(
          ['%Y', '%m', '%d', '%W'],
          [\date('Y'), \date('m'), \date('d'), \date('W')],
          $conf['bestellabschluss_bestellnummer_suffix']
        );

        return [$prefix . $orderNo . $suffix, $orderNo];
    }

    /**
     * @param int $pluginId
     * @return string
     */
    public static function getIntegrationType(int $pluginId): string
    {
        $config = self::getConfigByID($pluginId);
        return $config[self::INTEGRATION_TYPE] ?? 'payment_page';
    }
}

