<?php
/**
 * User: louis
 * Date: 17-10-20
 * Time: 上午12:51
 */

namespace ArrowWorker\Web;

use ArrowWorker\Config;
use ArrowWorker\Console;
use ArrowWorker\Log;
use ArrowWorker\App;

/**
 * Class Router
 * @package ArrowWorker
 */
class Router
{
    const LOG_NAME = 'router';

    /**
     * @var string
     */
    private $_controller = '';

    /**
     * @var array
     */
    private $_restApiConfig = [];

    /**
     * @var array
     */
    private $_pregAlias = [];

    /**
     * @var string
     */
    private $_404 = 'page not found(该页面不存在).';

    /**
     * @param string $_404
     * @return self
     */
    public static function Init( string $_404 ) : self
    {
        return new self( $_404 );
    }

    private function __construct( string $_404 )
    {
        $this->_loadRestConfig();
        $this->_buildRestPattern();
        $this->_init404( $_404 );
        $this->_controller = App::GetController();
    }

    private function _loadRestConfig()
    {
        $config = Config::Get( 'WebRouter' );
        if ( false === $config )
        {
            Log::Warning( "Load rest api configuration failed" );
            return;
        }
        if ( !is_array( $config ) )
        {
            Log::Warning( " rest api configuration format is incorrect." );
            return;
        }

        foreach ( $config as $serverNames => $restMap )
        {
            if ( !is_array( $restMap ) )
            {
                continue;
            }
            $restAlias      = $this->_rebuildRestGroup( $restMap );
            $serverNameList = explode( ',', $serverNames );
            foreach ( $serverNameList as $serverName )
            {
                $this->_restApiConfig[ trim( $serverName ) ] = $restAlias;
            }
        }
    }

    private function _rebuildRestGroup( array $restMap )
    {
        $restAlias = [];
        foreach ( $restMap as $uri => $alias )
        {
            if ( !is_array( $alias ) )
            {
                continue;
            }

            $isGroup = true;
            foreach ( $alias as $method => $function )
            {
                $method = strtoupper( $method );
                if ( in_array( $method, [
                    'GET',
                    'POST',
                    'DELETE',
                    'PUT',
                ] ) )
                {
                    $restAlias[ $uri ][ $method ] = $function;
                    $isGroup                      = false;
                    continue;
                }
            }

            if ( !$isGroup )
            {
                continue;
            }

            $subAlias = $this->_rebuildRestGroup( $alias );
            if ( 0 == count( $subAlias ) )
            {
                continue;
            }

            foreach ( $subAlias as $subUri => $subFunctions )
            {
                $restAlias[ $uri . $subUri ] = $subFunctions;
            }
        }
        return $restAlias;
    }

    /**
     *
     */
    private function _buildRestPattern()
    {
        foreach ( $this->_restApiConfig as $serverName => $restMap )
        {
            foreach ( $restMap as $uri => $alias )
            {
                $nodes    = explode( '/', $uri );
                $match    = preg_replace( [
                    '/:\w+/',
                    '/\//',
                ], [
                    '[a-zA-Z0-9_-]+',
                    '\\/',
                ], $uri );
                $colonPos = strpos( $uri, ':' );
                $key      = ( false === $colonPos ) ? $uri : substr( $uri, 0, $colonPos - 1 );
                $params   = [];
                foreach ( $nodes as $index => $param )
                {
                    if ( false === strpos( $param, ':' ) )
                    {
                        continue;
                    }
                    $params[ $index ] = str_replace( ':', '', $param );
                }
                $this->_pregAlias[ $serverName ][ $key ][ "/^{$match}$/" ] = [
                    'uri'    => $uri,
                    'params' => $params,
                ];
            }
        }

    }

    /**
     * @return string
     */
    public function _getRestUriKey() : string
    {
        $uri        = Request::Uri();
        $nodes      = explode( '/', $uri );
        $nodeLen    = count( $nodes );
        $serverName = Request::Host();

        for ( $i = $nodeLen; $i > 1; $i-- )
        {
            $key = '/' . implode( '/', array_slice( $nodes, 1, $i - 1 ) );
            if ( !isset( $this->_pregAlias[ $serverName ][ $key ] ) )
            {
                continue;
            }

            $nodeMap = $this->_pregAlias[ $serverName ][ $key ];
            foreach ( $nodeMap as $match => $eachNode )
            {
                $isMatched = preg_match( $match, $uri );
                if ( false === $isMatched || $isMatched === 0 )
                {
                    continue;
                }

                $params = [];
                foreach ( $eachNode[ 'params' ] as $index => $param )
                {
                    $params[ $param ] = $nodes[ $index ];
                }
                Request::SetParams( $params, 'REST' );
                return $eachNode[ 'uri' ];
            }
        }
        return '';
    }
    
    public function Go()
    {
        if ( $this->_restRouter() )
        {
            return;
        }

        if ( $this->_pathRouter() )
        {
            return;
        }

        $this->_response( "controller->method not found" );
    }

    /**
     * @return bool
     */
    private function _restRouter()
    {
        $key        = $this->_getRestUriKey();
        $method     = Request::Method();
        $serverName = Request::Host();

        if ( empty( $key ) )
        {
            return false;
        }

        if ( !isset( $this->_restApiConfig[ $serverName ][ $key ][ $method ] ) )
        {
            return false;
        }

        list( $class, $function ) = explode( '@', $this->_restApiConfig[ $serverName ][ $key ][ $method ] );
        $class = $this->_controller . $class;
        return $this->_routeToFunction( $class, $function );
    }

    /**
     * @return bool
     */
    private function _pathRouter()
    {
        $uri      = Request::Uri();
        $pathInfo = explode( '/', $uri );
        $pathLen  = count( $pathInfo );
        Request::SetParams( [], 'PATH' );

        if ( $pathLen < 3 )
        {
            return false;
        }

        if ( $pathLen == 4 && $pathInfo[ 1 ] != '' && $pathInfo[ 2 ] != '' && $pathInfo[ 3 ] != '' )
        {
            $class = $this->_controller . $pathInfo[ 1 ] . '\\' . $pathInfo[ 2 ];
            return $this->_routeToFunction( $class, $pathInfo[ 3 ] );
        }

        if ( $pathLen >= 3 && $pathInfo[ 1 ] != '' && $pathInfo[ 2 ] != '' )
        {
            $class = $this->_controller . $pathInfo[ 1 ];
            return $this->_routeToFunction( $class, $pathInfo[ 2 ] );
        }

        return false;
    }


    /**
     * @param string $class
     * @param string $function
     *
     * @return bool
     */
    private function _routeToFunction( string $class, string $function )
    {
        if ( !class_exists( $class ) )
        {
            return $this->_response( "controller {$class} not found" );
        }

        $controller = new $class;
        if ( !method_exists( $controller, $function ) )
        {
            return $this->_response( "controller method {$class}->{$function} not found" );
        }
        $controller->$function();
        unset( $controller );
        return true;
    }

    /**
     * @param string $msg
     * @return bool
     */
    private function _response( string $msg )
    {
        Response::Status(404);
        if ( !Console::Init()->IsDebug() )
        {
            $msg = $this->_404;
        }
        Response::Write( $msg );
        return true;
    }

    /**
     * @param string $_404
     */
    private function _init404( string $_404 )
    {
        if ( empty( $_404 ) || !file_exists( $_404 ) )
        {
            $this->_404 = file_get_contents( ArrowWorker . '/Static/404.html' );
            return;
        }

        $this->_404 = file_get_contents( $_404 );
    }

}