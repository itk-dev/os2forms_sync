<?php

namespace Drupal\os2forms_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\os2forms_sync\Helper\ImportHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Webform controller.
 */
final class WebformController extends ControllerBase {
  /**
   * The request stack.
   *
   * @var \Drupal\Core\Http\RequestStack
   */
  private RequestStack $requestStack;

  /**
   * The import helper.
   *
   * @var \Drupal\os2forms_sync\Helper\ImportHelper
   */
  private ImportHelper $importHelper;

  /**
   * Constructor.
   */
  public function __construct(RequestStack $requestStack, ImportHelper $importHelper) {
    $this->requestStack = $requestStack;
    $this->importHelper = $importHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('request_stack'),
      $container->get(ImportHelper::class)
    );
  }

  /**
   * Index action.
   *
   * @return \Symfony\Component\HttpFoundation\Response|array
   *   The response.
   *
   * @phpstan-return array<string, mixed>
   */
  public function index(): array {
    $webforms = $this->importHelper->getAvailableWebforms();

    return [
      '#theme' => 'os2forms_sync_webforms_index',
      '#webforms' => $webforms,
    ];
  }

  /**
   * Import action.
   *
   * @return \Symfony\Component\HttpFoundation\Response|array
   *   The response.
   *
   * @phpstan-return \Symfony\Component\HttpFoundation\Response|array<string, mixed>
   */
  public function import() {
    $request = $this->requestStack->getCurrentRequest();
    $url = $request->get('url');
    if (empty($url)) {
      throw new BadRequestHttpException();
    }

    $referrer = $request->query->get('referer');
    if ('POST' === $request->getMethod()) {
      try {
        $webform = $this->importHelper->import($url);
        $this->messenger()->addStatus($this->t('Webform @title imported.', ['@title' => $webform->get('title')]));

        return new TrustedRedirectResponse($referrer ?? Url::fromRoute('entity.webform.edit_form', ['webform' => $webform->id()])->toString(TRUE)->getGeneratedUrl());
      }
      catch (\Exception $exception) {
        $this->messenger()->addError($exception->getMessage());
      }

      return new TrustedRedirectResponse($referrer ?? Url::fromRoute('os2forms_sync.webform.import', ['url' => $url])->toString(TRUE)->getGeneratedUrl());
    }

    $webform = $this->importHelper->getAvailableWebform($url);

    return [
      '#theme' => 'os2forms_sync_webform_import',
      '#url' => $url,
      '#webform' => $webform,
    ];
  }

}
