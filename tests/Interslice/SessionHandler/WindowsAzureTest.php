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

use Exception;
use Interslice\SessionHandler\WindowsAzure as SessionHandler;
use Mockery;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Table\Models\Entity;
use WindowsAzure\Table\Models\GetEntityResult;
use WindowsAzure\Table\Models\QueryEntitiesResult;

class WindowsAzureTest extends \PHPUnit_Framework_TestCase
{
    private function getMockClient()
    {
        return Mockery::mock('WindowsAzure\Table\TableRestProxy');
    }

    public function testOpen()
    {
        $client = $this->getMockClient();
        $client->shouldReceive('createTable');
        $sessionHandler = new SessionHandler($client);
        $this->assertTrue($sessionHandler->open(null, null));

        $client = $this->getMockClient();
        $client->shouldReceive('createTable')->andThrow(new Exception());
        $sessionHandler = new SessionHandler($client);
        $this->assertFalse($sessionHandler->open(null, null));

        $client = $this->getMockClient();
        $client->shouldReceive('createTable')->andThrow(new ServiceException('403'));
        $sessionHandler = new SessionHandler($client);
        $this->assertFalse($sessionHandler->open(null, null));

        $client = $this->getMockClient();
        $client->shouldReceive('createTable')->andThrow(new ServiceException('409'));
        $sessionHandler = new SessionHandler($client);
        $this->assertTrue($sessionHandler->open(null, null));
    }

    public function testClose()
    {
        $client = $this->getMockClient();
        $sessionHandler = new SessionHandler($client);
        $this->assertTrue($sessionHandler->close());
    }

    public function testRead()
    {
        $entity= new Entity();
        $entity->setRowKey('id');
        $entity->addProperty('data', null, base64_encode('data'));
        $entityResult = new GetEntityResult();
        $entityResult->setEntity($entity);
        $client = $this->getMockClient();
        $client->shouldReceive('getEntity')->andReturn($entityResult);
        $sessionHandler = new SessionHandler($client);
        $this->assertEquals('data', $sessionHandler->read('id'));

        $client = $this->getMockClient();
        $client->shouldReceive('createTable')->andThrow(new ServiceException('404'));
        $sessionHandler = new SessionHandler($client);
        $this->assertEquals('', $sessionHandler->read('id'));
    }

    public function testDestroy()
    {
        $client = $this->getMockClient();
        $client->shouldReceive('deleteEntity');
        $sessionHandler = new SessionHandler($client);
        $this->assertTrue($sessionHandler->destroy('id'));

        $client = $this->getMockClient();
        $client->shouldReceive('deleteEntity')->andThrow(new ServiceException('404'));
        $sessionHandler = new SessionHandler($client);
        $this->assertFalse($sessionHandler->destroy('id'));
    }

    public function testGc()
    {
        $client = $this->getMockClient();
        $client->shouldReceive('queryEntities')->andThrow(new ServiceException('404'));
        $sessionHandler = new SessionHandler($client);
        $this->assertFalse($sessionHandler->gc(0));

        $client = $this->getMockClient();
        $queryEntitiesResult = new QueryEntitiesResult();
        $entity = new Entity();
        $entity->setRowKey('id');
        $entity->addProperty('data', null, base64_encode('data'));
        $queryEntitiesResult->setEntities(array($entity));
        $client->shouldReceive('queryEntities')->andReturn($queryEntitiesResult);
        $client->shouldReceive('deleteEntity');
        $sessionHandler = new SessionHandler($client);
        $this->assertTrue($sessionHandler->gc(0));
    }
}
