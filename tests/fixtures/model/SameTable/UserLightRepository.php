<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
 * Copyright (C) 2026 Xavier Leune
 *
 ***********************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you
 * may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 **********************************************************************/


namespace tests\fixtures\model\SameTable;

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;

/**
 * Maps the table "user" of the database "bouh_world", as UserRepository does
 *
 * @extends Repository<UserLight>
 */
class UserLightRepository extends Repository implements MetadataInitializer
{
    public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata
    {
        $metadata = new Metadata($serializerFactory);
        $metadata->setEntity(UserLight::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('user');
        $metadata->addField(['primary' => true, 'fieldName' => 'id', 'columnName' => 'id', 'type' => 'int']);
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'name', 'type' => 'string']);

        return $metadata;
    }
}
