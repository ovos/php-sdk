<?php
/**
 * This class defines integration tests to verify interface and functionality of shipment.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * @copyright Copyright © 2016-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/
 *
 * @author  Simon Gabriel <development@heidelpay.com>
 *
 * @package  heidelpay/mgw_sdk/tests/integration
 */
namespace heidelpay\MgwPhpSdk\test\integration;

use heidelpay\MgwPhpSdk\Constants\Currencies;
use heidelpay\MgwPhpSdk\Exceptions\HeidelpayApiException;
use heidelpay\MgwPhpSdk\Exceptions\HeidelpaySdkException;
use heidelpay\MgwPhpSdk\Resources\PaymentTypes\InvoiceGuaranteed;
use heidelpay\MgwPhpSdk\test\BasePaymentTest;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;

class ShipmentTest extends BasePaymentTest
{
    /**
     * Verify shipment transaction can be called.
     *
     * @test
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws \RuntimeException
     * @throws HeidelpayApiException
     * @throws HeidelpaySdkException
     */
    public function shipmentShouldBeCreatableAndFetchable()
    {
        $invoiceGuaranteed = new InvoiceGuaranteed();
        $authorize = $this->heidelpay->authorize(
            100.0,
            Currencies::EURO,
            $invoiceGuaranteed,
            self::RETURN_URL,
            $this->getMaximumCustomer());
        $this->assertNotNull($authorize->getId());
        $this->assertNotNull($authorize);

        $shipment = $this->heidelpay->ship($authorize->getPayment());
        $this->assertNotNull($shipment->getId());
        $this->assertNotNull($shipment);

        $fetchedShipment = $this->heidelpay->fetchShipment($shipment->getPayment()->getId(), $shipment->getId());
        $this->assertNotEmpty($fetchedShipment);
        $this->assertEquals($shipment->expose(), $fetchedShipment->expose());
    }

    /**
     * Verify shipment transaction can be called on the payment object.
     *
     * @test
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws \RuntimeException
     * @throws HeidelpayApiException
     * @throws HeidelpaySdkException
     */
    public function shipmentCanBeCalledOnThePaymentObject()
    {
        $invoiceGuaranteed = new InvoiceGuaranteed();
        $authorize = $this->heidelpay->authorize(
            100.0,
            Currencies::EURO,
            $invoiceGuaranteed,
            self::RETURN_URL,
            $this->getMaximumCustomer()
        );

        $payment  = $authorize->getPayment();
        $shipment = $payment->ship();
        $this->assertNotNull($shipment);
        $this->assertNotEmpty($shipment->getId());
        $this->assertNotEmpty($shipment->getUniqueId());
        $this->assertNotEmpty($shipment->getShortId());

        $fetchedShipment = $this->heidelpay->fetchShipment($shipment->getPayment()->getId(), $shipment->getId());
        $this->assertNotEmpty($fetchedShipment);
        $this->assertEquals($shipment->expose(), $fetchedShipment->expose());
    }

    /**
     * Verify shipment can be performed with payment object.
     *
     * @test
     *
     * @throws AssertionFailedError
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws \RuntimeException
     * @throws HeidelpayApiException
     * @throws HeidelpaySdkException
     */
    public function shipmentShouldBePossibleWithPaymentObject()
    {
        $invoiceGuaranteed = new InvoiceGuaranteed();
        $authorize = $this->heidelpay->authorize(
            100.0,
            Currencies::EURO,
            $invoiceGuaranteed,
            self::RETURN_URL,
            $this->getMaximumCustomer()
        );

        $payment  = $authorize->getPayment();
        $shipment = $this->heidelpay->ship($payment);
        $this->assertNotNull($shipment->getId());
        $this->assertNotNull($shipment);
    }
}