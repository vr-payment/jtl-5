<?php declare(strict_types=1);

namespace Plugin\jtl_vrpayment\Services;

use JTL\Shop;
use VRPayment\Sdk\ApiClient;

class VRPaymentOrderService
{
	public function updateOrderStatus($orderId, $currentStatus, $newStatus)
	{
		return Shop::Container()
		    ->getDB()->update(
			    'tbestellung',
			    ['kBestellung', 'cStatus'],
			    [$orderId, $currentStatus],
			    (object)['cStatus' => $newStatus]
		    );
	}
}
