<?php
/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

namespace CCMBenchmark\Ting\Repository;

use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Exceptions\IOException;

class MetadataCacheGenerator
{
    protected $cacheDir = '';

    protected $filename = 'ting.php';

    protected $template = '<?php
        /**
         * File generated by Ting - PHP DataMapper
         * MetadataCacheGenerator
         */
        return <array>;
    ';

    /**
     * @param string $cacheDir
     * @param string $filename
     * @throws IOException
     *
     * @internal
     */
    public function __construct($cacheDir, $filename = null)
    {
        $this->cacheDir = $cacheDir;

        if ($filename !== null) {
            $this->filename = $filename;
        }

        if (is_writable($this->cacheDir . '/') === false) {
            throw new IOException('Cache directory for Metadata is not writable: ' . $cacheDir);
        }
        if (touch($this->getFileName()) === false) {
            throw new IOException('Cache file for Metadata is not writable: ' . $this->getFileName());
        }
    }

    /**
     * This method create a cache file containing every given classes.
     * This class names have to be fully qualified and must all extends CCMBenchmark\Ting\Repository\Repository
     *
     * @param array $repositories
     * @return string $filename complete path to cache file
     *
     * @internal
     */
    public function createCache(array $repositories)
    {
        $code = str_replace('<array>', var_export($repositories, true), $this->template);
        file_put_contents($this->getFileName(), $code);

        return $this->getFileName();
    }

    protected function getFileName()
    {
        return $this->cacheDir . '/' . $this->filename;
    }
}
