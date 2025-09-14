<?php

namespace Drupal\sdp_integration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\sdp_integration\Service\SDPService;

class SDPController extends ControllerBase {

  protected $sdpService;

  // Constructor for dependency injection
  public function __construct(SDPService $sdpService) {
    $this->sdpService = $sdpService;
  }

  // This tells Drupal how to create the class with dependencies
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sdp_integration.service')
    );
  }

  public function getUserInfo(Request $request) {
    $token = $request->query->get('token');
    if (empty($token)) {
      return new JsonResponse(['error' => 'Missing token'], 400);
    }

    try {
      $userInfo = $this->sdpService->getUserInfo($token);
      return new JsonResponse($userInfo);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }

  public function checkAuthorization(Request $request) {
    $token = $request->query->get('token');
    if (empty($token)) {
      return new JsonResponse(['error' => 'Missing token'], 400);
    }

    try {
      $authInfo = $this->sdpService->isAuthorized($token);
      return new JsonResponse($authInfo);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }

}
