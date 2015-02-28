<?php

namespace Keboola\FacebookAdsExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Jobs\JsonRecursiveJob;
use	Keboola\Utils\Utils;
use Syrup\ComponentBundle\Exception\SyrupComponentException;

class FacebookAdsExtractorJob extends JsonRecursiveJob
{
	protected $configName;

	/**
	 * @brief Return a download request
	 *
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request
	 */
	protected function firstPage()
	{
		$params = Utils::json_decode($this->config["params"], true);
		$url = Utils::buildUrl(trim($this->config["endpoint"], "/"), $params);

// 		$this->configName = preg_replace("/[^A-Za-z0-9\-\._]/", "_", trim($this->config["endpoint"], "/"));

		return $this->client->createRequest("GET", $url);
	}

	/**
	 * @brief Return a download request OR false if no next page exists
	 *
	 * @param $response
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request | false
	 */
	protected function nextPage($response, $data)
	{
		if (empty($response->paging->next)) {
			return false;
		}

		return $this->client->createRequest("GET", $response->paging->next);
	}

	/**
	 * Workaround for /stats results
	 * @param mixed $response
	 * @param array|string $parentId ID (or list thereof) to be passed to parser
	 * @return array The data containing array part of response
	 */
	protected function parse($response, $parentId = null)
	{
		if (substr(trim($this->config["endpoint"], "/"), 0, 5) == 'stats') {
			$response = (array) $response;
		}

		parent::parse($response, $parentId);
	}
}
