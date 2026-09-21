<?php declare(strict_types=1);

use JTL\Shop;
use JTL\Alert\Alert;
use Plugin\jtl_vrpayment\Services\VRPaymentTransactionService;
use Plugin\jtl_vrpayment\VRPaymentApiClient;
use Plugin\jtl_vrpayment\VRPaymentHelper;

/** @global \JTL\Smarty\JTLSmarty $smarty */
/** @global JTL\Plugin\PluginInterface $plugin */

$transactionId = (int)($_GET['tID'] ?? $_SESSION[VRPaymentHelper::SESSION_TRANSACTION_ID] ?? 0);
VRPaymentHelper::log("failed_payment: User landed on failure page. TransactionId: " . ($transactionId ?: 'NONE'));
$translations = VRPaymentHelper::getTranslations($plugin->getLocalization(), [
    'jtl_vrpayment_payment_not_available_by_country_or_currency',
], false);
$errorMessage = (string)($translations['jtl_vrpayment_payment_not_available_by_country_or_currency'] ?? '');

if ($transactionId > 0) {
    $transactionService = null;
    $transaction = null;
    $orderId = 0;

    try {
        $apiClient = new VRPaymentApiClient($plugin->getId());
        $client = $apiClient->getApiClient();
        if ($client !== null) {
            $transactionService = new VRPaymentTransactionService($client, $plugin);
            $transaction = $transactionService->getTransactionFromPortal($transactionId);
        }
    } catch (\Throwable $e) {
        VRPaymentHelper::log(
            'failed_payment: API read failed for transaction ' . $transactionId
            . ': ' . get_class($e) . ' (' . $e->getCode() . '): ' . $e->getMessage()
        );
    }

    unset($_SESSION[VRPaymentHelper::SESSION_TRANSACTION_ID]);

    if ($transaction !== null) {
        $portalMessage = (string)($transaction->getUserFailureMessage() ?? '');
        if ($portalMessage !== '') {
            $errorMessage = $portalMessage;
        }
        $orderId = (int)($transaction->getMetaData()['orderId'] ?? 0);
    }

    if ($transactionService !== null && $orderId === 0) {
        try {
            $localTransaction = $transactionService->getLocalVRPaymentTransactionById((string)$transactionId);
            $orderId = (int)($localTransaction->order_id ?? 0);
        } catch (\Throwable $e) {
            VRPaymentHelper::log('failed_payment: local transaction lookup failed: ' . $e->getMessage());
        }
    }

    if ($errorMessage !== '') {
        Shop::Container()->getAlertService()->addAlert(
            Alert::TYPE_ERROR,
            $errorMessage,
            md5($errorMessage),
            ['saveInSession' => true]
        );
    }

    if ($orderId > 0 && $transactionService !== null) {
        $alreadyRolledBack = (int)($_SESSION['vrpn_rollback_done_order_id'] ?? 0) === $orderId;
        unset($_SESSION['vrpn_rollback_done_order_id']);

        if ($alreadyRolledBack) {
            VRPaymentHelper::log("failed_payment: Order $orderId was already rolled back by confirmTransaction. Skipping cleanup.");
        } else {
            try {
                $orderCancelled = $transactionService->cancelOrderOnce($orderId);
                if ($orderCancelled) {
                    $transactionService->restoreStock($orderId);
                }
            } catch (\Throwable $e) {
                VRPaymentHelper::log('failed_payment: local order cleanup failed for order ' . $orderId . ': ' . $e->getMessage());
            }
        }
    }
}

unset(
    $_SESSION['arrayOfPossibleMethods'],
    $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_ID],
    $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_NAME],
    $_SESSION['vrpaymentValidatedStateHash'],
    $_SESSION['vrpaymentValidatedTransactionId'],
    $_SESSION['vrpaymentValidatedMethod']
);

$linkHelper = Shop::Container()->getLinkService();
\header('Location: ' . $linkHelper->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1');
exit;
