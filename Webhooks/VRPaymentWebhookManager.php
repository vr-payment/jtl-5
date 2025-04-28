<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\Webhooks;

use JTL\Plugin\Plugin;
use JTL\Shop;
use Plugin\jtl_vrpayment\Services\VRPaymentOrderService;
use Plugin\jtl_vrpayment\Services\VRPaymentPaymentService;
use Plugin\jtl_vrpayment\Services\VRPaymentRefundService;
use Plugin\jtl_vrpayment\Services\VRPaymentTransactionService;
use Plugin\jtl_vrpayment\Webhooks\Strategies\VRPaymentNameOrderUpdateRefundStrategy;
use Plugin\jtl_vrpayment\Webhooks\Strategies\VRPaymentNameOrderUpdateTransactionInvoiceStrategy;
use Plugin\jtl_vrpayment\Webhooks\Strategies\VRPaymentNameOrderUpdateTransactionStrategy;
use Plugin\jtl_vrpayment\VRPaymentApiClient;
use Plugin\jtl_vrpayment\VRPaymentHelper;
use VRPayment\Sdk\ApiClient;
use VRPayment\Sdk\Model\{TransactionInvoiceState, TransactionState};

/**
 * Class VRPaymentWebhookManager
 * @package Plugin\jtl_vrpayment
 */
class VRPaymentWebhookManager
{
    /**
     * @var array $data
     */
    protected $data;

    /**
     * @var ApiClient $apiClient
     */
    protected ApiClient $apiClient;

    /**
     * @var Plugin $plugin
     */
    protected $plugin;

    /**
     * @var VRPaymentTransactionService $transactionService
     */
    protected $transactionService;

    /**
     * @var VRPaymentRefundService $refundService
     */
    protected $refundService;

    /**
     * @var VRPaymentOrderService $orderService
     */
    protected $orderService;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->data = json_decode(file_get_contents('php://input'), true);
        $this->apiClient = (new VRPaymentApiClient($plugin->getId()))->getApiClient();
        $this->transactionService = new VRPaymentTransactionService($this->apiClient, $this->plugin);
        $this->refundService = new VRPaymentRefundService($this->apiClient, $this->plugin);
    }

    public function listenForWebhooks(): void
    {
        $listenerEntityTechnicalName = $this->data['listenerEntityTechnicalName'] ?? null;
        if (!$listenerEntityTechnicalName) {
            return;
        }

        $orderUpdater = new VRPaymentOrderUpdater(new VRPaymentNameOrderUpdateTransactionStrategy($this->transactionService, $this->plugin));
        $entityId = (string)$this->data['entityId'];

        $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? null;
        if (!empty($signature)) {
            try {
                $this->apiClient->getWebhookEncryptionService()->isContentValid($signature, file_get_contents('php://input'));
            } catch (\Exception $e) {
                header('Content-Type: application/json', true, 400);
                echo json_encode([
                    'error' => 'Webhook validation failed: ' . $e->getMessage(),
                    'entityId' => $entityId ?? 'unknown'
                ]);
                exit;
            }
        }

        switch ($listenerEntityTechnicalName) {
            case VRPaymentHelper::TRANSACTION:
                $orderUpdater->updateOrderStatus($entityId);
                $transactionStateFromWebhook = $this?->data['state'] ?? null;
                if ($transactionStateFromWebhook === TransactionState::AUTHORIZED) {
                    $transaction = $this->transactionService->getTransactionFromPortal($entityId);
                    $orderId = (int)$transaction->getMetaData()['orderId'];
                    $this->transactionService->sendEmail($orderId, 'authorization');
                }
                break;

            case VRPaymentHelper::TRANSACTION_INVOICE:
                $orderUpdater->setStrategy(new VRPaymentNameOrderUpdateTransactionInvoiceStrategy($this->transactionService));
                $orderUpdater->updateOrderStatus($entityId);
                break;

            case VRPaymentHelper::REFUND:
                $orderUpdater->setStrategy(new VRPaymentNameOrderUpdateRefundStrategy($this->refundService, $this->transactionService));
                $orderUpdater->updateOrderStatus($entityId);
                break;

            case VRPaymentHelper::PAYMENT_METHOD_CONFIGURATION:
                $paymentService = new VRPaymentPaymentService($this->apiClient, $this->plugin->getId());
                $paymentService->syncPaymentMethods();
                break;
        }
    }
}

