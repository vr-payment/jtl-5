<?php declare(strict_types=1);

use JTL\Checkout\Bestellung;
use JTL\Shop;
use Plugin\jtl_vrpayment\Services\VRPaymentTransactionService;
use Plugin\jtl_vrpayment\VRPaymentApiClient;
use Plugin\jtl_vrpayment\VRPaymentHelper;
use VRPayment\Sdk\Model\TransactionState;

/** @global \JTL\Smarty\JTLSmarty $smarty */
/** @global JTL\Plugin\PluginInterface $plugin */

$transactionId = (int)($_GET['tID'] ?? 0);
$orderId = 0;
$transactionService = null;

if ($transactionId > 0) {
    try {
        $apiClient = new VRPaymentApiClient($plugin->getId());
        $client = $apiClient->getApiClient();
        if ($client !== null) {
            $transactionService = new VRPaymentTransactionService($client, $plugin);
            $transaction = $transactionService->getTransactionFromPortal($transactionId);

            Shop::Container()->getLogService()->notice('Transaction found. Starting to create order.');
            $createAfterPayment = (int)($transaction->getMetaData()['orderAfterPayment'] ?? 1);
            if ($createAfterPayment) {
                $orderId = (int)($transaction->getMetaData()['orderId'] ?? 0);
                if ($orderId > 0) {
                    $order = new Bestellung($orderId);
                    $orderId = (int)$order->kBestellung;
                }
            } else {
                Shop::Container()->getLogService()->notice(
                    'Order was not created. We created it previously and returning the ID.'
                );
                $localTransaction = $transactionService->getLocalVRPaymentTransactionById((string)$transactionId);
                $orderId = (int)($localTransaction->order_id ?? 0);
            }

            if ($orderId > 0) {
                $state = $transaction->getState();
                if ($state === TransactionState::FULFILL || $state === TransactionState::AUTHORIZED) {
                    VRPaymentHelper::log(
                        "thank_you_page: Transaction $transactionId is successful ($state). "
                        . "Resetting cAbgeholt to LET_SYNC_TO_WAWI ('N')."
                    );
                    $transactionService->updateWawiSyncFlag(
                        $orderId,
                        $transactionService::LET_SYNC_TO_WAWI
                    );
                }
            }
        }
    } catch (\Throwable $e) {
        // A temporary VR Payment API problem must not turn a successful browser
        // return into HTTP 500. The local transaction table still contains the
        // JTL order reference created before redirecting to the payment page.
        VRPaymentHelper::log(
            'thank_you_page: API processing failed for transaction ' . $transactionId
            . ': ' . get_class($e) . ' (' . $e->getCode() . '): ' . $e->getMessage()
        );
    }

    if ($orderId === 0 && $transactionService !== null) {
        try {
            $localTransaction = $transactionService->getLocalVRPaymentTransactionById((string)$transactionId);
            $orderId = (int)($localTransaction->order_id ?? 0);
        } catch (\Throwable $e) {
            VRPaymentHelper::log('thank_you_page: local transaction lookup failed: ' . $e->getMessage());
        }
    }
} else {
    Shop::Container()->getLogService()->notice('No transaction ID.');
}

unset(
    $_SESSION[VRPaymentHelper::SESSION_TRANSACTION_ID],
    $_SESSION['arrayOfPossibleMethods'],
    $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_ID],
    $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_NAME],
    $_SESSION['vrpaymentValidatedStateHash'],
    $_SESSION['vrpaymentValidatedTransactionId'],
    $_SESSION['vrpaymentValidatedMethod']
);
$_SESSION['Warenkorb'] = null;

$linkHelper = Shop::Container()->getLinkService();
$controlId = '';
if ($orderId > 0) {
    $bestellid = Shop::Container()->getDB()->select('tbestellid', 'kBestellung', $orderId);
    $controlId = $bestellid->cId ?? '';
}
$url = $linkHelper->getStaticRoute('bestellabschluss.php') . '?i=' . $controlId;
\header('Location: ' . $url);
exit;
