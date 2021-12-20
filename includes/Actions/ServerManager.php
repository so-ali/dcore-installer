<?php


namespace Devingo\Installer\Console\Actions;


use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ServerManager {
	private $serverURL;
	private $slug;
	private $version;
	private $license;

	public function __construct (string $slug, string $version = '', string $license = '') {
		$this->serverURL = 'http://127.0.0.1:8000/api/cli/';
		$this->slug      = $slug;
		$this->version   = $version;
		$this->license   = $license;
	}

	public function getAddonDownloadUrl () {
		$parsedSlug = $this->slug;
		if ( !empty($this->version) ) {
			$parsedSlug .= '@' . $this->version;
		}
		$targetURL = sprintf($this->serverURL . 'install/%s/%s', $parsedSlug, urlencode($this->license));

		return $this->_request($targetURL);
	}

	private function _request (string $url) {
		$client = HttpClient::create();
		try {
			$response = $client->request('GET', $url);
		} catch ( TransportExceptionInterface $e ) {
			return [
				'status' => false,
				'data'   => 'An error occurred while requesting!'
			];
		}

		if ( $response->getStatusCode() !== 200 ) {
			return [
				'status' => false,
				'data'   => 'An error occurred while requesting!'
			];
		}
		$response = $response->toArray();

		if ( !is_array($response) ) {
			$response = [];
		}

		return array_merge([
			'status' => false,
			'data'   => 'An error occurred while requesting!'
		], $response);
	}

	public function fileDownloader (string $url, string $path) {
		if ( function_exists('copy') && copy($url, $path) ) {
			return $path;
		}

		if ( function_exists('file_put_contents') && function_exists('file_get_contents') && file_put_contents($path, file_get_contents($url)) ) {
			return $path;
		}

		if ( function_exists('curl_version') && extension_loaded('curl') ) {
			$ch       = curl_init($url);
			$fileOpen = fopen($path, 'wb');
			curl_setopt($ch, CURLOPT_FILE, $fileOpen);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_exec($ch);
			curl_close($ch);
			fclose($fileOpen);

			return $path;
		}

		return false;
	}

	public function getAddonVersions () {
		$targetURL = sprintf($this->serverURL . 'detail/%s/%s', $this->slug, urlencode($this->license));

		return $this->_request($targetURL);
	}
}