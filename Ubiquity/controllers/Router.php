<?php

namespace Ubiquity\controllers;

use Ubiquity\cache\CacheManager;
use Ubiquity\utils\http\URequest;
use Ubiquity\utils\base\UString;
use Ubiquity\log\Logger;
use Ubiquity\controllers\traits\RouterModifierTrait;
use Ubiquity\controllers\traits\RouterAdminTrait;

/**
 * Router manager
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.3
 */
class Router {
	use RouterModifierTrait,RouterAdminTrait;
	
	protected static $routes;

	/**
	 * Starts the router by loading normal routes (not rest)
	 */
	public static function start() {
		self::$routes = CacheManager::getControllerCache ();
	}

	/**
	 * Starts the router by loading rest routes (not normal routes)
	 */
	public static function startRest() {
		self::$routes = CacheManager::getControllerCache ( true );
	}
	
	/**
	 * Starts the router by loading all routes (normal + rest routes)
	 */
	public static function startAll() {
		self::$routes = array_merge(CacheManager::getControllerCache (),CacheManager::getControllerCache(true));
	}

	/**
	 * Returns the route corresponding to a path
	 * @param string $path
	 * @param boolean $cachedResponse
	 * @return boolean|mixed[]|string
	 */
	public static function getRoute($path, $cachedResponse = true) {
		$path = self::slashPath ( $path );
		if(isset(self::$routes[$path])){
			return self::getRoute_(self::$routes[$path], $path, [$path], $cachedResponse);
		}
		foreach ( self::$routes as $routePath => $routeDetails ) {
			if (preg_match ( "@^" . $routePath . "$@s", $path, $matches )) {
				return self::getRoute_($routeDetails, $routePath, $matches, $cachedResponse);
			}
		}
		Logger::warn("Router", "No route found for {$path}","getRoute");
		return false;
	}
	
	private static function getRoute_(&$routeDetails,$routePath,$matches,$cachedResponse){
		if (! isset ( $routeDetails ["controller"] )) {
			$method = URequest::getMethod ();
			if (isset ( $routeDetails [$method] )){
				$routeDetailsMethod=$routeDetails [$method];
				return self::getRouteUrlParts ( [ "path" => $routePath,"details" => $routeDetailsMethod ], $matches, $routeDetailsMethod ["cache"]??false, $routeDetailsMethod["duration"]??null, $cachedResponse );
			}
		} else{
			return self::getRouteUrlParts ( [ "path" => $routePath,"details" => $routeDetails ], $matches, $routeDetails ["cache"]??false, $routeDetails ["duration"]??null, $cachedResponse );
		}
		return false;
	}

	/**
	 * Returns the generated path from a route
	 *
	 * @param string $name
	 *        	name of the route
	 * @param array $parameters
	 *        	array of the route parameters. default : []
	 * @param boolean $absolute
	 */
	public static function getRouteByName($name, $parameters = [], $absolute = true) {
		foreach ( self::$routes as $routePath => $routeDetails ) {
			if (self::checkRouteName ( $routeDetails, $name )) {
				if (\sizeof ( $parameters ) > 0)
					$routePath = self::_getURL ( $routePath, $parameters );
				if (! $absolute)
					return \ltrim ( $routePath, '/' );
				else
					return $routePath;
			}
		}
		return false;
	}

	/**
	 * Returns the generated path from a route
	 *
	 * @param string $name
	 *        	the route name
	 * @param array $parameters
	 *        	default: []
	 * @param boolean $absolute
	 *        	true if the path is absolute (/ at first)
	 * @return boolean|string|array|mixed the generated path (/path/to/route)
	 */
	public static function path($name, $parameters = [], $absolute = false) {
		return self::getRouteByName ( $name, $parameters, $absolute );
	}

	/**
	 * Returns the generated url from a route
	 *
	 * @param string $name
	 *        	the route name
	 * @param array $parameters
	 *        	default: []
	 * @return string the generated url (http://myApp/path/to/route)
	 */
	public static function url($name, $parameters = []) {
		return URequest::getUrl ( self::getRouteByName ( $name, $parameters, false ) );
	}

	protected static function _getURL($routePath, $params) {
		$result = \preg_replace_callback ( '~\((.*?)\)~', function () use (&$params) {
			return array_shift ( $params );
		}, $routePath );
		if (\sizeof ( $params ) > 0) {
			$result = \rtrim ( $result, '/' ) . '/' . \implode ( '/', $params );
		}
		return $result;
	}

	protected static function checkRouteName($routeDetails, $name) {
		if (! isset ( $routeDetails ["name"] )) {
			foreach ( $routeDetails as $methodRouteDetail ) {
				if (isset ( $methodRouteDetail ["name"] ) && $methodRouteDetail == $name)
					return true;
			}
		}
		return isset ( $routeDetails ["name"] ) && $routeDetails ["name"] == $name;
	}

	public static function getRouteUrlParts($routeArray, $params, $cached = false, $duration = NULL, $cachedResponse = true) {
		\array_shift($params);
		$routeDetails=$routeArray ["details"];
		$result = [ str_replace ( "\\\\", "\\", $routeDetails["controller"] ),$routeDetails["action"] ];
		if(($paramsOrder = $routeDetails["parameters"]) && (sizeof($paramsOrder)>0)){
			self::setParamsInOrder($result, $paramsOrder, $params);
		}
		if(!$cached || !$cachedResponse){
			Logger::info('Router', sprintf('Route found for %s : %s',$routeArray["path"],implode("/", $result)),'getRouteUrlParts');
			return $result;
		}
		Logger::info('Router', sprintf('Route found for %s (from cache) : %s',$routeArray["path"],implode("/", $result)),'getRouteUrlParts');
		return CacheManager::getRouteCache ( $result, $duration );
	}
	
	protected static function setParamsInOrder(&$routeUrlParts,$paramsOrder,$params){
		$index = 0;
		foreach ( $paramsOrder as $order ) {
			if ($order === '*') {
				if (isset ( $params [$index] ))
					$routeUrlParts = \array_merge ( $routeUrlParts, \array_diff ( \explode ( "/", $params [$index] ), [ "" ] ) );
					break;
			}
			if ($order[0] === '~') {
				$order = \intval ( \substr ( $order, 1, 1 ) );
				if (isset ( $params [$order] )) {
					$routeUrlParts = \array_merge ( $routeUrlParts, \array_diff ( \explode ( "/", $params [$order] ), [ "" ] ) );
					break;
				}
			}
			$routeUrlParts [] = self::cleanParam ( $params [$order] );
			unset ( $params [$order] );
			$index ++;
		}
	}

	private static function cleanParam($param) {
		if (UString::endswith ( $param, "/" ))
			return \substr ( $param, 0, - 1 );
		return $param;
	}
	
	protected static function slashPath($path) {
		if (UString::startswith ( $path, "/" ) === false)
			$path = "/" . $path;
		if (! UString::endswith ( $path, "/" ))
			$path = $path . "/";
		return $path;
	}
	
	/**
	 * Declare a route as expired or not
	 *
	 * @param string $routePath
	 * @param boolean $expired
	 */
	public static function setExpired($routePath, $expired = true) {
		CacheManager::setExpired ( $routePath, $expired );
	}
}
