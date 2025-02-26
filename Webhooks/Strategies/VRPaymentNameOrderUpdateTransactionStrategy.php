<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\Webhooks\Strategies;

use JTL\Checkout\Bestellung;
use JTL\Checkout\Zahlungsart;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\Plugin;
use JTL\Shop;
use Plugin\jtl_vrpayment\VRPaymentHelper;
use Plugin\jtl_vrpayment\Services\VRPaymentOrderService;
use Plugin\jtl_vrpayment\Services\VRPaymentTransactionService;
use Plugin\jtl_vrpayment\Webhooks\Strategies\Interfaces\VRPaymentOrderUpdateStrategyInterface;
use VRPayment\Sdk\Model\Transaction;
use VRPayment\Sdk\Model\TransactionState;

class VRPaymentNameOrderUpdateTransactionStrategy implements VRPaymentOrderUpdateStrategyInterface
{
    /**
     * @var Plugin $plugin
     */
    private $plugin;

    /**
     * @var VRPaymentTransactionService $transactionService
     */
    private $transactionService;

    /**
     * @var VRPaymentOrderService $orderService
     */
    private $orderService;

    public function __construct(VRPaymentTransactionService $transactionService, Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->transactionService = $transactionService;
        $this->orderService = new VRPaymentOrderService();
    }

    /**
     * @param string $transactionId
     * @return void
     */
    public function updateOrderStatus(string $entityId): void
    {
        $transaction = $this->transactionService->getTransactionFromPortal($entityId);
        if ($transaction === null) {
            print 'Transaction ' . $entityId . ' not found';
            exit;
        }

        $transactionId = $transaction->getId();
        $transactionState = $transaction->getState();

        $orderId = (int)$transaction->getMetaData()['orderId'];
        if ($orderId === 0) {
            print 'Order not found for transaction ' . $entityId;
            exit;
        }

        switch ($transactionState) {
            case TransactionState::FULFILL:
                $order = new Bestellung($orderId);
                if ($order && (int )$order->cStatus !== \BESTELLUNG_STATUS_BEZAHLT) {
                    $orderData = $order->fuelleBestellung();
                    $this->transactionService->addIncomingPayment((string)$transactionId, $orderData, $transaction);
                    $this->transactionService->updateWawiSyncFlag($orderId, $this->transactionService::LET_SYNC_TO_WAWI);
                    $this->transactionService->handleNextOrderReferenceNumber($transaction->getMetaData()['order_no']);
                }
                break;

            case TransactionState::AUTHORIZED:
                $order = new Bestellung($orderId);
                if ($order && (int )$order->cStatus === \BESTELLUNG_STATUS_OFFEN) {
                    $this->transactionService->updateWawiSyncFlag($orderId, $this->transactionService::LET_SYNC_TO_WAWI);
                    $this->transactionService->handleNextOrderReferenceNumber($transaction->getMetaData()['order_no']);
                }
                break;

            case TransactionState::FAILED:
                // Deleted the order from the shop, because transaction failed and order will never be finished.
                // New transaction will be created with new order.
                Shop::Container()->getDB()->delete('tbestellung', 'kBestellung', $orderId);
                break;

            case TransactionState::DECLINE:
            case TransactionState::VOIDED:
                $order = new Bestellung($orderId);
                $paymentMethodEntity = new Zahlungsart((int)$order->kZahlungsart);
                $moduleId = $paymentMethodEntity->cModulId ?? '';
                $paymentMethod = new Method($moduleId);
                $paymentMethod->cancelOrder($orderId);

                // If transaction was cancelled from portal - we delete leftovers from the shop
                if ($order && (int )$order->cStatus === \BESTELLUNG_STATUS_OFFEN) {
                    Shop::Container()->getDB()->delete('tbestellung', 'kBestellung', $orderId);
                }
                print 'Order ' . $orderId . ' status was updated to cancelled';
                break;
        }
        $this->transactionService->updateTransactionStatus($transactionId, $transactionState);
    }
}
