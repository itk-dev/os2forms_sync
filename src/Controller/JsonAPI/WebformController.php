<?php

namespace Drupal\os2forms_sync\Controller\JsonAPI;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\os2forms_sync\Helper\ImportHelper;
use Drupal\os2forms_sync\Helper\JsonAPISerializer;
use Drupal\os2forms_sync\Helper\WebformHelper;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Webform JSON:API controller.
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
   * The JSON:API helper.
   *
   * @var \Drupal\os2forms_sync\Helper\JsonAPISerializer
   */
  private JsonAPISerializer $jsonAPIHelper;

  /**
   * Constructor.
   */
  public function __construct(WebformHelper $webformHelper, ImportHelper $importHelper, JsonAPISerializer $jsonAPIHelper) {
    $this->webformHelper = $webformHelper;
    $this->importHelper = $importHelper;
    $this->jsonAPIHelper = $jsonAPIHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(WebformHelper::class),
      $container->get(ImportHelper::class),
      $container->get(JsonAPISerializer::class)
    );
  }

  /**
   * Index action.
   */
  public function index(): Response {
    $webforms = $this->webformHelper->loadPublishedWebforms();

    $data = $this->jsonAPIHelper->serialize($webforms);
    $data['links']['self'] = Url::fromRoute('os2forms_sync.jsonapi.webform.index', [], ['absolute' => TRUE])->toString();

    return new JsonResponse($data);
  }

  /**
   * Show action.
   */
  public function show(WebformInterface $webform): Response {
    if (!$this->webformHelper->webformIsPublished($webform)) {
      throw new AccessDeniedHttpException();
    }

    return new JsonResponse($this->jsonAPIHelper->serialize($webform));
  }

  /**
   * Imported action.
   */
  public function imported(): Response {
    $data = $this->importHelper->loadImportedWebforms();

    return new JsonResponse($this->jsonAPIHelper->serialize($data));
  }

  /**
   * Available action.
   */
  public function available(): Response {
    $data = $this->importHelper->getAvailableWebforms();

    return new JsonResponse(['data' => $data]);
  }

}
