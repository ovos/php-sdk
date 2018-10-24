<?php
/**
 * Description
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/
 *
 * @author  Simon Gabriel <development@heidelpay.de>
 *
 * @package  heidelpay/${Package}
 */

use heidelpay\MgwPhpSdk\Constants\Currencies;
use heidelpay\MgwPhpSdk\Exceptions\HeidelpayApiException;
use heidelpay\MgwPhpSdk\Exceptions\HeidelpaySdkException;
use heidelpay\MgwPhpSdk\Heidelpay;

//#######   Checks whether examples are enabled. #######################################################################
require_once __DIR__ . '/CardConstants.php';

/**
 * Require the composer autoloader file
 */
require_once __DIR__ . '/../../../autoload.php';


if (!isset($_POST['paymentTypeId'])) {
    throw new RuntimeException('PaymentType id is missing!');
}
if (!isset($_POST['transaction'])) {
    throw new RuntimeException('Transaction is missing!');
}

$paymentTypeId   = $_POST['paymentTypeId'];
$transactionType = $_POST['transaction'];
$transaction = null;

switch ($transactionType) {
    case 'authorization':
        authorize();
        break;
    case 'charge':
        charge();
        break;
    default:
        throw new RuntimeException('Transaction type ' . $transactionType . ' is unknown!');
        break;
}

echo 'Success: ' . $transactionType . ' has been created for Payment #' . $transaction->getPaymentId();


function authorize()
{
    try {
        $heidelpay = new Heidelpay( PRIVATE_KEY );
        $GLOBALS['transaction'] = $heidelpay->authorize(100.0, Currencies::EURO, $GLOBALS['paymentTypeId'], CONTROLLER_URL);
    } catch (RuntimeException $e) {
        // log $e->getMessage();
        echo 'Error: An unexpected error occurred';
        die;
    } catch (HeidelpayApiException $e) {
        // log $e->getMessage();
        echo 'Error: ' . $e->getMessage();
        echo 'Error: ' . $e->getClientMessage();
        die;
    } catch (HeidelpaySdkException $e) {
        // log $e->getMessage();
        echo 'Error: ' . $e->getMessage();
        echo 'Error: ' . $e->getClientMessage();
        die;
    }
}


function charge()
{
    try {
        $heidelpay = new Heidelpay( PRIVATE_KEY );
        $GLOBALS['transaction'] = $heidelpay->charge(100.0, Currencies::EURO, $GLOBALS['paymentTypeId'], CONTROLLER_URL);
    } catch (RuntimeException $e) {
        // log $e->getMessage();
        echo 'Error: An unexpected error occurred';
        die;
    } catch (HeidelpayApiException $e) {
        // log $e->getMessage();
        echo 'Error: ' . $e->getMessage();
        echo 'Error: ' . $e->getClientMessage();
        die;
    } catch (HeidelpaySdkException $e) {
        // log $e->getMessage();
        echo 'Error: ' . $e->getMessage();
        echo 'Error: ' . $e->getClientMessage();
        die;
    }
}
