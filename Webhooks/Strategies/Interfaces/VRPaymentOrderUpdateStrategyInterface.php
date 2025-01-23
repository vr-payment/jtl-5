<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\Webhooks\Strategies\Interfaces;

interface VRPaymentOrderUpdateStrategyInterface
{
	public function updateOrderStatus(string $entityId): void;
}
