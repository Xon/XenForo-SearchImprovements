<?php

class XenES_Api
{
    public static $hookObject = null;
    public static $hookArgs = null;

	protected static $_instance = null;

	protected $_server = '';

	protected $_indexName = '';

	protected static $_isSingleType = null;
	protected static $_singleTypeName = 'xf';

	protected static $_version;

	/**
	 * @var Zend_Http_Client
	 */
	protected $_httpClient = null;

	/**
	 * Private, use statically.
	 */
	protected function __construct($server = null)
	{
		if ($server === null)
		{
			$esServerOption = XenForo_Application::get('options')->elasticSearchServer;
			if ($esServerOption)
			{
				$server = "http://$esServerOption[host]:$esServerOption[port]/";
			}
			else
			{
				$server = 'http://127.0.0.1:9200/';
			}
		}

		$this->_server = $server;
		$this->_httpClient = XenForo_Helper_Http::getClient($this->_server, array('keepalive' => true, 'timeout' => 45));

		$this->_indexName = strtolower(XenForo_Application::get('options')->elasticSearchIndex);
		if (!$this->_indexName)
		{
			$this->_indexName = strtolower(XenForo_Application::get('config')->db->dbname);
		}
	}

	/**
	 * Singleton stuff
	 *
	 * @return XenES_Api
	 */
	public static function getInstance()
	{
		if (self::$_instance === null)
		{
			self::$_instance = new self();
			self::$_version = null;
		}

		return self::$_instance;
	}

	/**
	 * Gets the index stats
	 *
	 * @param $indexName
	 * @return array|false
	 */
	public static function stats($indexName)
	{
		$result = self::getInstance()->call(Zend_Http_Client::GET,
			sprintf('%s/_stats', $indexName)
		);
		if ($result && isset($result->indices->{$indexName}->total))
		{
			return json_decode(json_encode($result->indices->{$indexName}->total), true);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Index a single data item into Elastic Search
	 *
	 * @param string $indexName
	 * @param string $contentType
	 * @param string $contentId
	 * @param array $contentData
	 *
	 * @return array
	 */
	public static function index($indexName, $contentType, $contentId, array $contentData)
	{
		self::addTypeToDataForSingleTypeIndex($contentType, $contentData);

		return self::getInstance()->call(Zend_Http_Client::PUT,
			self::getIdUrl($indexName, $contentType, $contentId),
			json_encode($contentData)
		);
	}

	/**
	 * Index multiple data items into Elastic Search
	 *
	 * @param string $indexName
	 * @param string $contentType
	 * @param array $contentData [$contentId => $content, ... ]
	 *
	 * @return array
	 */
	public static function indexBulk($indexName, $contentType, array $contentData)
	{
		$items = array();

		foreach ($contentData AS $contentId => $content)
		{
			$items[] = self::getBulkActionEntry('index', $indexName, $contentType, $contentId, $content);
		}

		return self::getInstance()->call(Zend_Http_Client::POST,
			'_bulk',
			implode("\n", $items) . "\n"
		);
	}

	/**
	 * Get a single data item from Elastic Search
	 *
	 * @param string $indexName
	 * @param string $contentType
	 * @param string $contentId
	 *
	 * @return array
	 */
	public static function get($indexName, $contentType, $contentId)
	{
		return self::getInstance()->call(Zend_Http_Client::GET,
			self::getIdUrl($indexName, $contentType, $contentId)
		);
	}

	/**
	 * Delete a single data item from Elastic Search
	 *
	 * @param string $indexName
	 * @param string $contentType
	 * @param string $contentId
	 *
	 * @return array
	 */
	public static function delete($indexName, $contentType, $contentId)
	{
		return self::getInstance()->call(Zend_Http_Client::DELETE,
			self::getIdUrl($indexName, $contentType, $contentId)
		);
	}

	/**
	 * Deletes multiple data items from Elastic Search
	 *
	 * @param string $indexName
	 * @param string $contentType
	 * @param array $contentIds
	 *
	 * @return array
	 */
	public static function deleteBulk($indexName, $contentType, $contentIds)
	{
		$deletes = array();

		foreach ($contentIds AS $contentId)
		{
			$deletes[] = self::getBulkActionEntry('delete', $indexName, $contentType, $contentId);
		}

		return self::getInstance()->call(Zend_Http_Client::POST,
			'_bulk',
			implode("\n", $deletes)
		);
	}

	/**
	 * Performs a search against Elastic Search
	 *
	 * @param string $indexName
	 * @param array $dsl
	 *
	 * @return array
	 */
	public static function search($indexName, array $dsl)
	{
		if (self::$hookObject)
		{
			self::$hookObject->searchHook($indexName, $dsl, self::$hookArgs);
		}
		$options = XenForo_Application::getOptions();
		$json = json_encode($dsl);
		if ($options->esLogDSL)
		{
			XenForo_Error::debug($json);
		}
		$response = self::getInstance()->call(Zend_Http_Client::POST,
			sprintf('%s/_search', $indexName),
			$json
		);

		if ($options->esLogDSLOnError && $response && !empty($response->error))
		{
			if (is_string($response->error))
			{
				$response->error = $response->error . "\n DSL:" . $json;
			}
			else
			{
				$response->error = json_encode($response->error) . "\n DSL:" . $json;
			}
		}
		return $response;
	}

	public static function version()
	{
		if (self::$_version !== null)
		{
			return self::$_version;
		}

		$result = self::getInstance()->call(Zend_Http_Client::GET, '/');
		if ($result && !empty($result->version->number))
		{
			$version = $result->version->number;
		}
		else
		{
			$version = false;
		}

		self::$_version = $version;
		return $version;
	}

	/**
	 * Updates settings against an ES index.
	 *
	 * @param string $indexName
	 * @param array $dsl
	 *
	 * @return array
	 */
	public static function updateSettings($indexName, array $dsl)
	{
		return self::getInstance()->call(Zend_Http_Client::PUT,
			sprintf('%s/_settings', $indexName),
			json_encode($dsl)
		);
	}

	/**
	 * Gets settings for an ES index.
	 *
	 * @param string $indexName
	 *
	 * @return array
	 */
	public static function getSettings($indexName)
	{
		return self::getInstance()->call(Zend_Http_Client::GET,
			sprintf('%s/_settings', $indexName)
		);
	}

	/**
	 * Gets mappings an ES index.
	 *
	 * @param string $indexName
	 *
	 * @return array
	 */
	public static function getMappings($indexName)
	{
		return self::getInstance()->call(Zend_Http_Client::GET,
			sprintf('%s/_mapping', $indexName)
		);
	}

	/**
	 * Updates settings against an ES index.
	 *
	 * @param string $indexName
	 * @param string $type
	 *
	 * @return array
	 */
	public static function deleteMapping($indexName, $type)
	{
		return self::getInstance()->call(Zend_Http_Client::DELETE,
			sprintf('%s/%s', $indexName, $type)
		);
	}

	/**
	 * Updates mapping for a type in an ES index.
	 *
	 * @param string $indexName
	 * @param string $type
	 * @param array $dsl
	 * @param bool $ignoreConflicts Deprecated and ignored
	 *
	 * @return array
	 */
	public static function updateMapping($indexName, $type, array $dsl, $ignoreConflicts = false)
	{
		if (!isset($dsl[$type]))
		{
			$dsl = array($type => $dsl);
		}

		return self::getInstance()->call(Zend_Http_Client::PUT,
			sprintf('%s/%s/_mapping', $indexName, $type),
			json_encode($dsl)
		);
	}

	/**
	 * Creates an ES index.
	 *
	 * @param string $indexName
	 * @param array $dsl
	 *
	 * @return array
	 */
	public static function createIndex($indexName, array $dsl = array())
	{
		return self::getInstance()->call(Zend_Http_Client::PUT,
			sprintf('%s/', $indexName),
			$dsl ? json_encode($dsl) : null
		);
	}

	/**
	 * Deletes an ES index.
	 *
	 * @param string $indexName
	 * @param string|null $type
	 *
	 * @return array
	 */
	public static function deleteIndex($indexName, $type = null)
	{
		if ($type)
		{
			// can't delete a single type
			return null;
		}
		else
		{
			return self::getInstance()->call(Zend_Http_Client::DELETE,
				sprintf('%s/', $indexName)
			);
		}
	}

	/**
	 * Closes an ES index.
	 *
	 * @param string $indexName
	 * @param array $dsl
	 *
	 * @return array
	 */
	public static function closeIndex($indexName)
	{
		return self::getInstance()->call(Zend_Http_Client::POST,
			sprintf('%s/_close', $indexName)
		);
	}

	/**
	 * Opens an ES index.
	 *
	 * @param string $indexName
	 * @param array $dsl
	 *
	 * @return array
	 */
	public static function openIndex($indexName)
	{
		return self::getInstance()->call(Zend_Http_Client::POST,
			sprintf('%s/_open', $indexName)
		);
	}

	/**
	 * Returns true if the current version of Elasticsearch only supports a single type per index.
	 * This may apply in Elasticsearch 6+.
	 *
	 * @return bool
	 */
	public static function isSingleTypeIndex()
	{
		if (self::$_isSingleType === null)
		{
			self::$_isSingleType = XenForo_Application::getOptions()->elasticSearchSingleType;
		}

		return self::$_isSingleType;
	}

	public static function forceSingleTypeIndex()
	{
		self::$_isSingleType = true;
	}

	public static function getSingleTypeName()
	{
		return self::$_singleTypeName;
	}

	protected static function getIdUrl($indexName, $type, $id)
	{
		if (self::isSingleTypeIndex())
		{
			return sprintf('%s/%s/%s-s', $indexName, self::$_singleTypeName, $type, $id);
		}
		else
		{
			return sprintf('%s/%s/%s', $indexName, $type, $id);
		}
	}

	protected static function addTypeToDataForSingleTypeIndex($type, array &$data)
	{
		if (self::isSingleTypeIndex())
		{
			$data['type'] = $type;
		}
	}

	protected static function getBulkActionEntry($action, $index, $type, $id, array $source = null)
	{
		$isSingleType = self::isSingleTypeIndex();

		$actionLine = array(
			$action => array(
				'_index' => $index,
				'_type' => $isSingleType ? self::$_singleTypeName : $type,
				'_id' => $isSingleType ? "$type-$id" : $id
			)
		);

		$bulk = json_encode($actionLine);

		if (is_array($source))
		{
			if ($isSingleType && ($action == 'index' || $action == 'create'))
			{
				self::addTypeToDataForSingleTypeIndex($type, $source);
			}

			$bulk .= "\n" . json_encode($source);
		}

		return $bulk;
	}

	public static function getTypeAndIdFromHit($hit)
	{
		if (is_array($hit))
		{
			if (empty($hit['_type']) || $hit['_type'] == self::$_singleTypeName)
			{
				$typeAndId = explode('-', $hit['_id'], 2);
			}
			else
			{
				$typeAndId = array($hit['_type'], $hit['_id']);
			}
		}
		else
		{
			if (empty($hit->_type) || $hit->_type == self::$_singleTypeName)
			{
				$typeAndId = explode('-', $hit->_id, 2);
			}
			else
			{
				$typeAndId = array($hit->_type, $hit->_id);
			}
		}

		// make sure the ID is an int
		$typeAndId[1] = intval($typeAndId[1]);

		return $typeAndId;
	}

	/**
	 * Make a call to Elastic Search
	 *
	 * @param string $method
	 * @param string $url
	 * @param string $data
	 *
	 * @return array|false
	 */
	public function call($method, $url, $data = null)
	{
		$this->_httpClient
			->resetParameters()
			->setUri($this->_server . $url);

		if ($data)
		{
			$contentType = $url == '_bulk' ? 'application/x-ndjson' : 'application/json';

			$this->_httpClient->setRawData($data, $contentType);
		}

		try
		{
			$response = $this->_httpClient->request($method);
			$body = $response->getBody();
		}
		catch (Zend_Http_Client_Exception $e)
		{
			return false;
		}

		return ($body ? json_decode($body) : false);
	}

	/**
	 * Gets the ES server URL.
	 *
	 * @return string
	 */
	public function getServer()
	{
		return $this->_server;
	}

	/**
	 * Gets the name of the ES index to use.
	 *
	 * @return string;
	 */
	public function getIndex()
	{
		return $this->_indexName;
	}
}