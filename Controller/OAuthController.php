<?php

namespace Keboola\FacebookAdsExtractorBundle\Controller;

use	Syrup\ComponentBundle\Exception\UserException,
	Syrup\ComponentBundle\Exception\SyrupComponentException;
use	Symfony\Component\HttpFoundation\Response,
	Symfony\Component\HttpFoundation\Request;
use	GuzzleHttp\Client as GuzzleClient,
	GuzzleHttp\Exception\ClientException;
use	Keboola\Utils\Utils;
use Keboola\ExtractorBundle\Controller\OAuth20Controller;
use	Keboola\StorageApi\ClientException as SapiClientException;

class OAuthController extends OAuth20Controller
{
	/**
	 * @var string
	 */
	protected $appName = "ex-fb-ads";

	/**
	 * OAuth 2.0 token retrieval URL
	 * See (C) at @link http://www.ibm.com/developerworks/library/x-androidfacebookapi/fig03.jpg
	 * ie: https://api.example.com/oauth2/token
	 * @var string
	 */
	protected $tokenUrl = "";

    public function preExecute(Request $request)
    {
        parent::preExecute($request);
        Request::setTrustedProxies(array($request->server->get('REMOTE_ADDR')));
    }

	/**
	 * Create OAuth 2.0 request code URL (use CODE "response type")
	 * See (A) at @link http://www.ibm.com/developerworks/library/x-androidfacebookapi/fig03.jpg
	 * @param string $redirUrl Redirect URL
	 * @param string $clientId Application's registered Client ID
	 * @param string $hash Session verification code (use in the "state" query parameter)
	 * @return string URL
	 * ie: return "https://api.example.com/oauth2/auth?
	 *	response_type=code
	 *	&client_id={$clientId}
	 *	&redirect_uri={$redirUrl}
	 *	&scope=users.read+records.write
	 *	&state={$hash}"
	 *	(obviously without them newlines!)
	 */
	protected function getOAuthUrl($redirUrl, $clientId, $hash)
	{
		return "https://www.facebook.com/dialog/oauth?client_id={$clientId}&redirect_uri={$redirUrl}&scope=ads_management,manage_pages&state={$hash}";
	}

	/**
	 * Because FB uses GET to get token
	 * {@inheritdoc}
	 *
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function getOAuthCallbackAction(Request $request)
	{
		$this->initSessionBag();

		if ($request->query->get("state") != $this->sessionBag->get("hash")) {
			throw new UserException("Invalid session verification hash");
		}

		$guzzle = new GuzzleClient();
		try {
			$response = $guzzle->get(sprintf(
				"https://graph.facebook.com/oauth/access_token?client_id=%s&redirect_uri=%s&client_secret=%s&code=%s",
				$this->getClientId(),
				$this->getCallbackUrl($request),
				$this->getClientSecret(),
				$request->query->get("code")
			));
		} catch (ClientException $e) {
			$errCode = $e->getResponse()->getStatusCode();
			if ($errCode == 400) {
// 				$desc = json_decode($e->getResponse()->getBody(true), true);
// 				throw new UserException("OAuth authentication failed[{$desc["code"]}]: {$desc["error_message"]}");
				throw new UserException("OAuth authentication failed.", $e, ['message' => (string) $e->getResponse()->getBody(true)]);
			} else {
				throw $e;
			}
		}

		$responseData = $this->parseTokenResponse($response->getBody(true));

		$this->storeOAuthData($responseData);
		return $this->returnResult($responseData);
	}

	/**
	 * @param string $body The FB OAuth access_token response body
	 * @return array
	 */
	protected function parseTokenResponse($body)
	{
		$parts = explode('&', $body);
		$result = [];
		foreach($parts as $part) {
			$pair = explode('=', $part, 2);
			if (count($pair) !== 2) {
				throw new SyrupComponentException(500, "Error parsing response from Facebook OAuth at {$part}", null, $parts);
			}

			if (array_key_exists($pair[0], $result)) {
				throw new SyrupComponentException(500, "Error parsing response from Facebook OAuth at {$part} - Key {$pair[0]} already exists", null, $parts);
			}

			$result[$pair[0]] = $pair[1];
		}
		return $result;
	}

	/**
	 * Handle saving data returned from the OAuth process to StorageApi
	 * By default, all response data is saved as oauth.{key}:{value} pairs
	 * Override this to change which attributes to save, which to set protected etc..
	 * @param \stdClass $data Data from the OAuth response
	 * @return void
	 * @todo workaround for the oauth.XX, respectively required_attributes not being handled with '.'
	 */
	protected function storeOAuthData($data)
	{
		$storageApi = $this->getStorageApi();

		$tableId = "sys.c-{$this->appName}." . $this->sessionBag->get("config");
		try {
			if (!$storageApi->tableExists($tableId)) {
				throw new UserException(sprintf("Configuration %s doesn't exist!", $this->sessionBag->get("config")));
			}

			foreach($data as $key => $value) {
				// Try to guess whether to save the attribute as protected or not, by looking for "token" in its name
				$protected = (strpos($key, "token") === false) ? false : true;

				$storageApi->setTableAttribute(
					$tableId,
					$key,
					$value,
					$protected
				);
			}
		} catch(SapiClientException $e) {
			throw new UserException("Saving OAuth information to SAPI failed: " . $e->getMessage(), $e);
		}
	}
}
