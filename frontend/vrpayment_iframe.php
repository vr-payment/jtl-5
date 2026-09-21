<?php declare(strict_types=1);

use JTL\Shop;
use Plugin\jtl_vrpayment\VRPaymentHelper;

/** @global \JTL\Smarty\JTLSmarty $smarty */
/** @global JTL\Plugin\PluginInterface $plugin */

$translations = VRPaymentHelper::getTranslations($plugin->getLocalization(), [
    'jtl_vrpayment_pay',
    'jtl_vrpayment_cancel',
], false);

$isTwint = false;
$paymentMethod = $_SESSION['Zahlungsart'] ?? null;
if ($paymentMethod === null) {
    $linkHelper = Shop::Container()->getLinkService();
    \header('Location: ' . $linkHelper->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1');
    exit;
}

if (strpos(strtolower($paymentMethod->cName), "twint") !== false || strpos(strtolower($paymentMethod->cTSCode), "twint") !== false) {
    $isTwint = true;
}

$linkHelper = Shop::Container()->getLinkService();
$smarty
    ->assign('translations', $translations)
    ->assign('integration', 'iframe')
    ->assign('paymentName', $_SESSION['Zahlungsart']->angezeigterName[VRPaymentHelper::getLanguageIso(false)])
    ->assign('paymentId', $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_ID] ?? null)
    ->assign('iframeJsUrl', $_SESSION[VRPaymentHelper::SESSION_JAVASCRIPT_URL] ?? '')
    ->assign('appJsUrl', $_SESSION[VRPaymentHelper::SESSION_APP_JS_URL] ?? '')
    ->assign('isTwint', $isTwint)
    ->assign('spinner', $plugin->getPaths()->getBaseURL() . 'frontend/assets/spinner.gif')
    ->assign('cancelUrl', $linkHelper->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1')
    ->assign('mainCssUrl', $plugin->getPaths()->getBaseURL() . 'frontend/css/vrpayment-main.css');
