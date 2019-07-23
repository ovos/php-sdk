<?php
/**
 * This custom debug handler will echo out debug messages.
 *
 * Copyright (C) 2018 heidelpay GmbH
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
 * @package  heidelpayPHP/test/integration
 */
namespace heidelpayPHP\examples;

use heidelpayPHP\Interfaces\DebugHandlerInterface;

class ExampleDebugHandler implements DebugHandlerInterface
{
    /**
     * {@inheritDoc}
     */
    public function log(string $message)
    {
        // ATTENTION: Uncomment following line to write debug messages to the error log of your web server.
        //error_log($message);
    }
}