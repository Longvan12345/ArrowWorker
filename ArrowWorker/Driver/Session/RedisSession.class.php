<?php
/**
 * Project ArrowWorker
 * User:   louis
 * Date:   18-1-1
 */

namespace ArrowWorker\Driver\Session;

/**
 * Class RedisSession
 * @package ArrowWorker\Driver\Session
 */
class RedisSession
{
    /**
     * @var
     */
    private $server;
	private $host = '127.0.0.1';
	private $port = 6379;
	private $auth = '';
	private $timeout = 0;

    /**
     * RedisSession constructor.
     * @param $host
     * @param $port
     * @param $auth
     * @param $timeout
     */
    public function __construct($host, $port, $auth, $timeout)
	{
		$this->host = $host;
		$this->port = $port;
		$this->auth = $auth;
		$this->timeout = $timeout;
		$this->connect();
	}

    /**
     * @return bool
     * @throws \Exception
     */
    public function connect()
	{
        if( !extension_loaded("redis") )
        {
            throw new \Exception('please install redis extension',500);
        }
        $this->server = new \Redis();
        if( !$this->server->connect($this->host, $this->port) )
        {
            throw new \Exception('can not connect session redis',500);
            return false;
        }
        else
        {
            if( !$this->server->auth($this->auth) )
            {
                throw new \Exception('session redis password is not correct',500);
                return false;
            }
        }
        return true;
	}

    /**
     * set
     * @param string $sessionId
     * @param string $key
     * @param string $val
     * @return bool
     */
    public function Hset(string $sessionId, string $key, string $val) : bool
    {
        $isOk = $this->server -> Hset($sessionId, $key, $val);
        if( $isOk === false )
        {
            return false;
        }
        return true;
    }

    /**
     * get specified information in specified session
     * @param string $sessionId
     * @param string $key
     * @return mixed
     */
    public function Get(string $sessionId, string $key)
    {
        return $this->server -> Hget($sessionId, $key);
    }

    /**
     * count the total number in specified session
     * @param string $sessionId
     * @return int
     */
    public function Len(string $sessionId) : int
    {
        return $this->server -> hLen($sessionId);
    }

    /**
     * delete specified key in specified session
     * @param string $sessionId
     * @param string $key
     * @return int
     */
    public function Del(string $sessionId, string $key) : int
    {
        return $this->server -> hDel($sessionId, $key);
    }

    /**
     * get specified session information
     * @param string $sessionId
     * @return array
     */
    public function All(string $sessionId) : array
    {
        return $this->server -> hGetAll( $sessionId );
    }

    /**
     * destory specified session
     * @param string $sessionId
     * @return mixed
     */
    public function Destory(string $sessionId) : int
    {
        return $this->server -> del( $sessionId );
    }


    /**
     * verified if the specified session exists
     * @param string $sessionId
     * @return mixed
     */
    public function Exists(string $sessionId) : bool
    {
        return $this->server -> exitst( $sessionId );
    }


    /**
     * verified if the specified session key exists
     * @param string $sessionId
     * @return mixed
     */
    public function KeyExits(string $sessionId, string $key) : bool
    {
        return $this->server -> hExists( $sessionId, $key);
    }


}

