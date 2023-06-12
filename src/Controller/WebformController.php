<?php

namespace Drupal\os2forms_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\os2forms_sync\Entity\AvailableWebform;
use Drupal\os2forms_sync\Helper\ImportHelper;
use Drupal\os2forms_sync\Helper\WebformHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Webform controller.
 */
final class WebformController extends ControllerBase {
  private const FILTER_QUERY_NAME = 'show';
  private const FILTER_WEBFORMS_IMPORTED = 'imported';
  private const FILTER_WEBFORMS_NOT_IMPORTED = 'not imported';

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
   * The webform helper.
   *
   * @var \Drupal\os2forms_sync\Helper\WebformHelper
   */
  private WebformHelper $webformHelper;

  /**
   * Constructor.
   */
  public function __construct(RequestStack $requestStack, ImportHelper $importHelper, WebformHelper $webformHelper) {
    $this->requestStack = $requestStack;
    $this->importHelper = $importHelper;
    $this->webformHelper = $webformHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('request_stack'),
      $container->get(ImportHelper::class),
      $container->get(WebformHelper::class)
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
    $importedWebforms = $this->importHelper->loadImportedWebforms();

    // Filter available webforms.
    switch ($this->requestStack->getCurrentRequest()->get(self::FILTER_QUERY_NAME)) {
      case self::FILTER_WEBFORMS_IMPORTED:
        $webforms = array_filter($webforms, static function (AvailableWebform $webform) use ($importedWebforms) {
          return isset($importedWebforms[$webform->sourceUrl]);
        });
        break;

      case self::FILTER_WEBFORMS_NOT_IMPORTED:
        $webforms = array_filter($webforms, static function (AvailableWebform $webform) use ($importedWebforms) {
          return !isset($importedWebforms[$webform->sourceUrl]);
        });
        break;
    }

    $elements = [
      '#attached' => ['library' => ['os2forms_sync/webform-index']],
    ];

    $elements['search'] = [
      '#type' => 'container',

      'search' => [
        '#type' => 'search',
        '#title' => $this->t('Search'),
        '#title_display' => 'invisible',
        '#attributes' => [
          'placeholder' => $this->t('Search title, description and category …'),
        ],
      ],

      'filters' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['filters'],
        ],

        'links' => [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => [
            (new Link($this->t('Show all webforms'), Url::fromRoute('os2forms_sync.webform.index')))->toRenderable(),
            (new Link($this->t('Show imported webforms'), Url::fromRoute('os2forms_sync.webform.index', ['show' => self::FILTER_WEBFORMS_IMPORTED])))->toRenderable(),
            (new Link($this->t('Show not imported webforms'), Url::fromRoute('os2forms_sync.webform.index', ['show' => self::FILTER_WEBFORMS_NOT_IMPORTED])))->toRenderable(),
          ],
        ],
      ],
    ];

    if (empty($webforms)) {
      $elements['info'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            $this->t('No webforms found'),
          ],
        ],
      ];
    }
    else {
      foreach ($webforms as $webform) {
        $attributes = $webform->attributes;
        try {
          $form = $this->webformHelper->getSubmissionForm($attributes['elements']);
        }
        catch (\Throwable $t) {
          $form = [
            '#theme' => 'status_messages',
            '#message_list' => [
              'error' => [
                $this->t('Cannot render form: @message', ['@message' => $t->getMessage()]),
              ],
            ],
          ];
        }
        // Make sure that the form cannot be submitted (hopefully).
        $form['#attributes']['onsubmit'] = 'return false';

        $sourceUrl = $webform->sourceUrl;
        $importedWebform = $importedWebforms[$sourceUrl] ?? NULL;

        $title = $attributes['title'] ?? $webform->id;
        $item = [
          '#type' => 'fieldset',
          '#title' => $title,
          '#attributes' => [
            'class' => ['os2forms-sync-webform'],
          ],

          'description' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['description'],
              'data-indexed' => strip_tags($title . ' ' . $attributes['description']),
            ],
            '#markup' => Markup::create($attributes['description']),
          ],

          'form_display' => [
            '#type' => 'details',
            '#title' => $this->t('Form display'),
            'form' => $form,
          ],

          'elements' => [
            '#type' => 'details',
            '#title' => $this->t('Elements'),
            '#markup' => '<pre>' . Yaml::encode($attributes['elements']) . '</pre>',
          ],

          'metadata' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['metadata'],
            ],

            'category' => [
              '#type' => 'container',
              '#attributes' => [
                'class' => ['category'],
                'data-indexed' => strip_tags($attributes['category']),
              ],

              'label' => [
                '#type' => 'label',
                '#title' => $this->t('Category'),
                '#title_display' => 'above',
              ],

              'value' => [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#attributes' => [
                  'class' => ['value'],
                ],
                '#value' => $attributes['category'],
              ],
            ],

            'source_url' => [
              '#type' => 'container',

              'label' => [
                '#type' => 'label',
                '#title' => $this->t('Source url'),
                '#title_display' => 'above',
              ],

              'value' => [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#attributes' => [
                  'class' => ['value'],
                ],
                'link' => (new Link($sourceUrl, Url::fromUri($sourceUrl)))->toRenderable(),
              ],
            ],
          ],
        ];

        $item['import_form'] = [
          '#type' => 'html_tag',
          '#tag' => 'form',
          '#attributes' => [
            'method' => 'post',
            'action' => Url::fromRoute('os2forms_sync.webform.import',
              ['url' => $sourceUrl])->toString(TRUE)->getGeneratedUrl(),
          ],

          'button' => [
            '#type' => 'button',
            '#value' => NULL === $importedWebform ? $this->t('Import webform') : $this->t('Update webform'),
          ],
        ];

        if (NULL !== $importedWebform) {
          $item['import_form']['info'] = [
            '#markup' => $this->t('<a href=":webform_url">Webform</a> updated at @updated_at.', [
              ':webform_url' => Url::fromRoute('entity.webform.edit_form',
                ['webform' => $importedWebform->webformId])->toString(TRUE)->getGeneratedUrl(),
              '@updated_at' => $importedWebform->updatedAt->format(DrupalDateTime::FORMAT),
            ]),
          ];
        }

        $elements[] = $item;
      }
    }

    $settingsUrl = Url::fromRoute('os2forms_sync.admin.settings');
    if ($settingsUrl->access($this->currentUser())) {
      $elements['settings'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['settings'],
        ],

        'link' => (new Link($this->t('O2Forms sync settings'), $settingsUrl))->toRenderable(),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['os2forms-sync-webform-index']],

      'elements' => $elements,
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

}
