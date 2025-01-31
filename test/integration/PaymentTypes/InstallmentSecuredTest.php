<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocMissingThrowsInspection */
/**
 * This class defines integration tests to verify interface and
 * functionality of the payment method Installment Secured.
 *
 * Copyright (C) 2020 - today Unzer E-Com GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link  https://docs.unzer.com/
 *
 * @author  Simon Gabriel <development@unzer.com>
 *
 * @package  UnzerSDK\test\integration\PaymentTypes
 */
namespace UnzerSDK\test\integration\PaymentTypes;

use UnzerSDK\Constants\ApiResponseCodes;
use UnzerSDK\Exceptions\UnzerApiException;
use UnzerSDK\Resources\AbstractUnzerResource;
use UnzerSDK\Resources\Customer;
use UnzerSDK\Resources\CustomerFactory;
use UnzerSDK\Resources\EmbeddedResources\Address;
use UnzerSDK\Resources\InstalmentPlan;
use UnzerSDK\Resources\PaymentTypes\InstallmentSecured;
use UnzerSDK\Resources\TransactionTypes\Authorization;
use UnzerSDK\Resources\TransactionTypes\Charge;
use UnzerSDK\test\BaseIntegrationTest;
use function count;

class InstallmentSecuredTest extends BaseIntegrationTest
{
    /**
     * Verify the following features:
     * 1. fetching instalment plans.
     * 2. selecting plan
     * 3. create hp resource
     * 4. fetch hp resource
     * 5 test update hp resource
     *
     * @test
     */
    public function instalmentPlanShouldBeSelectable(): void
    {
        $plans = $this->unzer->fetchInstallmentPlans(119.0, 'EUR', 4.99);
        $this->assertGreaterThan(0, count($plans->getPlans()));

        /** @var InstalmentPlan $selectedPlan */
        $selectedPlan = $plans->getPlans()[1];

        $ins = new InstallmentSecured($selectedPlan, 'DE46940594210000012345', 'Manuel Weißmann');
        $this->unzer->createPaymentType($ins);
        $this->assertArraySubset($selectedPlan->expose(), $ins->expose());

        $fetchedIns = $this->unzer->fetchPaymentType($ins->getId());
        $this->assertEquals($ins->expose(), $fetchedIns->expose());

        $ins->setIban('DE89370400440532013000')
            ->setBic('COBADEFFXXX')
            ->setAccountHolder('Peter Universum')
            ->setInvoiceDate($this->getYesterdaysTimestamp())
            ->setInvoiceDueDate($this->getTomorrowsTimestamp());
        $insClone = clone $ins;
        $this->unzer->updatePaymentType($ins);
        $this->assertEquals($insClone->expose(), $ins->expose());
    }

    /**
     * Verify, backwards compatibility regarding fetching payment type and map it to invoice secured class.
     *
     * @test
     */
    public function hddTypeShouldBeFetchable(): InstallmentSecured
    {
        // Mock a hdd Type
        $date = $this->getTodaysDateString();
        $requestData =
            [
                "iban" =>"DE89370400440532013000",
                "bic" => "COBADEFFXXX",
                "accountHolder" => "Max Mustermann",
                "invoiceDueDate" => $date,
                "numberOfRates" => 3,
                "invoiceDate" => $date,
                "dayOfPurchase" => $date,
                "orderDate" => $date,
                "totalPurchaseAmount" => 119,
                "totalInterestAmount" => 0.96,
                "totalAmount" => 119.96,
                "effectiveInterestRate" => 4.99,
                "nominalInterestRate" => 4.92,
                "feeFirstRate" => 0,
                "feePerRate" => 0,
                "monthlyRate" => 39.99,
                "lastRate" => 39.98,
        ];

        $payload = json_encode($requestData);
        $hddMock = $this->getMockBuilder(InstallmentSecured::class)
            ->setMethods(['getUri', 'jsonSerialize'])
            ->getMock();
        $hddMock->method('getUri')->willReturn('/types/hire-purchase-direct-debit');
        $hddMock->method('jsonSerialize')->willReturn($payload);

        // When
        /** @var InstallmentSecured $insType */
        $this->unzer->createPaymentType($hddMock);
        $this->assertRegExp('/^s-hdd-[.]*/', $hddMock->getId());

        // Then
        $fetchedType = $this->unzer->fetchPaymentType($hddMock->getId());
        $this->assertInstanceOf(InstallmentSecured::class, $fetchedType);
        $this->assertRegExp('/^s-hdd-[.]*/', $fetchedType->getId());

        return $fetchedType;
    }

    /**
     * Verify fetched hdd type can be authorized and charged
     *
     * @test
     * @depends hddTypeShouldBeFetchable
     *
     * @param InstallmentSecured $hddType fetched ins type.
     *
     * @return AbstractUnzerResource|Charge
     *
     * @throws UnzerApiException
     */
    public function hddTypeAuthorizeAndCharge(InstallmentSecured $hddType)
    {
        $customer = $this->getMaximumCustomer();
        $basket = $this->createBasket();

        $auth = $hddType->authorize(119.00, 'EUR', 'https://unzer.com', $customer, null, null, $basket);
        $charge = $auth->getPayment()->charge();
        $this->assertNotNull($auth);
        $this->assertNotEmpty($auth->getId());
        $this->assertTrue($auth->isSuccess());

        return $charge;
    }

    /**
     * Verify fetched hdd payment can be shipped.
     *
     * @test
     * @depends hddTypeAuthorizeAndCharge
     */
    public function insTypeShouldBeShippable(Charge $hddCharge)
    {
        $invoiceId = 'i' . self::generateRandomId();
        $ship = $this->unzer->ship($hddCharge->getPayment(), $invoiceId);

        $this->assertNotNull($ship);
    }

    /**
     * Verify Installment Secured authorization (positive and negative).
     *
     * @test
     * @dataProvider CustomerRankingDataProvider
     *
     * @param $firstname
     * @param $lastname
     * @param $errorCode
     */
    public function installmentSecuredAuthorize($firstname, $lastname, $errorCode): void
    {
        $hpPlans = $this->unzer->fetchInstallmentPlans(119.0, 'EUR', 4.99);
        /** @var InstalmentPlan $selectedPlan */
        $selectedPlan = $hpPlans->getPlans()[0];
        $ins = new InstallmentSecured($selectedPlan, 'DE46940594210000012345', 'Manuel Weißmann');
        $this->unzer->createPaymentType($ins);

        $customer = $this->getCustomer()->setFirstname($firstname)->setLastname($lastname);
        $basket = $this->createBasket();

        try {
            $authorize = $ins->authorize(119.0, 'EUR', self::RETURN_URL, $customer, null, null, $basket);
            if ($errorCode!== null) {
                $this->assertTrue(false, 'Expected error for negative ranking test.');
            }
            $this->assertNotEmpty($authorize->getId());
        } catch (UnzerApiException $e) {
            if ($errorCode !== null) {
                $this->assertEquals($errorCode, $e->getCode());
            } else {
                $this->assertTrue(false, "No error expected for positive ranking test. ({$e->getCode()})");
            }
        }
    }

    /**
     * Verify fetching instalment plans.
     *
     * @test
     */
    public function instalmentPlanSelectionWithAllFieldsSet(): void
    {
        $yesterday = $this->getYesterdaysTimestamp();
        $plans = $this->unzer->fetchInstallmentPlans(119.0, 'EUR', 4.99, $yesterday);
        $this->assertGreaterThan(0, count($plans->getPlans()));

        /** @var InstalmentPlan $selectedPlan */
        $selectedPlan = $plans->getPlans()[0];
        $this->assertCount($selectedPlan->getNumberOfRates(), $selectedPlan->getInstallmentRates(), 'The number of rates should equal the actual rate count.');
        $ins = new InstallmentSecured($selectedPlan, 'DE46940594210000012345', 'Manuel Weißmann', $yesterday, 'COBADEFFXXX', $yesterday, $this->getTomorrowsTimestamp());
        $this->unzer->createPaymentType($ins);
        $this->assertArraySubset($selectedPlan->expose(), $ins->expose());
    }

    /**
     * Verify charge.
     *
     * @test
     */
    public function verifyChargingAnInitializedInstallmentSecured(): void
    {
        $yesterday = $this->getYesterdaysTimestamp();
        $plans = $this->unzer->fetchInstallmentPlans(119.0, 'EUR', 4.99, $yesterday);
        $this->assertGreaterThan(0, count($plans->getPlans()));

        /** @var InstalmentPlan $selectedPlan */
        $selectedPlan = $plans->getPlans()[0];
        $ins = new InstallmentSecured($selectedPlan, 'DE46940594210000012345', 'Manuel Weißmann', $yesterday, 'COBADEFFXXX', $yesterday, $this->getTomorrowsTimestamp());
        $this->unzer->createPaymentType($ins);

        $authorize = $ins->authorize(119.0, 'EUR', self::RETURN_URL, $this->getCustomer(), null, null, $basket = $this->createBasket());
        $payment = $authorize->getPayment();
        $charge = $payment->charge();
        $this->assertNotNull($charge->getId());
    }

    //<editor-fold desc="Shipment">

    /**
     * Verify charge and ship.
     *
     * @test
     */
    public function verifyShippingAChargedInstallmentSecured(): void
    {
        $yesterday = $this->getYesterdaysTimestamp();
        $plans = $this->unzer->fetchInstallmentPlans(119.0, 'EUR', 4.99, $yesterday);

        /** @var InstalmentPlan $selectedPlan */
        $selectedPlan = $plans->getPlans()[0];
        $ins = new InstallmentSecured($selectedPlan, 'DE89370400440532013000', 'Manuel Weißmann', $yesterday, 'COBADEFFXXX', $this->getTodaysDateString(), $this->getTomorrowsTimestamp());
        $this->unzer->createPaymentType($ins);

        $authorize = $ins->authorize(119.0, 'EUR', self::RETURN_URL, $this->getCustomer(), null, null, $this->createBasket());
        $payment = $authorize->getPayment();
        $payment->charge();
        $shipment = $payment->ship();
        $this->assertNotNull($shipment->getId());
    }

    //</editor-fold>

    //<editor-fold desc="Charge cancel">

    /**
     * Verify full cancel of charged HP.
     *
     * @test
     *
     * @depends verifyChargingAnInitializedInstallmentSecured
     */
    public function verifyChargeAndFullCancelAnInitializedInstallmentSecured(): void
    {
        $yesterday = $this->getYesterdaysTimestamp();
        $plans = $this->unzer->fetchInstallmentPlans(119.0, 'EUR', 4.99, $yesterday);
        $this->assertGreaterThan(0, count($plans->getPlans()));

        /** @var InstalmentPlan $selectedPlan */
        $selectedPlan = $plans->getPlans()[0];
        $ins = new InstallmentSecured($selectedPlan, 'DE46940594210000012345', 'Manuel Weißmann', $yesterday, 'COBADEFFXXX', $yesterday, $this->getTomorrowsTimestamp());
        $this->unzer->createPaymentType($ins);

        $authorize = $ins->authorize(119.0, 'EUR', self::RETURN_URL, $this->getCustomer(), null, null, $basket = $this->createBasket());
        $payment = $authorize->getPayment();
        $payment->charge();
        $cancel = $payment->cancelAmount();
        $this->assertGreaterThan(0, count($cancel));
    }

    /**
     * Verify full cancel of charged HP.
     *
     * @test
     *
     * @depends verifyChargingAnInitializedInstallmentSecured
     */
    public function verifyPartlyCancelChargedInstallmentSecured(): void
    {
        $yesterday = $this->getYesterdaysTimestamp();
        $plans = $this->unzer->fetchInstallmentPlans(119.0, 'EUR', 4.99, $yesterday);
        $this->assertGreaterThan(0, count($plans->getPlans()));

        /** @var InstalmentPlan $selectedPlan */
        $selectedPlan = $plans->getPlans()[0];
        $ins = new InstallmentSecured($selectedPlan, 'DE46940594210000012345', 'Manuel Weißmann', $yesterday, 'COBADEFFXXX', $yesterday, $this->getTomorrowsTimestamp());
        $this->unzer->createPaymentType($ins);

        $authorize = $ins->authorize(119.0, 'EUR', self::RETURN_URL, $this->getCustomer(), null, null, $basket = $this->createBasket());
        $payment = $authorize->getPayment();
        $payment->charge();
        $cancel = $payment->cancelAmount(59.5, null, null, 50.0, 9.5);
        $this->assertCount(1, $cancel);
        $this->assertTrue($payment->isCompleted());
    }

    /**
     * Verify full cancel of charged HP after shipment.
     *
     * @test
     *
     * @depends verifyChargingAnInitializedInstallmentSecured
     */
    public function verifyChargeAndFullCancelAnInitializedInstallmentSecuredAfterShipment(): void
    {
        $yesterday = $this->getYesterdaysTimestamp();
        $plans = $this->unzer->fetchInstallmentPlans(119.0, 'EUR', 4.99, $yesterday);
        $this->assertGreaterThan(0, count($plans->getPlans()));

        /** @var InstalmentPlan $selectedPlan */
        $selectedPlan = $plans->getPlans()[0];
        $ins = new InstallmentSecured($selectedPlan, 'DE89370400440532013000', 'Manuel Weißmann', $yesterday, 'COBADEFFXXX', $this->getTodaysDateString(), $this->getTomorrowsTimestamp());
        $this->unzer->createPaymentType($ins);

        $authorize = $ins->authorize(119.0, 'EUR', self::RETURN_URL, $this->getCustomer(), null, null, $basket = $this->createBasket());
        $payment = $authorize->getPayment();

        $hddCharge = $payment->charge();
        $invoiceId = 'i' . self::generateRandomId();
        $ship = $this->unzer->ship($hddCharge->getPayment(), $invoiceId);
        $this->assertNotNull($ship);

        $cancel = $payment->cancelAmount();
        $this->assertGreaterThan(0, count($cancel));
    }

    /**
     * Verify full cancel of charged HP after shipment.
     *
     * @test
     *
     * @depends verifyChargingAnInitializedInstallmentSecured
     */
    public function verifyPartlyCancelChargedInstallmentSecuredAfterShipment(): void
    {
        $yesterday = $this->getYesterdaysTimestamp();
        $plans = $this->unzer->fetchInstallmentPlans(119.0, 'EUR', 4.99, $yesterday);
        $this->assertGreaterThan(0, count($plans->getPlans()));

        /** @var InstalmentPlan $selectedPlan */
        $selectedPlan = $plans->getPlans()[0];
        $ins = new InstallmentSecured($selectedPlan, 'DE89370400440532013000', 'Manuel Weißmann', $yesterday, 'COBADEFFXXX', $this->getTodaysDateString(), $this->getTomorrowsTimestamp());
        $this->unzer->createPaymentType($ins);

        $authorize = $ins->authorize(119.0, 'EUR', self::RETURN_URL, $this->getCustomer(), null, null, $basket = $this->createBasket());
        $payment = $authorize->getPayment();

        $hddCharge = $payment->charge();
        $invoiceId = 'i' . self::generateRandomId();
        $ship = $this->unzer->ship($hddCharge->getPayment(), $invoiceId);
        $this->assertNotNull($ship);

        $cancel = $payment->cancelAmount(59.5, null, null, 50.0, 9.5);
        $this->assertCount(1, $cancel);
        $this->assertTrue($payment->isCompleted());
    }

    //</editor-fold>

    //<editor-fold desc="Helper">

    /**
     * @return Customer
     */
    public function getCustomer(): Customer
    {
        $customer = CustomerFactory::createCustomer('Manuel', 'Weißmann');
        $address = (new Address())
            ->setStreet('Hugo-Junckers-Straße 3')
            ->setState('DE-BO')
            ->setZip('60386')
            ->setCity('Frankfurt am Main')
            ->setCountry('DE');
        $customer
            ->setBillingAddress($address)
            ->setBirthDate('2000-12-12')
            ->setEmail('manuel-weissmann@unzer.com');

        return $customer;
    }

    //</editor-fold>

    //<editor-fold desc="Data Providers">

    /**
     * @return array
     */
    public function CustomerRankingDataProvider(): array
    {
        return [
            'positive' => ['Manuel', 'Weißmann', null],
            'negative #1 - Payment guarantee' => ['Manuel', 'Zeißmann', ApiResponseCodes::SDM_ERROR_CURRENT_INSURANCE_EVENT],
            'positive #2 - Limit exceeded' => ['Manuel', 'Leißmann', ApiResponseCodes::SDM_ERROR_LIMIT_EXCEEDED],
            'positive #3 - Negative trait' => ['Imuel', 'Seißmann', ApiResponseCodes::SDM_ERROR_NEGATIVE_TRAIT_FOUND],
            'positive #4 - Negative increased risk' => ['Jamuel', 'Seißmann', ApiResponseCodes::SDM_ERROR_INCREASED_RISK]
        ];
    }

    //</editor-fold>
}
