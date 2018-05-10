<?php
/**
 * User: louis
 * Time: 18-5-10 下午12:38
 */

namespace ArrowWorker;


class Swoole
{
    public static $Http = [
        'port'      => 8888,
        'workerNum' => 4,
        'daemonize' => false,
        'backlog'   => 1000
    ];

    private static function getHttpConfig()
    {
        $config = Config::App("Swoole");
        if( false===$config )
        {
            throw new \Exception('swoole config does not exists');
        }

        if( !isset($config['http']) )
        {
            throw new \Exception('swoole http config does not exists');
        }

        static::$Http = array_merge(static::$Http, $config['http']);
    }

    public static function Http()
    {
        static::getHttpConfig();
        $server = new \swoole_http_server("0.0.0.0", static::$Http['port']);
        $server->set([
            'worker_num' => static::$Http['workerNum'],
            'daemonize'  => static::$Http['daemonize'],
            'backlog'    => static::$Http['backlog'],
        ]);
        $server->on('Request', function($request, $response) {
            Cookie::Init(is_array($request->cookie) ? $request->cookie : [], $response);
            Request::Init(
                is_array($request->get)   ? $request->get : [],
                is_array($request->post) ? $request->post : [],
                is_array($request->server) ? $request->server : [],
                is_array($request->files) ? $request->files : []
            );
            Session::Reset();
            Response::Init($response);
            Router::Start();
        });

        $server->start();
    }
}