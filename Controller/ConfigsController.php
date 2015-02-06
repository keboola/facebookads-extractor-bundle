<?php

namespace Keboola\FacebookAdsExtractorBundle\Controller;

use Keboola\ExtractorBundle\Controller\ConfigsController as Controller;

class ConfigsController extends Controller {
	protected $appName = "ex-fb-ads";
	protected $columns = array (
  0 => '"endpoint"',
  1 => '"params"',
  2 => '"dataType"',
  3 => '"dataField"',
  4 => '"recursionParams"',
);
}
