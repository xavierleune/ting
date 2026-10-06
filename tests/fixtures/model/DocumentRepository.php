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

namespace tests\fixtures\model;

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Serializer;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;

class DocumentRepository implements MetadataInitializer
{
    public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata
    {
        $metadata = new Metadata($serializerFactory);
        $metadata->setEntity(Document::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('T_DOCUMENT_DOC');
        $metadata->addField([
            'primary'       => true,
            'autoincrement' => true,
            'fieldName'     => 'id',
            'columnName'    => 'doc_id',
            'type'          => 'int',
        ]);
        $metadata->addField(['fieldName' => 'title', 'columnName' => 'doc_title', 'type' => 'string']);
        // Decoded to a \stdClass (no "assoc" option): mutable
        $metadata->addField(['fieldName' => 'payload', 'columnName' => 'doc_payload', 'type' => 'json']);
        // A \DateTime: mutable
        $metadata->addField([
            'fieldName'  => 'publishedAt',
            'columnName' => 'doc_published_at',
            'type'       => 'datetime',
            'serializer' => Serializer\DateTime::class,
        ]);

        return $metadata;
    }
}
