<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\Webhooks\Strategies;

use JTL\Shop;
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

        $transaction = $transactionInvoice->getCompletion()
            ->getLineItemVersion()
            ->getTransaction();
        
        
        $orderNr = $transaction->getMetaData()['order_nr'];
        $transactionId = $transaction->getId();
        
        // Fallback for older plugin versions
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
                $this->transactionService->addIncommingPayment((string)$transactionId, $order, $transaction);
                print 'Order ' . $orderId . ' status was updated to paid. Triggered by Transaction Invoice webhook.';
                break;
        }
    }
}
