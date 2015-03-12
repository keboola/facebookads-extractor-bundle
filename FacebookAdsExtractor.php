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

	public function run(Config $config) {
		$client = new Client(
			[
				'base_url' => 'https://graph.facebook.com/v2.2/',
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
			$job->run();

			$this->metadata['jobs.lastStart.' . $jobConfig->getJobId()] = $this->metadata['jobs.start.' . $jobConfig->getJobId()];
		}

		$this->updateParserMetadata($parser);

		return $parser->getCsvFiles();
	}
}
