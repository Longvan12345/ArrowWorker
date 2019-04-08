<?php
/**
 * By yubin at 2019/3/22 9:25 AM.
 */

namespace App\Controller;

use \Swoole\Server as server;
use ArrowWorker\Log;

class Tcp
{

    public static function Connect(server $server, int $fd)
    {
        Log::Info("{$fd} connected .");
    }

    public static function Receive(server $server, int $fd, int $reactor_id, string $data)
    {

    }

    public static function Close(server $server, int $fd)
    {
        Log::Info("{$fd} closed .");
    }

}