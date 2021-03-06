<?php

/**
 * User: Arrow
 * Date: 2017/9/22
 * Time: 12:51
 */

namespace ArrowWorker\Driver\Channel;

use ArrowWorker\Driver\Channel;


/**
 * Class Pipe  管道类
 * @package ArrowWorker\Driver\Channel
 */
class Queue extends Channel
{

    /**
     * 打开方式
     */
    const mode = 0666;

    /**
     * Pipe constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
       //todo
    }

    /**
     * Init 初始化 对外提供
     * @author Louis
     * @param array $config
     * @param string $alias
     * @return Queue
     */
    public static function Init(array $config, string $alias)
    {
        //设置当前
        self::$current = $alias;

        //如果已经创建并做了相关检测则直接跳过
        if( isset( static::$pool[self::$current] ) )
        {
            return self::$instance;
        }

        //存储配置
        if ( !isset( self::$config[$alias] ) )
        {
            self::$config[$alias] = $config;
        }

        if( !self::$instance )
        {
            self::$instance = new self($config);
        }
        //初始化（创建队列等）
        static::_initQueue();

        return static::$instance;
    }


    /**
     * _initHandle  创建管道文件
     * @author Louis
     * @throws \Exception
     */
    private static function _initQueue()
    {

        $channelFile = static::$channelFileDir.self::$current.'.chan';
		if (!file_exists($channelFile))
		{
		    if( !touch($channelFile) )
            {
                throw new \Exception("create queue file : {$channelFile} error.");
            }
		}
		$key = ftok($channelFile, 'A');
        static::$pool[self::$current] = msg_get_queue($key, static::mode);
        msg_set_queue(static::$pool[self::$current],['msg_qbytes'=>self::$config[self::$current]['bufSize']]);
    }

    /**
     * _getQueue 获取队列
     * @author Louis
     * @param string $alias
     * @return mixed
     */
    private function _getQueue(string $alias='')
	{
	    if( !empty($alias) && isset(static::$pool[$alias]) )
        {
            return static::$pool[$alias];
        }
		return static::$pool[self::$current];
	}

    /**
     * Write  写入消息
     * @author Louis
     * @param string $message 要写入的消息
     * @param string $chan channel name
     * @param int $msgType 消息类型
     * @return bool
     */
    public function Write( string $message, string $chan='', int $msgType=1 )
    {
        return msg_send( static::_getQueue($chan), $msgType, (string)$message,true, true, $errorCode);
	}

    /**
     * Status  获取队列状态
     * @author Louis
     * @param string $alias 队列配置名
     * @return array
     */
    public function Status( string $alias='' )
    {
        return msg_stat_queue( static::_getQueue($alias) );
    }

    /**
     * Read 写消息
     * @author Louis
     * @param int $waitSecond seconds to wait while there is no message in channel
     * @param string $chan channel name to read from
     * @param int $msgType message type to be read
     * @return bool|string
     */
    public function Read(int $waitSecond=500, string $chan='', int $msgType=1)
    {
		$result = msg_receive(
		    static::_getQueue($chan),
            $msgType,
            $messageType,
            self::$config[self::$current]['msgSize'],
            $message,
            true,
            MSG_IPC_NOWAIT,
            $errorCode
        );
    	if( !$result && MSG_ENOMSG==$errorCode )
        {
            usleep($waitSecond);
        }
		return $result ? $message : $result;
    }

    /**
     * Close 关闭管道
     * @author Louis
     */
    public static function Close()
    {
        foreach (static::$pool as $eachQueue)
        {
            msg_remove_queue($eachQueue);
        }
    }

    /**
     *__destruct
     */
    public function __destruct()
    {
		//$this->Close();
    }

}

