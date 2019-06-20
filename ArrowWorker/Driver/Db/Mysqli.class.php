<?php
/**
 * User: Arrow
 * Date: 2016/8/1
 * Time: 19:51
 */

namespace ArrowWorker\Driver\Db;
use ArrowWorker\Driver\Db AS db;
use ArrowWorker\Log;


/**
 * Class Mysqli
 * @package ArrowWorker\Driver\Db
 */
class Mysqli
{

    //数据库连接池
    protected static $connPool = [];

    protected static $instance;

    protected static $config = [];

    protected static $dbCurrent = null;

    protected function __construct($config)
    {
        //Todo
    }

    public static function GetDb()
    {
        return self::$instance;
    }


    /**
	 * 初始化数据库连接类
	 * @param array $config
	 * @param string $alias
	 * @return Mysqli
	 */
	static function Init(array $config, string $alias)
    {
        //存储配置
        if ( !isset( self::$config[$alias] ) )
        {
            self::$config[$alias] = $config;
        }

        //设置当前
        self::$dbCurrent = $alias;

        if(!self::$instance)
        {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

	/**
	 * @param array $config
	 * @return \mysqli
	 */
	private function _initConnection(array $config)
    {
        //建立连接
        @$conn = new \mysqli($config['host'],$config['userName'],$config['password'],$config['dbName'],$config['port']);
        //捕捉错误
        if($conn->connect_errno)
        {
            Log::DumpExit("connecting to mysql failed : ".$conn->connect_error);
        }
        //初始化字符集
        if( false===$conn->query("set names '".self::$config[self::$dbCurrent]['charset']."'") )
        {
            Log::Warning("mysqi set names(charset) failed.");
        }
        return $conn;
    }

	/**
	 * 连接数据库
	 * @param bool $isMaster
	 * @param int $connectNum
	 * @return \mysqli
	 */
	protected function _getConnection(bool $isMaster=false, int $connectNum=0)
    {
        if( $isMaster==true || self::$config[self::$dbCurrent]['seperate']==0 )
        {
            return $this->_connectMaster();
        }
        return $this->_connectSlave($connectNum);
    }


	/**
	 * 检测并连接主库
     * @return \mysqli
	 */
	private function _connectMaster()
    {
        if( !isset( self::$connPool[self::$dbCurrent]['master'] ) )
        {
            self::$connPool[self::$dbCurrent]['master'] = $this->_initConnection( self::$config[self::$dbCurrent]['master'] );
        }
        return self::$connPool[self::$dbCurrent]['master'];
    }


	/**
	 * 检测并连接从库
	 * @param int $slaveIndex
	 * @return \mysqli
	 */
	private function _connectSlave(int $slaveIndex=0)
    {
        $slaveCount = count(self::$config[self::$dbCurrent]['slave']);
        $slave = ( $slaveIndex==0 || $slaveIndex>=$slaveCount || $slaveIndex<0 ) ? mt_rand( 0, $slaveCount-1 ) : $slaveIndex;

        if ( !isset( self::$connPool[self::$dbCurrent]['slave'][$slave] ) )
        {
            self::$connPool[self::$dbCurrent]['slave'][$slave] = $this->_initConnection(self::$config[self::$dbCurrent]['slave'][$slave]);
        }
        return self::$connPool[self::$dbCurrent]['slave'][$slave];
    }


	/**
	 * 查询
	 * @param string $sql
	 * @param bool $isMaster
	 * @param int $connectNum
	 * @return array|bool
	 */
	public function Query(string $sql, bool $isMaster=false, int $connectNum=0)
    {
        $result = $this->_getConnection($isMaster,$connectNum)->query($sql);
        if($result)
        {
            Log::Info($sql);
            $field  = $this->_parseFieldType($result);
            $return = [];
            while($row = $result->fetch_assoc())
            {
                foreach ($row as $key=>&$val)
                {
                    settype($val, $field[$key]);
                }
                $return[] = $row;
            }
            return $return;
        }
        else
        {
            Log::Error("sql error:{$sql}");
            return false;
        }

    }

    /**
     * @param \mysqli_result $result
     * @return array
     */
    private function _parseFieldType(\mysqli_result $result): array
    {
        $fields = [];
        while ($info = $result->fetch_field()) {
            switch ($info->type) {
                case MYSQLI_TYPE_BIT:
                case MYSQLI_TYPE_TINY:
                case MYSQLI_TYPE_SHORT:
                case MYSQLI_TYPE_LONG:
                case MYSQLI_TYPE_LONGLONG:
                case MYSQLI_TYPE_INT24:
                    $type = 'int';
                    break;
                case MYSQLI_TYPE_FLOAT:
                case MYSQLI_TYPE_DOUBLE:
                case MYSQLI_TYPE_DECIMAL:
                case MYSQLI_TYPE_NEWDECIMAL:
                    $type = 'float';
                    break;
                default:
                    $type = 'string';
            }
            $fields[$info->name] = $type;
        }
        return $fields;
    }


	/**
	 * execute 写入或更新
	 * @param string $sql
	 * @return array
	 */
	public function Execute(string $sql)
    {
        $conn = $this->_getConnection(true);
        return [
            'result'       => $conn->query($sql),
            'affectedRows' => $conn->affected_rows,
            'insertId'     => $conn->insert_id
        ];
    }


	/**
	 * Begin 开始事务
	 */
	public function Begin()
    {
        $conn = $this->_getConnection(true);
        $conn->autocommit(false);
        $conn->begin_transaction();
    }



	/**
	 * Commit 提交事务
	 */
	public function Commit()
    {
        $conn = $this->_getConnection(true);
        $conn->commit();
        $conn->autocommit(true);
    }


	/**
	 * Rollback 事务回滚
	 */
	public function Rollback()
    {
        $conn = $this->_getConnection(true);
        $conn->rollback();
        $conn->autocommit(true);
    }


	/**
	 * Autocommit 是否自动提交
	 * @param bool $flag
	 */
	public function Autocommit(bool $flag)
    {
        $this->_getConnection(true)->autocommit($flag);
    }


}
