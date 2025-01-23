<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment;

if (file_exists(dirname(__DIR__) . '/jtl_vrpayment/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/jtl_vrpayment/vendor/autoload.php';
}

use JTL\Checkout\Bestellung;
use JTL\Checkout\Zahlungsart;
use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Plugin\Payment\Method;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\jtl_paypal\paymentmethod\PendingPayment;
use Plugin\jtl_vrpayment\adminmenu\AdminTabProvider;
use Plugin\jtl_vrpayment\frontend\Handler as FrontendHandler;
use Plugin\jtl_vrpayment\Services\VRPaymentPaymentService;
use Plugin\jtl_vrpayment\Services\VRPaymentTransactionService;
use Plugin\jtl_vrpayment\Services\VRPaymentWebhookService;
use VRPayment\Sdk\ApiException;
use VRPayment\Sdk\ApiClient;
use VRPayment\Sdk\Model\PaymentMethodConfiguration;

/**
 * Class Bootstrap
 * @package Plugin\jtl_vrpayment
 */
class Bootstrap extends Bootstrapper
{
    /**
     * @var VRPaymentPaymentService|null
     */
    private ?VRPaymentPaymentService $paymentService = null;
    
    /**
     * @var VRPaymentTransactionService|null
     */
    private ?VRPaymentTransactionService $transactionService = null;

    /**
     * @var ApiClient|null
     */
    private ?ApiClient $apiClient = null;

    /**
     * @inheritdoc
     */
    public function boot(Dispatcher $dispatcher)
    {
        parent::boot($dispatcher);
        $plugin = $this->getPlugin();

        if (Shop::isFrontend()) {
            $apiClient = VRPaymentHelper::getApiClient($plugin->getId());
            if (empty($apiClient)) {
                // Need to run composer install
                return;
            }
            $handler = new FrontendHandler($plugin, $apiClient, $this->getDB());
            $this->listenFrontendHooks($dispatcher, $handler);
        } else {
            $this->listenPluginSaveOptionsHook($dispatcher);
        }
    }

    /**
     * @inheritdoc
     */
    public function uninstalled(bool $deleteData = true)
    {
        parent::uninstalled($deleteData);
        $this->updatePaymentMethodStatus(VRPaymentPaymentService::STATUS_DISABLED);
    }

    /**
     * @inheritDoc
     */
    public function enabled(): void
    {
        parent::enabled();
        $this->updatePaymentMethodStatus();
    }

    /**
     * @inheritDoc
     */
    public function disabled(): void
    {
        parent::disabled();
        $this->updatePaymentMethodStatus(VRPaymentPaymentService::STATUS_DISABLED);
    }

    /**
     * @inheritDoc
     */
    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $tabsProvider = new AdminTabProvider($this->getPlugin(), $this->getDB(), $smarty);
        return $tabsProvider->createOrdersTab($menuID);
    }

    /**
     * @return void
     */
    protected function installPaymentMethodsOnSettingsSave(): void
    {
        $paymentService = $this->getPaymentService();
        $paymentService?->syncPaymentMethods();
    }

    /**
     * @return void
     */
    protected function registerWebhooksOnSettingsSave(): void
    {
        $apiClient = $this->getApiClient();
        if ($apiClient === null) {
            return;
        }

        $webhookService = new VRPaymentWebhookService($apiClient, $this->getPlugin()->getId());
        $webhookService->install();
    }

    /**
     * @param Dispatcher $dispatcher
     * @param FrontendHandler $handler
     * @return void
     */
    private function listenFrontendHooks(Dispatcher $dispatcher, FrontendHandler $handler): void
    {
        $cartUpdateListener = function () use ($handler) {
            $transactionId = $_SESSION['transactionId'] ?? null;
            if ($transactionId) {
                $lastCartItemsHash = $_SESSION['lastCartItemHash'] ?? null;
                $lineItems = $_SESSION['Warenkorb']?->PositionenArr;
                
                if ($lineItems === null) {
                    return;
                }

                $cartItemsHash = md5(json_encode($lineItems));
                
                if ($lastCartItemsHash !== $cartItemsHash) {
                    $_SESSION['lastCartItemHash'] = $cartItemsHash;
                    $transactionService = $this->getTransactionService();
                    $transactionService->updateTransaction($transactionId);
                }
            }
        };

        $cartUpdateHooks = [\HOOK_BESTELLVORGANG_PAGE, \HOOK_WARENKORB_PAGE, \HOOK_WARENKORB_CLASS_FUEGEEIN, \HOOK_WARENKORB_LOESCHE_POSITION, \HOOK_WARENKORB_LOESCHE_ALLE_SPEZIAL_POS];
        foreach ($cartUpdateHooks as $cartUpdateHook) {
            $dispatcher->listen('shop.hook.' . $cartUpdateHook, $cartUpdateListener);
        }

        $dispatcher->listen('shop.hook.' . \HOOK_SMARTY_OUTPUTFILTER, [$handler, 'contentUpdate']);
        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE, function ($args) use ($handler) {
            if (isset($_SESSION['finalize']) && $_SESSION['finalize'] === true) {
                unset($_SESSION['finalize']);
            } else {
                if (isset($_SESSION['Zahlungsart']->cModulId) && str_contains(\strtolower($_SESSION['Zahlungsart']->cModulId), 'vrpayment')) {
                    $redirectUrl = $handler->getRedirectUrlAfterCreatedTransaction($args['oBestellung']);
                    header("Location: " . $redirectUrl);
                    exit;
                }
            }
        });

        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLVORGANG_PAGE_STEPZAHLUNG, function () use ($handler) {
            $smarty = Shop::Smarty();
            $paymentMethods = $handler->getPaymentMethodsForForm($smarty);
            $smarty->assign('Zahlungsarten', $paymentMethods);
        });
        
        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLUNGEN_XML_BEARBEITESET, function ($args) use ($handler) {
            $order = $args['oBestellung'] ?? null;
            if ($order === null) {
                return;
            }
            
            $orderStatus = $order->cStatus ?? null;
            if ($orderStatus === null) {
                return;
            }
            
            if ((int)$orderStatus === \BESTELLUNG_STATUS_BEZAHLT) {
                $orderId = $args['oBestellung']->kBestellung ?? null;
                if ($orderId === null) {
                    return;
                }

                $order = new Bestellung($orderId);
                if (empty($order->kZahlungsart)) {
                    return;
                }
                $paymentMethodEntity = new Zahlungsart($order->kZahlungsart);
                
                if ($order->cStatus != \BESTELLUNG_STATUS_VERSANDT && $paymentMethodEntity->cAnbieter === 'VRPayment') {
                    $moduleId = $paymentMethodEntity->cModulId ?? '';
                    $paymentMethod = new Method($moduleId);
                    $paymentMethod->setOrderStatusToPaid($order);
                    
                    Shop::Container()
                      ->getDB()->update(
                        'tbestellung',
                        ['kBestellung',],
                        [$orderId],
                        (object)['cAbgeholt' => 'Y']
                    );
                }
            }
        });

        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, function ($args) use ($handler) {
            $handler->completeOrderAfterWawi($args);
        });

        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, function ($args) use ($handler) {
            $handler->cancelOrderAfterWawi($args);
        });
    }

    /**
     * @param Dispatcher $dispatcher
     * @return void
     */
    private function listenPluginSaveOptionsHook(Dispatcher $dispatcher): void
    {
        $dispatcher->listen('shop.hook.' . \HOOK_PLUGIN_SAVE_OPTIONS, function ($args_arr) {
            if ($this->isValidFormData($args_arr)) {
                $this->installPaymentMethodsOnSettingsSave();
                $this->registerWebhooksOnSettingsSave();
            }
            $args_arr['continue'] = false;
        });
    }
    
    /**
     * @param array $args
     * @return bool
     */
    private function isValidFormData(array $args): bool {
        $errors = [];

        // Validation rules configuration
        $validationRules = [
          'jtl_vrpayment_space_id' => ['type' => 'numeric', 'message' => 'Space ID must be a valid number.'],
          'jtl_vrpayment_user_id' => ['type' => 'numeric', 'message' => 'User ID must be a valid number.'],
          'jtl_vrpayment_application_key' => ['type' => 'string', 'message' => 'Application Key cannot be empty.'],
        ];
        
        // Validate form data
        foreach ($args['options'] as $option) {
            $rule = $validationRules[$option->valueID] ?? null;
            if ($rule) {
                // Perform validation check
                $errorFound = false;
                if ($rule['type'] === 'numeric' && (!is_numeric($option->value) || empty($option->value))) {
                    $errorFound = true;
                } elseif ($rule['type'] === 'string' && empty($option->value)) {
                    $errorFound = true;
                }
                
                if ($errorFound) {
                    $this->addValidationError($rule['message'], $errors);
                }
            }
        }
        
        // Add further validation for space access only if no errors in basic validation
        if (empty($errors)) {
            $apiClient = $this->getApiClient();
            if ($apiClient !== null) {
                $this->validateSpaceAccess($apiClient, $errors);
            }
        }
        
        return empty($errors);
    }
    
    private function addValidationError(string $message, array &$errors): void {
        $errors[] = $message;
        // Second parameter is key. We want to display all errors at once, so let's make it dynamic
        Shop::Container()->getAlertService()->addDanger($message, 'isValidFormData' . md5($message));
    }
    
    /**
     * @param ApiClient|null $apiClient
     * @param array $errors
     * @return void
     */
    private function validateSpaceAccess(ApiClient $apiClient, array &$errors): void {
        $config = VRPaymentHelper::getConfigByID($this->getPlugin()->getId());
        $spaceId = $config[VRPaymentHelper::SPACE_ID] ?? null;
        
        try {
            $spaceData = $apiClient->getSpaceService()->read($spaceId);
            if (is_null($spaceData) || is_null($spaceData->getAccount())) {
                $this->addValidationError('The space does not exist or you do not have access to it.', $errors);
            }
        } catch (ApiException $e) {
            $this->addValidationError($e->getResponseBody()->message, $errors);
        }
    }

    /**
     * @return VRPaymentPaymentService|null
     */
    private function getPaymentService(): ?VRPaymentPaymentService
    {
        $apiClient = $this->getApiClient();
        if ($apiClient === null) {
            return null;
        }

        if ($this->paymentService === null) {
            $this->paymentService = new VRPaymentPaymentService($apiClient, $this->getPlugin()->getId());
        }

        return $this->paymentService;
    }
    
    /**
     * @return VRPaymentTransactionService|null
     */
    private function getTransactionService(): ?VRPaymentTransactionService
    {
        $apiClient = $this->getApiClient();
        if ($apiClient === null) {
            return null;
        }
        
        if ($this->transactionService === null) {
            $this->transactionService = new VRPaymentTransactionService($apiClient, $this->getPlugin());
        }
        
        return $this->transactionService;
    }

    /**
     * @return ApiClient|null
     */
    private function getApiClient(): ?ApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = VRPaymentHelper::getApiClient($this->getPlugin()->getId());
        }

        return $this->apiClient;
    }

    /**
     * @param int $status
     * @return void
     */
    private function updatePaymentMethodStatus(int $status = VRPaymentPaymentService::STATUS_ENABLED): void
    {
        $paymentService = $this->getPaymentService();
        $paymentService?->updatePaymentMethodStatus($status);
    }
}
