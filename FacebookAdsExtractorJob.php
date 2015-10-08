<?php

namespace Keboola\FacebookAdsExtractorBundle;

use	Keboola\ExtractorBundle\Extractor\Jobs\JsonRecursiveJob,
	Keboola\ExtractorBundle\Common\Logger;
use	Keboola\Utils\Utils,
	Keboola\Utils\Exception\JsonDecodeException;
use	Syrup\ComponentBundle\Exception\SyrupComponentException,
	Syrup\ComponentBundle\Exception\UserException;
use	Keboola\Code\Builder,
	Keboola\Code\Exception\UserScriptException;

class FacebookAdsExtractorJob extends JsonRecursiveJob
{
	protected $configName;

	/**
	 * @var array
	 */
	protected $configMetadata;

	/**
	 * @var array
	 */
	protected $datePeriods = null;

	/**
	 * @var Builder
	 */
	protected $stringBuilder;

	/**
	 * @var string
	 */
	protected $minStartDate;

	/**
	 * @var int
	 */
	protected $maxSlices;

	/**
	 * @brief Return a download request
	 *
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request
	 */
	protected function firstPage()
	{
		try {
			$params = (array) Utils::json_decode($this->config["params"]);
		} catch(JsonDecodeException $e) {
			throw new UserException("Error decoding 'params' JSON: " . $e->getMessage());
		}

		if (!empty($params)) {
			try {
				foreach($params as $key => &$value) {
					if (is_object($value)) {
						$value = $this->stringBuilder->run($value, ['metadata' => $this->configMetadata]);
					}
					unset($value);
				}
			} catch(UserScriptException $e) {
				throw new UserException("User function failed: " . $e->getMessage());
			}
		}

		if (!empty($params['slice_by'])) {
			if ($this->datePeriods === null) {
				// TODO this should perhaps use some iterator class instead!
				$this->datePeriods = $this->createSlicedParams($params);
			}

			$params = array_replace($params, $this->getNextDatePeriod());
			unset($params['slice_by'], $params['running_totals'], $params['sliding_window']);
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

	/**
	 * @todo It has a flaw, the slice_by check should be here and not in the firstPage()
	 * @param array $params
	 * @return array Date ranges for each call
	 */
	protected function createSlicedParams(array $params)
	{
		if (!isset($params['start_time'])) {
			Logger::log('warning', "'start_time' is not defined while trying to slice by {$params['slice_by']}");
			return [];
		} elseif (!is_numeric($params['start_time'])) {
			$startTime = strtotime($params['start_time']);
			if ($startTime === false) {
				throw new UserException("Failed parsing start_time ({$params['start_time']}) to a time string!");
			}
		} else {
			$startTime = $params['start_time'];
		}

		if ($startTime < $this->minStartDate) {
			Logger::log('warning', "Start time '{$startTime}' is set before Facebook went public on '{$this->minStartDate}'. Using '{$this->minStartDate}' as the start time.");
			$this->startTime = $this->minStartDate;
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
			case '7days':
			case '7 days':
				$multiplier = 60*60*24*7;
				break;
			default:
				throw new UserException("Slicing by '{$params['slice_by']}' is not supported!");
				break;
		}

		if (empty($params['running_totals'])) {
			$currentStart = $startTime;

			do {
				$currentEnd = $currentStart + $multiplier;
				$parts[] = [
					'start_time' => date(
						DATE_ISO8601,
						empty($params['sliding_window']) ? $currentStart : ($currentEnd - $this->getTimeWindow($params['sliding_window']))
					),
					'end_time' => date(
						DATE_ISO8601,
						($currentEnd < $endTime) ? $currentEnd : $endTime
					)
				];
				$currentStart = $currentEnd;
			} while($currentEnd < $endTime);
		} else {
			do {
				$currentEnd = (empty($currentEnd) ? $startTime : $currentEnd) + $multiplier;
				$parts[] = [
					'start_time' => date(
						DATE_ISO8601,
						$startTime
					),
					'end_time' => date(
						DATE_ISO8601,
						($currentEnd < $endTime) ? $currentEnd : $endTime
					)
				];
				$currentStart = $currentEnd;
			} while($currentEnd < $endTime);
		}

		if (count($parts) > $this->maxSlices) {
			throw new UserException("Attempted to export data sliced into more than '{$this->maxSlices}' parts.");
		}

		Logger::log("info", "Exporting data sliced into " . count($parts) . " parts.", ['parts' => $parts]);
		return $parts;
	}

	protected function getTimeWindow($timeFrameString)
	{
		$timeFrame = strtotime($timeFrameString, 0);
		if (empty($timeFrame)) {
			throw new UserException(
				"'sliding_window' parameter must be a strtotime compatible string (eg: '7 days'). '{$timeFrameString}' parsing failed."
			);
		}
		return $timeFrame;
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

	public function setConfigMetadata(array $data)
	{
		$this->configMetadata = $data;
	}

	/**
	 * @param Builder $builder
	 */
	public function setBuilder(Builder $builder)
	{
		$this->stringBuilder = $builder;
	}

	/**
	 * @param string|long $date
	 */
	public function setMinStartDate($date)
	{
		$this->minStartDate = is_numeric($date) ? $date : strtotime($date);
	}

	/**
	 * @param int $i
	 */
	public function setMaxSlices($i)
	{
		$this->maxSlices = $i;
	}
}
