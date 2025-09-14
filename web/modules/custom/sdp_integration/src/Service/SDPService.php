<?php

namespace Drupal\sdp_integration\Service;

use GuzzleHttp\Client;
use Drupal\Core\Config\ConfigFactoryInterface;

class SDPService {

  protected $client;
  protected $configFactory;

  public function __construct(Client $client, ConfigFactoryInterface $configFactory) {
    $this->client = $client;
    $this->configFactory = $configFactory;
  }

  public function getUserInfo($accessToken) {
    $config = $this->configFactory->get('sdp_integration.settings');
    $url = $config->get('sdp_base_url') . $config->get('sdp_user_info_endpoint');

    $response = $this->client->post($url, [
      'json' => ['accessToken' => $accessToken],
      'headers' => ['Content-Type' => 'application/json']
    ]);

    return json_decode($response->getBody(), TRUE);
  }

  public function isAuthorized($accessToken) {
    $config = $this->configFactory->get('sdp_integration.settings');
    $url = $config->get('sdp_base_url') . $config->get('sdp_auth_endpoint');

    $response = $this->client->post($url, [
      'json' => ['accessToken' => $accessToken],
      'headers' => ['Content-Type' => 'application/json']
    ]);

    return json_decode($response->getBody(), TRUE);
  }

}
