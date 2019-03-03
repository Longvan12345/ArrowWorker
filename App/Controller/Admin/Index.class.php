<?php
/**
 * By yubin at 2019/2/18 11:11 AM.
 */

namespace App\Controller\Admin;

use ArrowWorker\Web\Response;
use ArrowWorker\Lib\Client\WebSocket;

class Index
{
    public function index()
    {

        Response::Write(mt_rand(0,10000));
    }

    public function get()
    {
        $cli = WebSocket::Connect('127.0.0.1',9503,'/');
        $cli->Push(mt_rand(1,1000).'_from http');
        Response::Write('rest get');
    }

    public function put()
    {
        Response::Write('rest put');
    }

    public function post()
    {
        Response::Write('rest post');
    }
}