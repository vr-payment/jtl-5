<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\Webhooks;

use Plugin\jtl_vrpayment\Webhooks\Strategies\Interfaces\VRPaymentOrderUpdateStrategyInterface;

class VRPaymentOrderUpdater
{
	/**
	 * @var VRPaymentOrderUpdateStrategyInterface $strategy
	 */
	private $strategy;

	public function __construct(VRPaymentOrderUpdateStrategyInterface $strategy)
	{
		$this->strategy = $strategy;
	}

	/**
	 * @param VRPaymentOrderUpdateStrategyInterface $strategy
	 * @return void
	 */
	public function setStrategy(VRPaymentOrderUpdateStrategyInterface $strategy)
	{
		$this->strategy = $strategy;
	}

	/**
	 * @param string $transactionId
	 * @return void
	 */
	public function updateOrderStatus(string $transactionId): void
	{
		$this->strategy->updateOrderStatus($transactionId);
	}
}
