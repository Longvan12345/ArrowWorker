<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 17-10-10
 * Time: 上午11:19
 */

namespace ArrowWorker;


class Exception
{
    private static $code;
    private static $msg;
    private static $file;
    private static $line;

    static function init()
    {
        //set_error_handler([__CLASS__ , 'error']);
       // set_exception_handler([__CLASS__,'exception']);
    }

    //错误处理
    static function error( $column=null,$msg = null, $file=null, $line=null )
    {
        //ob_clean();
        header("HTTP/1.1 500 Something must be wrong with your program,by ArrowWorker!");
        if( APP_TYPE=='web' && APP_STATUS=='debug' )
        {
            exit("<b>Error:</b><br />File : {$file}<br />Line : {$line}<br />Message : {$msg}<br />");
        }
        else if( APP_TYPE=='web' && APP_STATUS!='debug' )
        {
            exit( json_encode( ['code' => 500, 'msg' => 'something is wrong with the server...'] ) );
        }
        else if( APP_TYPE=='cli')
        {
            exit(PHP_EOL."Error:".PHP_EOL."File:".PHP_EOL."{$file}".PHP_EOL."Line:".PHP_EOL."{$line}".PHP_EOL."Message:".PHP_EOL."{$msg}".PHP_EOL."");
            exit();
        }
    }

    //异常处理
    static function exception($msg = null, $code=null)
    {
       // var_dump($code);
        $exception = (array)$msg;
        //var_dump();
        foreach ($exception as $key => $val)
        {
            //echo "|".json_encode($key)."|";
            switch ($key)
            {
                case " *message":
                    self::$msg = $val;
                    echo json_encode($val);
                    break;
                case '*file':
                    self::$file = $val;
                    break;
                case '*line':
                    self::$line = $val;
                    break;
                case '*code':
                    self::$code = $val;
                    break;
            }
        }
        self::error( self::$code, self::$msg, self::$file, self::$line );
    }

}