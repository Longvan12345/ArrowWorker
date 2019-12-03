<?php

namespace ArrowWorker\Component\Cache;


interface Cache
{

    /**
     *
     */
    const LOG_NAME          = 'Cache';

    /**
     * @param array $config
     */
    public function __construct( array $config );

    /**
     * @return bool
     */
    public function InitConnection() : bool;

    /**
     * Db 选择数据库
     * @param int $dbName
     * @return bool
     */
    public function Db(int $dbName) : bool;


    /**
     * Set : write cache
     * @param $key
     * @param $val
     * @return bool
     */
    public function Set(string $key, string $val) : bool;


    /**
     * Get : read cache
     * @param string $key
     * @return string|false
     */
    public function Get(string $key);


}