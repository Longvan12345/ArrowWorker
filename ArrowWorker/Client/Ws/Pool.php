<?php
/**
 * By yubin at 2019-10-05 11:07.
 */

namespace ArrowWorker\Client\Ws;

use ArrowWorker\Config;
use ArrowWorker\PoolInterface as ConnPool;
use ArrowWorker\Log;
use ArrowWorker\Library\Coroutine;
use ArrowWorker\Library\Channel as SwChan;

class Pool implements ConnPool
{

    const LOG_NAME          = 'WsClient';

    const CONFIG_NAME       = 'WsClient';
    
    const LOG_PREFIX = '[ WsPool  ] ';

    /**
     * @var array
     */
    private static $_pool   = [];

    /**
     * @var array
     */
    private static $_configs = [];

    /**
     * @var array
     */
    private static $_connections = [

    ];

    /**
     * @param  array $appAlias
     * @param array $config
     */
    public static function Init(array $appAlias, array $config=[]) : void
    {
        self::_initConfig($appAlias, $config);
        self::InitPool();
    }

    /**
     * @param array $appAlias specified keys and pool size
     * @param array $config
     */
    private static function _initConfig( array $appAlias, array $config)
    {
        if( count($config)>0 )
        {
            goto INIT;
        }

        $config = Config::Get( self::CONFIG_NAME );
        if ( !is_array( $config ) || count( $config ) == 0 )
        {
            Log::Dump( self::LOG_PREFIX.'incorrect config file' );
            return ;
        }

        INIT:
        foreach ( $config as $index => $value )
        {
            if( !isset($appAlias[$index]) )
            {
                continue ;
            }

            if (
                !isset( $value['host'] ) ||
                !isset( $value['port'] ) ||
                !isset( $value['uri'] ) ||
                !isset( $value['isSsl'])
            )
            {
                Log::Dump( self::LOG_PREFIX."configuration for {$index} is incorrect. config : ".json_encode($value) );
                continue;
            }

            $value['poolSize']     = (int)$appAlias[$index]>0 ? $appAlias[$index] : self::DEFAULT_POOL_SIZE;
            $value['connectedNum'] = 0;

            self::$_configs[$index] = $value;
            self::$_pool[$index]    = SwChan::Init( $value['poolSize'] );
        }
    }


    /**
     * initialize connection pool
     */
    public static function InitPool()
    {
        foreach (self::$_configs as $index=>$config)
        {
            for ($i=$config['connectedNum']; $i<$config['poolSize']; $i++)
            {
                $wsClient = Client::Init( $config['host'], $config['port'], $config['uri'], $config['isSsl'] );
                $upgrade = $wsClient->Upgrade();
                if( false===$upgrade )
                {
                    Log::Dump(self::LOG_PREFIX."initialize connection failed, config : {$index}=>".json_encode($config));
                    continue ;
                }
                self::$_configs[$index]['connectedNum']++;
                self::$_pool[$index]->Push( $wsClient );
            }
        }
    }

    /**
     * @param string $alias
     * @return false|Client
     */
    public static function GetConnection( $alias = 'default' )
    {
        $coId = Coroutine::Id();
        if( isset(self::$_connections[$coId][$alias]) )
        {
            return self::$_connections[$coId][$alias];
        }

        if( !isset(self::$_pool[$alias] ) )
        {
            return false;
        }

        $retryTimes = 0;
        _RETRY:
        $conn = self::$_pool[$alias]->Pop( 0.2 );
        if ( false === $conn )
        {
            if( self::$_configs[$alias]['connectedNum']<self::$_configs[$alias]['poolSize'] )
            {
                self::InitPool();
            }

            if( $retryTimes<=2 )
            {
                $retryTimes++;
                Log::Critical("get ( {$alias} : {$retryTimes} ) connection failed, retrying",self::LOG_NAME);
                goto _RETRY;
            }
        }
        self::$_connections[$coId][$alias] = $conn;
        return $conn;
    }

    /**
     * @return void
     */
    public static function Release() : void
    {
        $coId = Coroutine::Id();
        if( !isset(self::$_connections[$coId]) )
        {
            return ;
        }

        foreach ( self::$_connections[$coId] as $alias=>$connection )
        {
            self::$_pool[$alias]->Push( $connection );
        }
        unset(self::$_connections[$coId], $coId);
    }

}