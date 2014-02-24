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
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Table\Models\EdmType;
use WindowsAzure\Table\Models\Entity;
use WindowsAzure\Table\Models\Filters\Filter;
use WindowsAzure\Table\TableRestProxy;

class WindowsAzure implements \SessionHandlerInterface
{
    private $client;
    private $table;
    private $partitionKey;

    /**
     * Constructor.
     *
     * @param TableRestProxy $client
     * @param string $table
     * @param string $partitionKey
     */
    public function __construct(TableRestProxy $client, $table = 'table', $partitionKey = 'partitionkey')
    {
        $this->client = $client;
        $this->table = $table;
        $this->partitionKey = $partitionKey;
    }

    /**
     * Open the session store.
     *
     * @return bool
     */
    public function open($savePath, $sessionName)
    {
        try {
            $this->client->createTable($this->table);
        } catch (ServiceException $e) {
            switch ($e->getCode()) {
                case 409:
                    break;
                default:
                    return false;
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Close the session store.
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * Retrieve a session by ID.
     *
     * @param string $sessionId
     * @return string
     */
    public function read($sessionId)
    {
        try {
            $result = $this->client->getEntity(
                $this->table,
                $this->partitionKey,
                $sessionId
            );
            $entity = $result->getEntity();
            $data = $entity->getPropertyValue('data');
            return base64_decode($data);
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Create or update a session by ID.
     *
     * @param string $sessionId
     * @param string $sessionData
     */
    public function write($sessionId, $sessionData)
    {
        $entity = new Entity();
        $entity->setPartitionKey($this->partitionKey);
        $entity->setRowKey($sessionId);
        $entity->addProperty('last_accessed', EdmType::INT32, time());
        $entity->addProperty('data', EdmType::STRING, base64_encode($sessionData));
        try {
            $this->client->insertOrReplaceEntity($this->table, $entity);
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * Destroy a session by ID.
     *
     * @param string $sessionId
     * @return bool
     */
    public function destroy($sessionId)
    {
        try {
            $this->client->deleteEntity(
                $this->table,
                $this->partitionKey,
                $sessionId
            );
            return true;
        } catch (ServiceException $e) {
            return false;
        }
    }

    /**
     * Delete sessions that have expired.
     *
     * @param int $maxlifetime
     * @return bool
     */
    public function gc($maxlifetime)
    {
        $deadline = time() - $maxlifetime;
        $queryString = "PartitionKey eq '$this->partitionKey' and last_accessed lt $deadline";
        $filter = Filter::applyQueryString($queryString);
        try {
            $result = $this->client->queryEntities($this->table, $filter);
            $entities = $result->getEntities();
            foreach ($entities as $entity) {
                $this->client->deleteEntity(
                    $this->table,
                    $this->partitionKey,
                    $entity->getRowKey()
                );
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
