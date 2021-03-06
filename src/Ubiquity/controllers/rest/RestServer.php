<?php

namespace Ubiquity\controllers\rest;

use Ubiquity\controllers\Startup;
use Ubiquity\cache\ClassUtils;
use Ubiquity\cache\CacheManager;
use Ubiquity\exceptions\RestException;

/**
 *
 * @author jc
 *
 */
class RestServer {
	/**
	 *
	 * @var array
	 */
	protected $config;
	protected $headers;
	protected $tokensFolder;
	protected $tokensCacheKey="_apiTokens";
	/**
	 *
	 * @var ApiTokens
	 */
	protected $apiTokens;

	public function __construct(&$config) {
		$this->config=$config;
		$this->headers=[ 'Access-Control-Allow-Origin' => 'http://127.0.0.1:4200','Access-Control-Allow-Credentials' => 'true','Access-Control-Max-Age' => '86400','Access-Control-Allow-Methods' => 'GET, POST, OPTIONS, PUT, DELETE, PATCH, HEAD' ];
	}

	public function connect(RestController $controller) {
		if (!isset($this->apiTokens)) {
			$this->apiTokens=$this->_getApiTokens();
		}
		$token=$this->apiTokens->addToken();
		$this->_addHeaderToken($token);
		echo $controller->_format([ "access_token" => $token,"token_type" => "Bearer","expires_in" => $this->apiTokens->getDuration() ]);
	}

	/**
	 * Check if token is valid
	 * @return boolean
	 */
	public function isValid() {
		$this->apiTokens=$this->_getApiTokens();
		$key=$this->_getHeaderToken();
		if ($this->apiTokens->isExpired($key)) {
			return false;
		} else {
			$this->_addHeaderToken($key);
			return true;
		}
	}

	public function _getHeaderToken() {
		$authHeader=$this->_getHeader("Authorization");
		if ($authHeader !== false) {
			list ( $type, $data )=explode(" ", $authHeader, 2);
			if (\strcasecmp($type, "Bearer") == 0) {
				return $data;
			} else {
				throw new RestException("Bearer is required in authorization header.");
			}
		} else {
			throw new RestException("The header Authorization is required in http headers.");
		}
	}

	public function finalizeTokens() {
		if (isset($this->apiTokens)) {
			$this->apiTokens->removeExpireds();
			$this->apiTokens->storeToCache();
		}
	}

	public function _getHeader($header) {
		$headers=getallheaders();
		if (isset($headers[$header])) {
			return $headers[$header];
		}
		return false;
	}

	public function _addHeaderToken($token) {
		$this->_header("Authorization", "Bearer " . $token);
	}

	/**
	 * To override for defining another ApiToken type
	 * @return ApiTokens
	 */
	public function _getApiTokens() {
		return ApiTokens::getFromCache(CacheManager::getAbsoluteCacheDirectory(). \DS, $this->tokensCacheKey);
	}

	/**
	 *
	 * @param string $headerField
	 * @param string $value
	 * @param boolean $replace
	 */
	public function _header($headerField, $value=null, $replace=null) {
		if (!isset($value)) {
			if (isset($this->headers[$headerField])) {
				$value=$this->headers[$headerField];
			} else
				return;
		}
		\header(trim($headerField) . ": " . trim($value), $replace);
	}

	/**
	 *
	 * @param string $contentType default application/json
	 * @param string $charset default utf8
	 */
	public function _setContentType($contentType, $charset=null) {
		$value=$contentType;
		if (isset($charset))
			$value.="; charset=" . $charset;
		$this->_header("Content-type", $value);
	}

	public function cors() {
		$this->_header('Access-Control-Allow-Origin');
		$this->_header('Access-Control-Allow-Credentials');
		$this->_header('Access-Control-Max-Age');
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
				$this->_header('Access-Control-Allow-Methods');

			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
				$this->_header('Access-Control-Allow-Headers', $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
			} else {
				$this->_header('Access-Control-Allow-Headers', '*');
			}
			throw new RestException("cors exit normally");
		}
	}

	public static function getRestNamespace() {
		$config=Startup::getConfig();
		$controllerNS=$config["mvcNS"]["controllers"];
		$restNS="";
		if (isset($config["mvcNS"]["rest"])) {
			$restNS=$config["mvcNS"]["rest"];
		}
		return ClassUtils::getNamespaceFromParts([ $controllerNS,$restNS ]);
	}
}
