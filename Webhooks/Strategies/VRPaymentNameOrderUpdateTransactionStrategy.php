<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\Webhooks\Strategies;

use JTL\Checkout\Bestellung;
use JTL\Checkout\Zahlungsart;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\Plugin;
use JTL\Shop;
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
        $transactionId = $transaction->getId();
        
        $orderNr = $transaction->getMetaData()['order_nr'];
        if ($orderNr === null) {
            $localTransaction = $this->transactionService->getLocalVRPaymentTransactionById((string)$transactionId);
            $orderId = (int)$localTransaction->order_id;
        } else {
            $orderData = $this->transactionService->getOrderIfExists($orderNr);
            if ($orderData === null) {
                return;
            }
            $orderId = (int)$orderData->kBestellung;
        }

        $transactionState = $transaction->getState();
    
        switch ($transactionState) {
            case TransactionState::FULFILL:
                $order = new Bestellung($orderId);
                $this->transactionService->addIncommingPayment((string)$transactionId, $order, $transaction);
                break;

            case TransactionState::PROCESSING:
                $this->transactionService->updateTransactionStatus($transactionId, $transactionState);
                break;

            case TransactionState::AUTHORIZED:
                $this->transactionService->updateTransactionStatus($transactionId, $transactionState);
                break;

            case TransactionState::DECLINE:
            case TransactionState::VOIDED:
            case TransactionState::FAILED:
                if ($orderId > 0) {
                    $order = new Bestellung($orderId);
                    $paymentMethodEntity = new Zahlungsart((int)$order->kZahlungsart);
                    $moduleId = $paymentMethodEntity->cModulId ?? '';
                    $paymentMethod = new Method($moduleId);
                    $paymentMethod->cancelOrder($orderId);
                }
                $this->transactionService->updateTransactionStatus($transactionId, $transactionState);
                print 'Order ' . $orderId . ' status was updated to cancelled';
                break;
        }
    }
}
