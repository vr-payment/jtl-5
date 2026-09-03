<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\frontend;

use JTL\Checkout\Bestellung;
use JTL\DB\DbInterface;
use JTL\Plugin\PluginInterface;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\jtl_vrpayment\Services\VRPaymentRefundService;
use Plugin\jtl_vrpayment\Services\VRPaymentTransactionService;
use Plugin\jtl_vrpayment\VRPaymentHelper;
use VRPayment\Sdk\ApiClient;
use VRPayment\Sdk\Model\TransactionState;

final class Handler
{
    /** @var PluginInterface */
    private $plugin;

    /** @var ApiClient|null */
    private $apiClient;

    /** @var DbInterface|null */
    private $db;

    /** @var VRPaymentTransactionService */
    private $transactionService;

    /**
     * @var VRPaymentRefundService $refundService
     */
    protected $refundService;

    /**
     * Handler constructor.
     * @param PluginInterface $plugin
     * @param DbInterface|null $db
     * @param ApiClient $apiClient
     */
    public function __construct(PluginInterface $plugin, ApiClient $apiClient, ?DbInterface $db = null)
    {
        $this->plugin = $plugin;
        $this->apiClient = $apiClient;
        $this->db = $db ?? Shop::Container()->getDB();
        $this->transactionService = new VRPaymentTransactionService($this->apiClient, $this->plugin);
        $this->refundService = new VRPaymentRefundService($this->apiClient, $this->plugin);
    }

    /**
     * @return string
     */
    public function createTransaction(): int
    {
        $transactionId = $_SESSION[VRPaymentHelper::SESSION_TRANSACTION_ID] ?? null;
        if (!$transactionId) {
            $order = new Bestellung();
            $order->Positionen = $_SESSION['Warenkorb']->PositionenArr;

            $randomString = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16);
            $order->cBestellNr = $randomString;

            $createdTransaction = $this->transactionService->createTransaction($order);
            $transactionId = $createdTransaction->getId();

            $_SESSION[VRPaymentHelper::SESSION_TRANSACTION_ID] = $transactionId;
        }

        return (int)$transactionId;
    }

    public function fetchPossiblePaymentMethods(string $transactionId)
    {
        return $this->transactionService->fetchPossiblePaymentMethods($transactionId);
    }

    public function getPaymentMethodsForForm(JTLSmarty $smarty): array
    {
        // Do not call the VR Payment API while the customer is only viewing the
        // payment-method list. JTL has already filtered the locally active methods
        // by shipping method/customer group. Remote eligibility is checked only
        // after a VR Payment method has actually been selected.
        $paymentMethods = $smarty->getTemplateVars('Zahlungsarten');

        return \is_array($paymentMethods) ? $paymentMethods : [];
    }

    /**
     * Lazily creates/synchronises the VR Payment transaction only after the
     * customer selected a VR Payment method. Returns false when that method is
     * unavailable or the remote API cannot be reached; callers can then keep the
     * customer in JTL's payment-selection step instead of producing HTTP 500.
     */
    public function prepareSelectedPayment(): bool
    {
        $paymentMethod = $_SESSION['Zahlungsart'] ?? null;
        if (!$this->isVRPaymentMethod($paymentMethod)) {
            $this->clearPaymentValidation();
            return true;
        }

        $stateHash = $this->getCheckoutStateHash();
        $transactionId = (int)($_SESSION[VRPaymentHelper::SESSION_TRANSACTION_ID] ?? 0);
        $moduleId = \strtolower((string)($paymentMethod->cModulId ?? ''));

        // A validated, unchanged checkout must not perform another remote request.
        if (
            $transactionId > 0
            && ($_SESSION['vrpaymentValidatedStateHash'] ?? null) === $stateHash
            && (int)($_SESSION['vrpaymentValidatedTransactionId'] ?? 0) === $transactionId
            && ($_SESSION['vrpaymentValidatedMethod'] ?? null) === $moduleId
            && !empty($_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_ID])
        ) {
            return true;
        }

        try {
            if ($transactionId <= 0) {
                $transactionId = $this->createTransaction();
            } else {
                // Reuse an existing pending transaction (e.g. after editing an
                // address/cart). Finished/failed transactions are never reused.
                $transaction = $this->transactionService->getTransactionFromPortal($transactionId);
                if (
                    empty($transaction)
                    || empty($transaction->getVersion())
                    || $transaction->getState() !== TransactionState::PENDING
                ) {
                    $this->clearTransactionSession();
                    $transactionId = $this->createTransaction();
                } else {
                    $this->transactionService->updateTransaction($transactionId);
                }
            }

            $possiblePaymentMethods = $this->fetchPossiblePaymentMethods((string)$transactionId);
            foreach ($possiblePaymentMethods as $possiblePaymentMethod) {
                $possibleModuleId = \strtolower(
                    VRPaymentHelper::PAYMENT_METHOD_PREFIX . '_' . $possiblePaymentMethod->getId()
                );
                if ($possibleModuleId !== $moduleId) {
                    continue;
                }

                $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_ID] = $possiblePaymentMethod->getId();
                $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_NAME] = $possiblePaymentMethod->getName();
                $_SESSION['vrpaymentValidatedStateHash'] = $stateHash;
                $_SESSION['vrpaymentValidatedTransactionId'] = $transactionId;
                $_SESSION['vrpaymentValidatedMethod'] = $moduleId;

                return true;
            }

            VRPaymentHelper::log(
                'prepareSelectedPayment: selected method ' . $moduleId
                . ' is not available for transaction ' . $transactionId . '.'
            );
            $this->clearPaymentValidation();
            $this->addPaymentUnavailableAlert();

            return false;
        } catch (\Throwable $e) {
            $this->logCheckoutException('prepareSelectedPayment', $e);
            // Do not reuse a transaction whose current remote state is unknown.
            $this->clearTransactionSession();
            $this->addPaymentUnavailableAlert();

            return false;
        }
    }

    public function isSelectedVRPaymentMethod(): bool
    {
        return $this->isVRPaymentMethod($_SESSION['Zahlungsart'] ?? null);
    }

    private function isVRPaymentMethod($paymentMethod): bool
    {
        if (!\is_object($paymentMethod)) {
            return false;
        }

        $provider = \strtolower((string)($paymentMethod->cAnbieter ?? ''));
        $moduleId = \strtolower((string)($paymentMethod->cModulId ?? ''));

        return $provider === 'vrpayment'
            || \str_starts_with($moduleId, VRPaymentHelper::PAYMENT_METHOD_PREFIX . '_');
    }

    private function getCheckoutStateHash(): string
    {
        $currency = $_SESSION['Waehrung']?->getCode() ?? ($_SESSION['cWaehrungName'] ?? '');
        $lineItems = $_SESSION['Warenkorb']?->PositionenArr ?? [];

        $state = [
            'billing' => $this->getAddressFingerprint($_SESSION['Kunde'] ?? null),
            'shipping' => $this->getAddressFingerprint($_SESSION['Lieferadresse'] ?? null),
            'currency' => (string)$currency,
            'lineItems' => \hash('sha256', \serialize($lineItems)),
            'paymentMethod' => \strtolower((string)($_SESSION['Zahlungsart']->cModulId ?? '')),
        ];

        return \hash('sha256', (string)\json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function getAddressFingerprint($address): array
    {
        if (!\is_object($address) && !\is_array($address)) {
            return [];
        }

        $address = (object)$address;
        $fields = [
            'cFirma', 'cVorname', 'cNachname', 'cStrasse', 'cHausnummer',
            'cPLZ', 'cOrt', 'cLand', 'cBundesland', 'cMail', 'cTel', 'cMobil',
            'cTitel', 'cAnrede'
        ];
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = (string)($address->{$field} ?? '');
        }

        return $result;
    }

    private function clearPaymentValidation(): void
    {
        unset(
            $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_ID],
            $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_NAME],
            $_SESSION['vrpaymentValidatedStateHash'],
            $_SESSION['vrpaymentValidatedTransactionId'],
            $_SESSION['vrpaymentValidatedMethod'],
            // Legacy cache keys from the original eager checkout implementation.
            $_SESSION['arrayOfPossibleMethods'],
            $_SESSION['addressCheck'],
            $_SESSION['currencyCheck'],
            $_SESSION['lineItemsCheck'],
            $_SESSION['paymentMethodsCheck'],
            $_SESSION['lastCartItemHash']
        );
    }

    private function clearTransactionSession(): void
    {
        unset($_SESSION[VRPaymentHelper::SESSION_TRANSACTION_ID]);
        $this->clearPaymentValidation();
    }

    private function addPaymentUnavailableAlert(): void
    {
        $translations = VRPaymentHelper::getTranslations(
            $this->plugin->getLocalization(),
            ['jtl_vrpayment_payment_not_available_by_country_or_currency'],
            false
        );
        $message = (string)($translations['jtl_vrpayment_payment_not_available_by_country_or_currency'] ?? '');
        if ($message === '') {
            $message = 'Die ausgewählte Zahlungsart ist derzeit nicht verfügbar. Bitte wählen Sie eine andere Zahlungsart.';
        }

        Shop::Container()->getAlertService()->addAlert(
            Alert::TYPE_ERROR,
            $message,
            'vrpayment_payment_unavailable'
        );
    }

    private function logCheckoutException(string $context, \Throwable $e): void
    {
        $message = $context . ': ' . \get_class($e) . ' (' . $e->getCode() . '): ' . $e->getMessage();
        VRPaymentHelper::log($message);
        Shop::Container()->getLogService()->error('VR Payment checkout error: {message}', ['message' => $message]);
    }

    /**
     * @param array $args
     * @return void
     */
    public function completeOrderAfterWawi(array $args): void
    {
        $order = $args['oBestellung'] ?? [];
        if (!$order || (int)$args['status'] !== \BESTELLUNG_STATUS_BEZAHLT) {
            return;
        }

        $obj = Shop::Container()->getDB()->selectSingleRow('vrpayment_transactions', 'order_id', $order->kBestellung);
        $transactionId = $obj->transaction_id ?? '';
        if (empty($transactionId)) {
            return;
        }

        $transaction = $this->transactionService->getLocalVRPaymentTransactionById((string)$transactionId);
        if ($transaction->state === TransactionState::AUTHORIZED) {
            $this->transactionService->completePortalTransaction($transactionId);
        }
    }

    /**
     * @param array $args
     * @return void
     */
    public function cancelOrderAfterWawi(array $args): void
    {
        $order = $args['oBestellung'] ?? [];

        $obj = Shop::Container()->getDB()->selectSingleRow('vrpayment_transactions', 'order_id', $order->kBestellung);
        $transactionId = $obj->transaction_id ?? '';
        if (empty($transactionId)) {
            return;
        }

        $transaction = $this->transactionService->getLocalVRPaymentTransactionById((string)$transactionId);

        switch ((int)$order->cStatus) {
            case \BESTELLUNG_STATUS_IN_BEARBEITUNG:
                if ($transaction->state === TransactionState::AUTHORIZED) {
                    $this->transactionService->cancelPortalTransaction($transactionId);
                }
                break;

            case \BESTELLUNG_STATUS_BEZAHLT:
                try {
                    $portalTransaction = $this->transactionService->getTransactionFromPortal($transactionId);
                    $this->refundService->makeRefund((string)$transactionId, (float)$portalTransaction->getAuthorizationAmount());
                } catch (\Exception $e) {

                }
                break;
        }
    }

    /**
     * @param string $spaceId
     * @param int $transactionId
     * @return void
     */
    public function confirmTransaction(string $spaceId, int $transactionId): void
    {
        $transaction = $this->apiClient->getTransactionService()->read($spaceId, $transactionId);

        $statesToUpdate = [
          TransactionState::DECLINE,
          TransactionState::FAILED,
          TransactionState::VOIDED,
          TransactionState::PROCESSING
        ];

        if (empty($transaction) || empty($transaction->getVersion()) || in_array($transaction->getState(), $statesToUpdate)) {
            $_SESSION[VRPaymentHelper::SESSION_TRANSACTION_ID] = null;
            $linkHelper = Shop::Container()->getLinkService();
            \header('Location: ' . $linkHelper->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1');
            exit;
        }

        $this->transactionService->confirmTransaction($transaction);
    }

    public function getRedirectUrlAfterCreatedTransaction($orderData): string
    {
        $linkHelper = Shop::Container()->getLinkService();
        if (!$this->isSelectedVRPaymentMethod()) {
            return $linkHelper->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1';
        }

        // Keep the final order object available for the existing address mapping.
        $_SESSION[VRPaymentHelper::SESSION_ORDER_DATA] = $orderData;

        if (!$this->prepareSelectedPayment()) {
            return $linkHelper->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1';
        }

        $config = VRPaymentHelper::getConfigByID($this->plugin->getId());
        $spaceId = (string)$config[VRPaymentHelper::SPACE_ID];
        $createdTransactionId = (int)($_SESSION[VRPaymentHelper::SESSION_TRANSACTION_ID] ?? 0);

        if ($createdTransactionId <= 0) {
            return $linkHelper->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1';
        }

        try {
            $integration = VRPaymentHelper::getIntegrationType($this->plugin->getId());

            // The iframe javascript URL is not needed for Payment Page integration.
            if ($integration !== VRPaymentHelper::INTEGRATION_TYPE_PAYMENT_PAGE) {
                $_SESSION[VRPaymentHelper::SESSION_JAVASCRIPT_URL] = $this->apiClient->getTransactionIframeService()
                    ->javascriptUrl($spaceId, $createdTransactionId);
                $_SESSION[VRPaymentHelper::SESSION_APP_JS_URL] = $this->plugin->getPaths()->getBaseURL()
                    . 'frontend/js/vrpayment-app.js?' . time();
            }

            // Normally this was already resolved when Wero/VR Payment was selected.
            // Keep an API fallback for existing sessions and unusual checkout flows.
            if (empty($_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_ID])) {
                $paymentMethod = $this->transactionService->getTransactionPaymentMethod(
                    $createdTransactionId,
                    $spaceId
                );
                if (empty($paymentMethod)) {
                    $this->addPaymentUnavailableAlert();
                    return $linkHelper->getStaticRoute('bestellvorgang.php') . '?editZahlungsart=1';
                }
                $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_ID] = $paymentMethod->getId();
                $_SESSION[VRPaymentHelper::SESSION_PAYMENT_METHOD_NAME] = $paymentMethod->getName();
            }

            $this->confirmTransaction($spaceId, $createdTransactionId);

            if ($integration === VRPaymentHelper::INTEGRATION_TYPE_PAYMENT_PAGE) {
                return $this->apiClient->getTransactionPaymentPageService()
                    ->paymentPageUrl($spaceId, $createdTransactionId);
            }

            return VRPaymentHelper::PLUGIN_CUSTOM_PAGES['payment-page'][$_SESSION['cISOSprache']];
        } catch (\Throwable $e) {
            $this->logCheckoutException('getRedirectUrlAfterCreatedTransaction', $e);
            return Shop::getURL() . '/'
                . VRPaymentHelper::PLUGIN_CUSTOM_PAGES['fail-page'][$_SESSION['cISOSprache']];
        }
    }

    public function contentUpdate(array $args): void
    {
        global $step;

        switch (Shop::getPageType()) {
            case \PAGE_BESTELLVORGANG:
                // No VR Payment API traffic while browsing checkout steps.
                $this->setPaymentMethodLogoSize();
                break;

            case \PAGE_BESTELLABSCHLUSS:
                // Critical compatibility guard: never intercept PayPal or any other
                // payment provider merely because nWaehrendBestellung is enabled.
                if (
                    $this->isSelectedVRPaymentMethod()
                    && (int)($_SESSION['Zahlungsart']->nWaehrendBestellung ?? 0) === 1
                ) {
                    $smarty = $args['smarty'];
                    $order = $smarty->getTemplateVars('Bestellung');

                    if (!empty($order)) {
                        $redirectUrl = $this->getRedirectUrlAfterCreatedTransaction($order);
                        header("Location: " . $redirectUrl);
                        exit;
                    }
                }
                break;
        }
    }

    public function setPaymentMethodLogoSize(): void
    {
        global $step;

        if (\in_array($step, ['Zahlung', 'Versand'])) {
            $paymentMethodsCss = '<link rel="stylesheet" href="' . $this->plugin->getPaths()->getBaseURL() . 'frontend/css/checkout-payment-methods.css">';
            pq('head')->append($paymentMethodsCss);
        }
    }


}
