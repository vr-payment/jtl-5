<?php
/**
 * VRPay SDK
 *
 * This library allows to interact with the VRPay payment service.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


namespace VRPayment\Sdk\Model;
use \VRPayment\Sdk\ObjectSerializer;

/**
 * TransactionUserInterfaceType model
 *
 * @category    Class
 * @description 
 * @package     VRPayment\Sdk
 * @author      VR Payment GmbH
 * @license     http://www.apache.org/licenses/LICENSE-2.0 Apache License v2
 */
class TransactionUserInterfaceType
{
    /**
     * Possible values of this enum
     */
    const IFRAME = 'IFRAME';
    const LIGHTBOX = 'LIGHTBOX';
    const PAYMENT_PAGE = 'PAYMENT_PAGE';
    const MOBILE_SDK = 'MOBILE_SDK';
    const TERMINAL = 'TERMINAL';
    
    /**
     * Gets allowable values of the enum
     * @return string[]
     */
    public static function getAllowableEnumValues()
    {
        return [
            self::IFRAME,
            self::LIGHTBOX,
            self::PAYMENT_PAGE,
            self::MOBILE_SDK,
            self::TERMINAL,
        ];
    }
}


