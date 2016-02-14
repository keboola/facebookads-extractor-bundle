<?php

namespace Keboola\FacebookAdsExtractorBundle;

use	Keboola\ExtractorBundle\Extractor\Extractors\JsonExtractor,
	Keboola\ExtractorBundle\Config\Config;
use	Syrup\ComponentBundle\Exception\SyrupComponentException;
use	GuzzleHttp\Client as Client;
use	Keboola\FacebookAdsExtractorBundle\FacebookAdsExtractorJob;
use	Keboola\Code\Builder;

class FacebookAdsExtractor extends JsonExtractor
{
	protected $name = 'fb-ads';

	/**
	 * @var string
	 */
	protected $minStartDate;

	/**
	 * @var int
	 */
	protected $maxSlices;

	public function __construct($minStartDate, $maxSlices)
	{
		$this->minStartDate = $minStartDate;
		$this->maxSlices = $maxSlices;
	}

	public function run(Config $config) {
		$apiVersion = isset($config->getAttributes()['api_version'])
			? $config->getAttributes()['api_version']
			: 'v2.4';

		$client = new Client(
			[
				'base_url' => "https://graph.facebook.com/{$apiVersion}/",
				'defaults' => [
					'query' => ['access_token' => $config->getAttributes()['access_token']]
				]
			]
		);
		$client->getEmitter()->attach($this->getBackoff(12, [500, 503, 504, 408, 420, 429, 400]));

		$parser = $this->getParser($config);
		$builder = new Builder();

		foreach($config->getJobs() as $jobConfig) {
			$this->metadata['jobs.lastStart.' . $jobConfig->getJobId()] =
				empty($this->metadata['jobs.lastStart.' . $jobConfig->getJobId()])
					? 0
					: $this->metadata['jobs.lastStart.' . $jobConfig->getJobId()];
			$this->metadata['jobs.start.' . $jobConfig->getJobId()] = time();

			$job = new FacebookAdsExtractorJob($jobConfig, $client, $parser);
			$job->setConfigMetadata($this->metadata);
			$job->setBuilder($builder);
			$job->setMinStartDate($this->minStartDate);
			$job->setMaxSlices($this->maxSlices);
			$job->run();

			$this->metadata['jobs.lastStart.' . $jobConfig->getJobId()] = $this->metadata['jobs.start.' . $jobConfig->getJobId()];
		}

		$this->updateParserMetadata($parser);

		return $parser->getCsvFiles();
	}
}
