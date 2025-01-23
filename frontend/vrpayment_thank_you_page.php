<?php declare(strict_types=1);

use JTL\Checkout\Bestellung;
use JTL\Shop;
use Plugin\jtl_vrpayment\Services\VRPaymentTransactionService;
use Plugin\jtl_vrpayment\VRPaymentApiClient;

/** @global \JTL\Smarty\JTLSmarty $smarty */
/** @global JTL\Plugin\PluginInterface $plugin */

$transactionId = (int)$_GET['tID'] ?? null;

if ($transactionId) {
    $apiClient = new VRPaymentApiClient($plugin->getId());
    $transactionService = new VRPaymentTransactionService($apiClient->getApiClient(), $plugin);
    $localTransaction = $transactionService->getLocalVRPaymentTransactionById((string)$transactionId);
    $orderId = (int) $localTransaction->order_id;
    $transaction = $transactionService->getTransactionFromPortal($transactionId);
    $createAfterPayment = (int)$transaction->getMetaData()['orderAfterPayment'] ?? 1;
    if ($createAfterPayment) {
        $orderNr = $transaction->getMetaData()['order_nr'];
        $data = $transactionService->getOrderIfExists($orderNr);
        if ($data === null) {
            $orderId = $transactionService->createOrderAfterPayment($transactionId);
        }
    }
}

$_SESSION['transactionId'] = null;
$_SESSION['Warenkorb'] = null;
$_SESSION['transactionId'] = null;
$_SESSION['arrayOfPossibleMethods'] = null;

$linkHelper = Shop::Container()->getLinkService();
if ($orderId > 0) {
    $bestellid = $this->db->select('tbestellid', 'kBestellung', $orderId);
}
$controlId = $bestellid->cId ?? '';
$url = $linkHelper->getStaticRoute('bestellabschluss.php') . '?i=' . $controlId;

\header('Location: ' . $url);
exit;
