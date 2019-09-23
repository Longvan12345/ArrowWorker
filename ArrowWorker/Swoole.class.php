<?php
/**
 * User: louis
 * Time: 18-5-10 下午12:38
 */

namespace ArrowWorker;

use \Swoole\Coroutine as Co;
use \Swoole\Http\Server as Http;
use \Swoole\Server as SocketServer;
use \Swoole\Server;
use \Swoole\WebSocket\Server as WebSocket;
use \Swoole\WebSocket\Frame;
use \Swoole\Http\Request as SwRequest;
use \Swoole\Http\Response as SwResponse;

use \ArrowWorker\Web\Router;
use \ArrowWorker\Web\Response;
use \ArrowWorker\Web\Request;
use \ArrowWorker\Web\Session;
use \ArrowWorker\Web\Cookie;


/**
 * Class Swoole
 * @package ArrowWorker
 */
class Swoole
{
    /**
     *
     */
    const WEB_SERVER        = 1;

    /**
     *
     */
    const WEB_SOCKET_SERVER = 2;

    /**
     *
     */
    const TCP_SERVER = 3;

    /**
     *
     */
    const UDP_SERVER = 4;

    /**
     *
     */
    const CONTROLLER_NAMESPACE = '\\' . APP_DIR . '\\' . APP_CONTROLLER_DIR . '\\';

    /**
     * @var array
     */
    public static $defaultHttpConfig = [
        'host'      => '0.0.0.0',
        'port'      => 8888,
        'workerNum' => 4,
        'backlog'   => 1000,
        'user'      => 'root',
        'group'     => 'root',
        'pipeBufferSize'   => 1024*1024*100,
        'socketBufferSize' => 1024*1024*100,
        'maxRequest'       => 20000,
        'reactorNum'       => 4,
        'maxContentLength' => 2088960,
        'maxCoroutine'     => 10000,
        'enableCoroutine'  => true,
        'enableStaticHandler' => false,
        'isAllowCORS'      => true,
        'sslCertFile'      => '',
        'sslKeyFile'       => '',
        'documentRoot'     => '',
        'mode'             => SWOOLE_PROCESS

    ];

    /**
     * @var array
     */
    public static $defaultWebSocketConfig = [
        'host'      => '0.0.0.0',
        'port'      => 8888,
        'workerNum' => 4,
        'backlog'   => 1000,
        'user'      => 'root',
        'group'     => 'root',
        'pipeBufferSize'   => 1024*1024*100,
        'socketBufferSize' => 1024*1024*100,
        'enableCoroutine'  => true,
        'isAllowCORS'      => true,
        'maxRequest'       => 20000,
        'reactorNum'       => 4,
        'maxContentLength' => 2088960,
        'maxCoroutine'     => 10000,
        'mode'             => SWOOLE_PROCESS
    ];

    /**
     * @var array
     */
    public static $defaultTcpConfig = [
        'host'             => '0.0.0.0',
        'port'             => 8888,
        'workerNum'        => 4,
        'backlog'          => 1000,
        'user'             => 'root',
        'group'            => 'root',
        'pipeBufferSize'   => 1024*1024*100,
        'socketBufferSize' => 1024*1024*100,
        'enableCoroutine'  => true,
        'maxRequest'       => 20000,
        'reactorNum'       => 4,
        'maxContentLength' => 2088960,
        'maxCoroutine'     => 10000,
        'heartbeatCheckInterval' => 30,
        'heartbeatIdleTime' => 60,
        'openEofCheck'      => false,
        'packageEof'        => '\r\n',
        'mode'              => SWOOLE_PROCESS
    ];

    /**
     * @var array
     */
    public static $defaultUdpConfig = [
        'port'      => 8888,
        'workerNum' => 4,
        'backlog'   => 1000,
        'user'      => 'root',
        'group'     => 'root',
        'pipeBufferSize'   => 1024*1024*100,
        'socketBufferSize' => 1024*1024*100,
        'enableCoroutine'  => true,
        'maxRequest'       => 20000,
        'reactorNum'       => 4,
        'maxContentLength' => 2088960,
        'maxCoroutine'     => 10000,
        'mode'             => SWOOLE_PROCESS
    ];

    /**
     * @param int   $type
     * @param array $config
     * @return array
     */
    private static function _getConfig( int $type, array $config) : array
    {
        switch ($type)
        {
            case static::WEB_SERVER:
                $defaultConfig = self::$defaultHttpConfig;
                break;
            case static::WEB_SOCKET_SERVER:
                $defaultConfig = self::$defaultWebSocketConfig;
                break;
            case static::TCP_SERVER:
                $defaultConfig = self::$defaultTcpConfig;
                break;
            default:
                $defaultConfig = self::$defaultUdpConfig;
        }
        $config = array_merge($defaultConfig, $config);
        $serverConfig = [
            'port'       => $config['port'],
            'worker_num' => $config['workerNum'],
            'daemonize'  => false,
            'backlog'    => $config['backlog'],
            'user'       => $config['user'],
            'group'      => $config['group'],
            'package_max_length'    => $config['maxContentLength'],
            'enable_static_handler' => $config['enableStaticHandler'],
            'reactor_num'        => $config['reactorNum'],
            'pipe_buffer_size'   => $config['pipeBufferSize'],
            'socket_buffer_size' => $config['socketBufferSize'],
            'max_request'        => $config['maxRequest'],
            'enable_coroutine'   => $config['enableCoroutine'],
            'max_coroutine'      => $config['maxCoroutine'],
            'document_root'      => $config['documentRoot'],
            'log_file'           => Log::$StdoutFile,
            'handler'            => $config['handler'],
            'ssl_cert_file'      => $config['sslCertFile'],
            'ssl_key_file'       => $config['sslKeyFile'],
            'mode'               => $config['mode'],
            'components'         => isset($config['components']) ? $config['components'] : [],
            'isAllowCORS'        => isset($config['isAllowCORS']) ? (bool)$config['isAllowCORS'] : false
        ];

        if( $type==static::TCP_SERVER )
        {
            $serverConfig['heartbeat_check_interval'] = $config['heartbeatCheckInterval'];
            $serverConfig['heartbeat_idle_time']      = $config['heartbeatIdleTime'];

            $serverConfig['open_eof_check']           = $config['openEofCheck'];
            $serverConfig['package_eof']              = $config['packageEof'];
            $serverConfig['open_eof_split']           = $config['openEofSplit'];

        }

        return $serverConfig;
    }

    /**
     * @param array $config
     */
    public static function StartHttpServer( array $config)
    {
        Router::Init(isset($config['404']) ? (string)$config['404'] : '');
        $config = static::_getConfig(static::WEB_SERVER, $config);
        $cors   = $config['isAllowCORS'];
        $server = new Http($config['host'], $config['port'], $config['mode'],  empty($config['ssl_cert_file']) ? SWOOLE_SOCK_TCP : SWOOLE_SOCK_TCP| SWOOLE_SSL);
        $server->set($config);
        $server->on('start', function($server) use ($config) {
            Log::Dump("Http server is listening at port : ".$config['port']);
        });
        $server->on('WorkerStart', function() use ($config) {
            self::_initComponents( $config );
        });
        $server->on('request', function(SwRequest $request, SwResponse $response) use ($cors) {
            Log::SetLogId();
            Response::Init($response, $cors);
            Request::Init(
                is_array($request->get)   ? $request->get : [],
                is_array($request->post) ? $request->post : [],
                is_array($request->server) ? $request->server : [],
                is_array($request->files) ? $request->files : [],
                is_array($request->header) ? $request->header : [],
                $request->rawContent()
            );
            Session::Reset();
            Cookie::Init(is_array($request->cookie) ? $request->cookie : []);
            Router::Exec();

            Component::Release();
        });

        $server->start();
    }

    /**
     * @param array $config
     */
    public static function StartWebSocketServer( array $config)
    {
        Router::Init( isset($config['404']) ? (string)$config['404'] : '' );
        $config = static::_getConfig(static::WEB_SOCKET_SERVER, $config);
        $cors   = $config['isAllowCORS'];

        $server = new WebSocket($config['host'], $config['port'],  $config['mode'], empty($config['ssl_cert_file']) ? SWOOLE_SOCK_TCP : SWOOLE_SOCK_TCP| SWOOLE_SSL);
        $server->set($config);
        $server->on('start', function($server) use ( $config ) {
            Log::Dump("Websocket server, port : ".$config['port']);
        });
        $server->on('WorkerStart', function() use ( $config ) {
            self::_initComponents($config);
        });
        $server->on('open', function(WebSocket $server, SwRequest $request) use ($config) {
            $function = static::CONTROLLER_NAMESPACE.$config['handler']['open'];
            Log::SetLogId();
            Request::Init(
                is_array($request->get)   ? $request->get : [],
                is_array($request->post) ? $request->post : [],
                is_array($request->server) ? $request->server : [],
                is_array($request->files) ? $request->files : [],
                is_array($request->header) ? $request->header : [],
                $request->rawContent()
            );
            $function($server, $request->fd);
            Component::Release(2);
        });
        $server->on('message', function(WebSocket $server, Frame $frame) use ( $config ) {
            Log::SetLogId();
            $function = static::CONTROLLER_NAMESPACE.$config['handler']['message'];
            $function($server, $frame);
            Component::Release(2);
        });
        $server->on('request', function( SwRequest $request, SwResponse $response ) use ($cors) {
            Log::SetLogId();
            Response::Init($response, $cors);
            Request::Init(
                is_array($request->get)   ? $request->get : [],
                is_array($request->post) ? $request->post : [],
                is_array($request->server) ? $request->server : [],
                is_array($request->files) ? $request->files : [],
                is_array($request->header) ? $request->header : [],
                $request->rawContent()
            );
            Cookie::Init(is_array($request->cookie) ? $request->cookie : []);
            Session::Reset();
            Router::Exec();
            Component::Release();
        });
        $server->on('close',   function(WebSocket $server, int $fd) use ($config) {
            Log::SetLogId();
            $function = static::CONTROLLER_NAMESPACE.$config['handler']['close'];
            $function($server, $fd);
            Component::Release(2);
        });
        $server->start();
    }

    /**
     * @param array $config
     */
    public static function StartTcpServer( array $config)
    {
        $config = static::_getConfig(static::TCP_SERVER, $config);
        $server = new SocketServer($config['host'], $config['port'], $config['mode'], SWOOLE_SOCK_TCP);
        $server->set($config);
        $server->on('WorkerStart', function() use ($config) {
            self::_initComponents($config);
        });
        $server->on('connect', function(SocketServer $server, int $fd) use ( $config ) {
            $function = static::CONTROLLER_NAMESPACE.$config['handler']['connect'];
            Log::SetLogId();
            $function($server, $fd);
            Component::Release(2);
        });
        $server->on('receive', function(SocketServer $server, int $fd, int $reactor_id, string $data) use ($config) {
            $function = static::CONTROLLER_NAMESPACE.$config['handler']['receive'];
            Log::SetLogId();
            $function($server, $fd, $data);
            Component::Release(2);
        });
        $server->on('close',   function(SocketServer $server, int $fd) use ($config) {
            $function = static::CONTROLLER_NAMESPACE.$config['handler']['close'];
            Log::SetLogId();
            $function($server, $fd);
            Component::Release(2);
        });
        $server->start();
    }

    /**
     * @param array $config
     */
    public static function StartUdpServer( array $config)
    {
        $config = static::_getConfig(static::UDP_SERVER, $config);
        $server = new SocketServer($config['host'], $config['port'], $config['mode'], SWOOLE_SOCK_UDP);
        $server->set($config);
        $server->on('WorkerStart', function() use ($config) {
            self::_initComponents($config);
        });
        $server->on('connect', function(SocketServer $server, int $fd) use ( $config ) {
            $function = static::CONTROLLER_NAMESPACE.$config['handler']['connect'];
            Log::SetLogId();
            $function($server, $fd);
            Log::Release();
            Component::Release(2);
        });
        $server->on('receive', function(SocketServer $server, int $fd, int $reactor_id, string $data) use ($config) {
            $function = static::CONTROLLER_NAMESPACE.$config['handler']['receive'];
            Log::SetLogId();
            $function($server, $fd, $data);
            Component::Release(2);
        });
        $server->on('close',   function(SocketServer $server, int $fd) use ($config) {
            $function = static::CONTROLLER_NAMESPACE.$config['handler']['close'];
            Log::SetLogId();
            $function($server, $fd);
            Component::Release(2);
        });
        $server->start();
    }

    /**
     * get swoole coroutine id
     * @return int
     */
    public static function GetCid() : int
    {
        return (int)Co::getuid();
    }

    /**
     * @var array $config
     */
    private static function _initComponents(array $config)
    {
        if(
            !isset($config['components']) ||
            !is_array($config['components'])
        )
        {
            return ;
        }
        Log::SetLogId();
        Component::Init($config['components']);
    }

}