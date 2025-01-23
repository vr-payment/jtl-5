<?php declare(strict_types=1);

use Plugin\jtl_vrpayment\Webhooks\VRPaymentWebhookManager;

/** @global JTL\Plugin\PluginInterface $plugin */
$webhookManager = new VRPaymentWebhookManager($plugin);
$webhookManager->listenForWebhooks();
exit;
