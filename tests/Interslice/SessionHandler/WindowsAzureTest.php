<?php
/**
 * Copyright 2013 Thai Phan
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Interslice\SessionHandler;

use Interslice\SessionHandler\WindowsAzure as SessionHandler;

class WindowsAzureTest extends \PHPUnit_Framework_TestCase
{
    /** @var SessionHandler */
    protected $sessionHandler;

    protected function setUp()
    {
        $clickMock = $this->getMockBuilder('WindowsAzure\Table\TableRestProxy')
            ->disableOriginalConstructor()
            ->getMock();

        $this->sessionHandler = new SessionHandler($clickMock);
    }

    public function testClose()
    {
        $this->assertTrue($this->sessionHandler->close());
    }
}
