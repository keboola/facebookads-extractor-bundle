<?php

namespace Keboola\FacebookAdsExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Jobs\JsonRecursiveJob,
	Keboola\ExtractorBundle\Common\Logger;
use	Keboola\Utils\Utils;
use Syrup\ComponentBundle\Exception\SyrupComponentException,
	Syrup\ComponentBundle\Exception\UserException;

class FacebookAdsExtractorJob extends JsonRecursiveJob
{
	protected $configName;
	/**
	 * @var array
	 */
	protected $datePeriods = null;

	/**
	 * @brief Return a download request
	 *
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request
	 */
	protected function firstPage()
	{
		$params = Utils::json_decode($this->config["params"], true);
		// TODO functions

		if (!empty($params['slice_by'])) {
			if ($this->datePeriods === null) {
				// TODO this should perhaps use some iterator class instead!
				$this->datePeriods = $this->createSlicedParams($params);
			}

			$params = array_replace($params, $this->getNextDatePeriod());
			unset($params['slice_by']);
		}

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
			// see if next time period is requested
			if (!empty($this->datePeriods)) {
				return $this->firstPage();
			} else {
				return false;
			}
		}

		return $this->client->createRequest("GET", $response->paging->next);
	}

	protected function getNextDatePeriod()
	{
		$currentPeriod = array_splice($this->datePeriods, 0, 1)[0];
		Logger::log('debug', "Getting new date period.", $currentPeriod);
		return $currentPeriod;
	}

	protected function createSlicedParams(array $params)
	{
		if (!isset($params['start_time'])) {
			Logger::log('warning', "'start_time' is not defined while trying to slice by {$params['slice_by']}");
			return false;
		} elseif (!is_int($params['start_time'])) {
			$startTime = strtotime($params['start_time']);
			if ($startTime === false) {
				throw new UserException("Failed parsing start_time ({$params['start_time']}) to a time string!");
			}
		} else {
			$startTime = $params['start_time'];
		}

		if (empty($params['end_time'])) {
			$endTime = time();
		} else {
			$endTime = $params['end_time'];
		}

		switch ($params['slice_by']) {
			case 'day':
				$multiplier = 60*60*24;
				break;
			case 'week':
				$multiplier = 60*60*24*7;
				break;
			default:
				throw new UserException("Slicing by '{$params['slice_by']}' is not supported!");
				break;
		}

		$currentStart = $startTime;
		do {
			$currentEnd = $currentStart + $multiplier;
			$parts[] = [
				'start_time' => date(DATE_ISO8601, $currentStart),
				'end_time' => date(DATE_ISO8601, $currentEnd)
			];
			$currentStart = $currentEnd;
		} while($currentEnd < $endTime);

		return $parts;
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
