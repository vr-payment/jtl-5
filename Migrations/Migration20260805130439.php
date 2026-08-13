<?php

declare(strict_types=1);

namespace Plugin\jtl_vrpayment\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20260805130439 extends Migration implements IMigration
{
    protected $description = 'Add cancellation field to the vrpayment_transactions table';

    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->execute("ALTER TABLE `vrpayment_transactions`
            ADD COLUMN `cancellation_email_sent` tinyint(1) NOT NULL DEFAULT '0'
            AFTER `fulfill_email_sent`;");
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->execute("ALTER TABLE `vrpayment_transactions` DROP COLUMN `cancellation_email_sent`;");
    }
}
