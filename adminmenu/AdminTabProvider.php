<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\adminmenu;

use JTL\Catalog\Currency;
use JTL\Catalog\Product\Preise;
use JTL\Checkout\Bestellung;
use JTL\Checkout\Zahlungsart;
use JTL\DB\DbInterface;
use JTL\DB\ReturnType;
use JTL\Helpers\PaymentMethod;
use JTL\Language\LanguageHelper;
use JTL\Pagination\Pagination;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\Plugin;
use JTL\Plugin\PluginInterface;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\jtl_vrpayment\Services\VRPaymentRefundService;
use Plugin\jtl_vrpayment\Services\VRPaymentTransactionService;
use Plugin\jtl_vrpayment\VRPaymentHelper;
use stdClass;
use VRPayment\Sdk\ApiClient;
use VRPayment\Sdk\Model\{TransactionInvoiceState, TransactionState};

class AdminTabProvider
{
    const ACTION_COMPLETE = 'complete';
    const ACTION_CANCEL = 'cancel';
    const ACTION_DOWNLOAD_INVOICE = 'download_invoice';
    const ACTION_DOWNLOAD_PACKAGING_SLIP = 'download_packaging_slip';
    const ACTION_REFUND = 'refund';
    const ACTION_ORDER_DETAILS = 'order_details';
    const ACTION_SEARCH_ORDERS = 'search_orders';

    const FILE_DOWNLOAD_ALLOWED_STATES = [
        'FULFILL',
        'PAID',
        'REFUNDED',
        'PARTIALY_REFUNDED'
    ];

    /**
     * @var PluginInterface
     */
    private $plugin;

    /**
     * @var DbInterface
     */
    private $db;

    /**
     * @var JTLSmarty
     */
    private $smarty;
    
    /**
     * @var ApiClient|null
     */
    private $apiClient;
    
    /**
     * @var VRPaymentTransactionService
     */
    private $transactionService;
    
    /**
     * @var VRPaymentRefundService
     */
    private $refundService;
    
    
    /**
     * @param PluginInterface $plugin
     * @param DbInterface $db
     * @param JTLSmarty $smarty
     */
    public function __construct(PluginInterface $plugin, DbInterface $db, JTLSmarty $smarty)
    {
        $this->plugin = $plugin;
        $this->db = $db;
        $this->smarty = $smarty;

        $this->apiClient = VRPaymentHelper::getApiClient($plugin->getId());
        if (empty($this->apiClient)) {
            return;
        }

        $this->transactionService = new VRPaymentTransactionService($this->apiClient, $this->plugin);
        $this->refundService = new VRPaymentRefundService($this->apiClient, $this->plugin);
    }

    /**
     *
     * @param int $menuID
     * @return string
     */
    public function createOrdersTab(int $menuID): string
    {
        $action = $_REQUEST['action'] ?? null;
        $orders = [];
        if ($action) {
            $this->handleAction($action);
            exit;
        }
        
        $searchQueryString = $_GET['q'] ?? '';
        $sqlConditions = [];
        $params = [];
        $orders = [];
        
        if ($searchQueryString) {
            $searchQuery = '%' . $searchQueryString . '%';
            $sqlConditions[] = 'ord.cBestellNr LIKE :searchQuery';
            $sqlConditions[] = 'tkunde.cVorname LIKE :searchQuery';
            $sqlConditions[] = 'ord.cZahlungsartName LIKE :searchQuery';
            $params[':searchQuery'] = $searchQuery;
        }
        
        $sqlConditionStr = $sqlConditions ? 'WHERE ' . implode(' OR ', $sqlConditions) : '';
        $ordersQuantity = $this->db->query('SELECT transaction_id FROM vrpayment_transactions', ReturnType::AFFECTED_ROWS);
        $pagination = (new Pagination('vrpayment-orders'))->setItemCount($ordersQuantity)->assemble();
        $query = '
            SELECT ord.kBestellung, ord.fGesamtsumme, plugin.transaction_id, plugin.state
            FROM tbestellung ord
            JOIN vrpayment_transactions plugin ON ord.kBestellung = plugin.order_id
            JOIN tkunde ON tkunde.kKunde = ord.kKunde
            ' . $sqlConditionStr . '
            ORDER BY ord.kBestellung DESC
        ';
        
        $orderArr = $this->db->executeQueryPrepared($query . ' LIMIT ' . $pagination->getLimitSQL(), $params, ReturnType::ARRAY_OF_OBJECTS);
        foreach ($orderArr as $order) {
            $orderId = (int)$order->kBestellung;
            $ordObj = new Bestellung($orderId);
            $ordObj->fuelleBestellung(true, 0, false);
            $orderDetails = [
              'orderDetails' => $ordObj,
              'vrpayment_transaction_id' => $order->transaction_id,
              'vrpayment_state' => $order->state,
              'total_amount' => (float)$order->fGesamtsumme
            ];
            $orders[$orderId] = $orderDetails;
        }

        $paymentStatus = VRPaymentHelper::getPaymentStatusWithTransations($this->plugin->getLocalization());
        $translations = VRPaymentHelper::getTranslations($this->plugin->getLocalization(), [
            'jtl_vrpayment_order_number',
            'jtl_vrpayment_customer',
            'jtl_vrpayment_payment_method',
            'jtl_vrpayment_order_status',
            'jtl_vrpayment_amount',
            'jtl_vrpayment_there_are_no_orders',
            'jtl_vrpayment_search',
        ]);
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        
        return $this->smarty->assign('orders', $orders)
            ->assign('currentUrl', $currentUrl)
            ->assign('searchQueryString', $searchQueryString)
            ->assign('adminUrl', $this->plugin->getPaths()->getadminURL())
            ->assign('translations', $translations)
            ->assign('pagination', $pagination)
            ->assign('pluginId', $this->plugin->getID())
            ->assign('postUrl', Shop::getURL() . '/' . \PFAD_ADMIN . 'plugin.php?kPlugin=' . $this->plugin->getID())
            ->assign('paymentStatus', $paymentStatus)
            ->assign('hash', 'plugin-tab-' . $menuID)
            ->assign('tplPath', $this->plugin->getPaths()->getAdminPath() . 'templates')
            ->fetch($this->plugin->getPaths()->getAdminPath() . 'templates/vrpayment_orders.tpl');
    }
    
    /**
     * @param string $action
     * @return void
     */
    private function handleAction(string $action): void
    {
        $transactionId = $_REQUEST['transactionId'] ?? null;
        switch ($action) {
            case self::ACTION_COMPLETE:
                if (!$transactionId) {
                    Shop::Container()->getLogService()->error('No transaction ID provided on action ' . $action);
                    exit;
                }
                $this->completeTransaction($transactionId);
                break;
            
            case self::ACTION_CANCEL:
                if (!$transactionId) {
                    Shop::Container()->getLogService()->error('No transaction ID provided on action ' . $action);
                    exit;
                }
                $this->cancelTransaction($transactionId);
                break;
            
            case self::ACTION_DOWNLOAD_INVOICE:
                if (!$transactionId) {
                    Shop::Container()->getLogService()->error('No transaction ID provided on action ' . $action);
                    exit;
                }
                $this->downloadInvoice($transactionId);
                break;
            
            case self::ACTION_DOWNLOAD_PACKAGING_SLIP:
                if (!$transactionId) {
                    Shop::Container()->getLogService()->error('No transaction ID provided on action ' . $action);
                    exit;
                }
                $this->downloadPackagingSlip($transactionId);
                break;
            
            case self::ACTION_REFUND:
                if (!$transactionId) {
                    Shop::Container()->getLogService()->error('No transaction ID provided on action ' . $action);
                    exit;
                }
                
                $amount = (float) $_REQUEST['amount'] ?? 0;
                $this->processRefund($transactionId, $amount);
                break;
            
            case self::ACTION_ORDER_DETAILS:
                $menuID = isset($_REQUEST['menuID']) ? (int) $_REQUEST['menuID'] : 0;
                $this->displayOrderInfo($_REQUEST, $menuID);
                break;
        }
    }
    
    /**
     * @param string|null $transactionId
     * @param float $amount
     * @return void
     */
    private function processRefund(?string $transactionId, float $amount): void
    {
        $this->refundService->makeRefund($transactionId, $amount);
        $transaction = $this->transactionService->getTransactionFromPortal($transactionId);
        $localTransaction = $this->transactionService->getLocalVRPaymentTransactionById((string)$transaction->getId());
        $order = new Bestellung((int) $localTransaction->order_id);
        
        $paymentMethodEntity = new Zahlungsart((int)$order->kZahlungsart);
        $paymentMethod = new Method($paymentMethodEntity->cModulId);
        $paymentMethod->setOrderStatusToPaid($order);
        
        $incomingPayment = new stdClass();
        $incomingPayment->fBetrag = -1 * $amount;
        $incomingPayment->cISO = $transaction->getCurrency();
        $incomingPayment->cZahlungsanbieter = $order->cZahlungsartName;
        $paymentMethod->addIncomingPayment($order, $incomingPayment);
    }

    private function displayOrderInfo(array $post, int $menuID): string
    {
        $translations = VRPaymentHelper::getTranslations($this->plugin->getLocalization(), [
            'jtl_vrpayment_order_number',
            'jtl_vrpayment_transaction_id',
            'jtl_vrpayment_transaction_state',
            'jtl_vrpayment_transaction_no_possible_actions',
            'jtl_vrpayment_complete',
            'jtl_vrpayment_cancel',
            'jtl_vrpayment_refunds',
            'jtl_vrpayment_download_invoice',
            'jtl_vrpayment_download_packaging_slip',
            'jtl_vrpayment_make_refund',
            'jtl_vrpayment_amount_to_refund',
            'jtl_vrpayment_refund_now',
            'jtl_vrpayment_refunded_amount',
            'jtl_vrpayment_amount',
            'jtl_vrpayment_refund_date',
            'jtl_vrpayment_total',
            'jtl_vrpayment_no_refunds_info_text',
        ]);

        $transactonId = $post['transaction_id'] ?? null;
        if ($transactonId === null) {
            return '';
        }
        
        $transaction = $this->transactionService->getTransactionFromPortal($transactonId);
        $currency = $transaction->getCurrency();

        $refunds = $this->refundService->getRefunds($post['order_id'], $transaction->getCurrency());
        $totalRefundsAmount = $this->refundService->getTotalRefundsAmount($refunds);

        preg_match('/([\d\.,]+)/', Preise::getLocalizedPriceString($post['total_amount'], $currency, true), $matches);
        $number = str_replace(',', '.', $matches[1]);
        $totalAmount = number_format((float)$number, 2, '.', '');

        $amountToBeRefunded = round(floatval($totalAmount) - $totalRefundsAmount, 2);

        $showRefundsForm = $post['transaction_state'] !== 'REFUNDED' && $amountToBeRefunded > 0;

        $result = $this->smarty->assign('adminUrl', $this->plugin->getPaths()->getadminURL())
            ->assign('refunds', $refunds)
            ->assign('totalAmount', $totalAmount)
            ->assign('totalAmountText', Preise::getLocalizedPriceString($post['total_amount'], $currency, true))
            ->assign('totalRefundsAmount', $totalRefundsAmount)
            ->assign('totalRefundsAmountText', Preise::getLocalizedPriceWithoutFactor($totalRefundsAmount, Currency::fromISO($currency), true))
            ->assign('amountToBeRefunded', $amountToBeRefunded)
            ->assign('showRefundsForm', $showRefundsForm)
            ->assign('orderNo', $post['order_no'])
            ->assign('transactionId', $post['transaction_id'])
            ->assign('transactionState', $post['transaction_state'])
            ->assign('translations', $translations)
            ->assign('menuId', '#plugin-tab-' . $menuID)
            ->assign('postUrl', Shop::getURL() . '/' . \PFAD_ADMIN . 'plugin.php?kPlugin=' . $this->plugin->getID())
            ->fetch($this->plugin->getPaths()->getAdminPath() . 'templates/vrpayment_order_details.tpl');

        print $result;
        exit;
    }

    /**
     * @param $transactionId
     * @return void
     */
    private function completeTransaction($transactionId): void
    {
        $transaction = $this->transactionService->getLocalVRPaymentTransactionById($transactionId);
        if ($transaction->state === 'AUTHORIZED') {
            $this->transactionService->completePortalTransaction($transactionId);
        }
    }

    /**
     * @param $transactionId
     * @return void
     */
    private function cancelTransaction($transactionId): void
    {
        $transaction = $this->transactionService->getLocalVRPaymentTransactionById($transactionId);
        if ($transaction->state === 'AUTHORIZED') {
            $this->transactionService->cancelPortalTransaction($transactionId);
        }
    }

    /**
     * @param $transactionId
     * @return void
     */
    private function downloadInvoice($transactionId): void
    {
        $transaction = $this->transactionService->getLocalVRPaymentTransactionById($transactionId);
        if (\in_array(strtoupper($transaction->state), self::FILE_DOWNLOAD_ALLOWED_STATES)) {
            $this->transactionService->downloadInvoice($transactionId);
        }
    }

    /**
     * @param $transactionId
     * @return void
     */
    private function downloadPackagingSlip($transactionId): void
    {
        $transaction = $this->transactionService->getLocalVRPaymentTransactionById($transactionId);
        if (\in_array(strtoupper($transaction->state), self::FILE_DOWNLOAD_ALLOWED_STATES)) {
            $this->transactionService->downloadPackagingSlip($transactionId);
        }
    }
}
