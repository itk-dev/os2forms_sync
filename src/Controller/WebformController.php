<?php

namespace Drupal\os2forms_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\os2forms_sync\Helper\ImportHelper;
use Drupal\os2forms_sync\Helper\WebformHelper;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Webform controller.
 */
final class WebformController extends ControllerBase {
  /**
   * The webform helper.
   *
   * @var \Drupal\os2forms_sync\Helper\WebformHelper
   */
  private WebformHelper $webformHelper;

  /**
   * The import helper.
   *
   * @var \Drupal\os2forms_sync\Helper\ImportHelper
   */
  private ImportHelper $importHelper;

  /**
   * Constructor.
   */
  public function __construct(WebformHelper $helper, ImportHelper $importHelper) {
    $this->webformHelper = $helper;
    $this->importHelper = $importHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(WebformHelper::class),
      $container->get(ImportHelper::class)
    );
  }

  /**
   * Index action.
   */
  public function index(): Response {
    $webforms = $this->webformHelper->loadPublishedWebforms();
    $data = array_map([$this->webformHelper, 'webformToArray'], $webforms);

    return new JsonResponse($data);
  }

  /**
   * Show action.
   */
  public function show(WebformInterface $webform): Response {
    if (!$this->webformHelper->webformIsPublished($webform)) {
      throw new AccessDeniedHttpException();
    }

    return new JsonResponse($this->webformHelper->webformToArray($webform));
  }

  /**
   * Imported action.
   */
  public function imported(): Response {
    $data = $this->importHelper->getWebformImportInformation();

    return new JsonResponse(['data' => $data]);
  }

}
