<?php
namespace ArrowWorker\Driver\Worker;

class GeneratorSchedule
{
    private static $taskMap = [];
    private static $isExit = false;
    private static $execCount = 0;

    public function newTask( Generator $coroutine )
    {
        self::$taskMap[] = new GeneratorTask( $coroutine );
    }

    public static function run()
    {
        while( 1 )
        {
            if ( self::$isExit )
            {
                break;
            }

            pcntl_signal_dispatch();

           foreach( self::$taskMap as $taskId => $task )
            {
                $return = $task -> run();
                self::$execCount += $return;
                if ( $task->isFinished() )
                {
                    unset( self::$taskMap[$taskId] );
                }
            }
        }

    }

    public function taskCount()
    {
        return self::$execCount;
    }

}
