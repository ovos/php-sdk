<?php
/**
 * This is the controller for the SEPA direct debit guaranteed example.
 * It is called when the pay button on the index page is clicked.
 *
 * Copyright (C) 2019 heidelpay GmbH
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
 * @link  https://docs.heidelpay.com/
 *
 * @author  Simon Gabriel <development@heidelpay.com>
 *
 * @package  heidelpayPHP/examples
 */

/** Require the constants of this example */
require_once __DIR__ . '/Constants.php';

/** Require the composer autoloader file */
require_once __DIR__ . '/../../../../autoload.php';

use heidelpayPHP\examples\ExampleDebugHandler;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Heidelpay;
use heidelpayPHP\Resources\Basket;
use heidelpayPHP\Resources\Customer;
use heidelpayPHP\Resources\EmbeddedResources\Address;
use heidelpayPHP\Resources\EmbeddedResources\BasketItem;
use heidelpayPHP\Resources\PaymentTypes\InvoiceFactoring;
use heidelpayPHP\Resources\PaymentTypes\InvoiceGuaranteed;

session_start();
session_unset();

$clientMessage = 'Something went wrong. Please try again later.';
$merchantMessage = 'Something went wrong. Please try again later.';

function redirect($url, $merchantMessage = '', $clientMessage = '')
{
    $_SESSION['merchantMessage'] = $merchantMessage;
    $_SESSION['clientMessage']   = $clientMessage;
    header('Location: ' . $url);
    die();
}

// You will need the id of the payment type created in the frontend (index.php)
if (!isset($_POST['paymentTypeId'], $_POST['customerId'])) {
    redirect(FAILURE_URL, 'Resource id is missing!', $clientMessage);
}
$paymentTypeId   = $_POST['paymentTypeId'];
$customerId  = $_POST['customerId'];

// Catch API errors, write the message to your log and show the ClientMessage to the client.
try {
    // Create a heidelpay object using your private key and register a debug handler if you want to.
    $heidelpay = new Heidelpay(HEIDELPAY_PHP_PAYMENT_API_PRIVATE_KEY);
    $heidelpay->setDebugMode(true)->setDebugHandler(new ExampleDebugHandler());

    $orderId = str_replace(['0.', ' '], '', microtime(false));

    // A Basket is mandatory for SEPA direct debit guaranteed payment type
    $basketItem = new BasketItem('Hat', 10.0, 10.0, 1);
    $basket = new Basket($orderId, 10.0, 'EUR', [$basketItem]);

    $transaction = $heidelpay->charge(12.99, 'EUR', $paymentTypeId, CONTROLLER_URL, $customerId, $orderId, null, $basket);

    // You'll need to remember the shortId to show it on the success or failure page
    $_SESSION['ShortId'] = $transaction->getShortId();

    // Redirect to the success or failure depending on the state of the transaction
    $payment = $transaction->getPayment();
    if ($transaction->isSuccess()) {
        redirect(SUCCESS_URL);
    }

    // Check the result message of the transaction to find out what went wrong.
    $merchantMessage = $transaction->getMessage()->getCustomer();
} catch (HeidelpayApiException $e) {
    $merchantMessage = $e->getMerchantMessage();
    $clientMessage = $e->getClientMessage();
} catch (RuntimeException $e) {
    $merchantMessage = $e->getMessage();
}
redirect(FAILURE_URL, $merchantMessage, $clientMessage);