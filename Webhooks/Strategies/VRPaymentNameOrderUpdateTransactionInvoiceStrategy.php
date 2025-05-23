<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\Webhooks\Strategies;

use JTL\Shop;
use JTL\Checkout\Bestellung;
use Plugin\jtl_vrpayment\Services\VRPaymentOrderService;
use Plugin\jtl_vrpayment\Services\VRPaymentTransactionService;
use Plugin\jtl_vrpayment\Webhooks\Strategies\Interfaces\VRPaymentOrderUpdateStrategyInterface;
use VRPayment\Sdk\Model\TransactionInvoiceState;
use VRPayment\Sdk\Model\TransactionState;

class VRPaymentNameOrderUpdateTransactionInvoiceStrategy implements VRPaymentOrderUpdateStrategyInterface
{
    /**
     * @var VRPaymentTransactionService $transactionService
     */
    public $transactionService;

    /**
     * @var VRPaymentOrderService $orderService
     */
    private $orderService;

    public function __construct(VRPaymentTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
        $this->orderService = new VRPaymentOrderService();
    }

    /**
     * @param string $transactionId
     * @return void
     */
    public function updateOrderStatus(string $entityId): void
    {
        $transactionInvoice = $this->transactionService->getTransactionInvoiceFromPortal($entityId);
        if ($transactionInvoice === null) {
            print 'Transaction Invoice ' . $entityId . ' not found';
            exit;
        }

        $transaction = $transactionInvoice->getCompletion()
            ->getLineItemVersion()
            ->getTransaction();

        if ($transaction === null) {
            print 'Transaction for Transaction Invoice  ' . $entityId . ' not found';
            exit;
        }

        $transactionId = $transaction->getId();
        $orderId = (int)$transaction->getMetaData()['orderId'];
        if ($orderId === 0) {
            print 'Order not found for transaction ' . $entityId;
            exit;
        }

        switch ($transactionInvoice->getState()) {
            case TransactionInvoiceState::DERECOGNIZED:
                $this->orderService->updateOrderStatus($orderId, \BESTELLUNG_STATUS_IN_BEARBEITUNG, \BESTELLUNG_STATUS_STORNO);
                $this->transactionService->updateTransactionStatus($transactionId, TransactionState::DECLINE);
                print 'Order ' . $orderId . ' status was updated to cancelled. Triggered by Transaction Invoice webhook.';
                break;

            //case TransactionInvoiceState::NOT_APPLICABLE:
            case TransactionInvoiceState::PAID:
                if (!$this->orderService->updateOrderStatus($orderId, \BESTELLUNG_STATUS_OFFEN, \BESTELLUNG_STATUS_BEZAHLT)) {
                    $this->orderService->updateOrderStatus($orderId, \BESTELLUNG_STATUS_IN_BEARBEITUNG, \BESTELLUNG_STATUS_BEZAHLT);
                }

                $order = new Bestellung($orderId);
                $this->transactionService->addIncomingPayment((string)$transactionId, $order, $transaction);
                $this->transactionService->handleNextOrderReferenceNumber($transaction->getMetaData()['order_no']);
                print 'Order ' . $orderId . ' status was updated to paid. Triggered by Transaction Invoice webhook.';
                break;
        }
    }
}
