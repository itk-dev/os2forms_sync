<?php

namespace Drupal\os2forms_sync\Helper;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\webform\WebformEntityStorageInterface;
use Drupal\webform\WebformInterface;

/**
 * The webform helper.
 */
final class WebformHelper {
  use StringTranslationTrait;

  /**
   * The webform entity storage.
   *
   * @var \Drupal\webform\WebformEntityStorageInterface
   */
  private WebformEntityStorageInterface $webformEntityStorage;

  /**
   * The import helper.
   *
   * @var ImportHelper
   */
  private ImportHelper $importHelper;

  /**
   * The request stack.
   *
   * @var \Drupal\Core\Http\RequestStack
   */
  private RequestStack $requestStack;

  /**
   * The constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ImportHelper $importHelper,
    RequestStack $requestStack
  ) {
    $this->webformEntityStorage = $entityTypeManager->getStorage('webform');
    $this->importHelper = $importHelper;
    $this->requestStack = $requestStack;
  }

  /**
   * Load webform.
   */
  public function loadWebform(string $id): ?WebformInterface {
    return $this->webformEntityStorage->load($id);
  }

  /**
   * Load published webforms.
   *
   * @return \Drupal\webform\Entity\WebformInterface[]|array
   *   The webforms.
   *
   * @phpstan-return array<WebformInterface>
   */
  public function loadPublishedWebforms(): array {
    return array_values(array_filter(
      $this->webformEntityStorage->loadMultiple(),
      [$this, 'webformIsPublished']
    ));
  }

  /**
   * Decide if webform is published.
   */
  public function webformIsPublished(WebformInterface $webform): bool {
    return (bool) ($webform->getThirdPartySetting('os2forms', 'os2forms_sync')['publish'] ?? FALSE);
  }

  /**
   * Webform to array.
   *
   * @phpstan-return array<string, mixed>
   */
  public function webformToArray(WebformInterface $webform): array {
    $data = array_filter(
      $webform->toArray(),
      static function ($key) {
        return in_array($key, [
          'id',
          'uuid',
          'title',
          'description',
          'category',
          'elements',
        ]);
      },
      ARRAY_FILTER_USE_KEY
    );

    if (isset($data['elements'])) {
      try {
        $data['elements'] = Yaml::decode($data['elements']);
      }
      catch (InvalidDataTypeException $invalidDataTypeException) {
      }
    }

    $url = Url::fromRoute('os2forms_sync.jsonapi.webform.show', ['webform' => $webform->id()],
      ['absolute' => TRUE])->toString();

    return [
      'data' => $data,
      'links' => [
        'self' => $url,
      ],
    ];
  }

  /**
   * Implements hook_webform_third_party_settings_form_alter().
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function webformThirdPartySettingsFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\EntityForm $formObject */
    $formObject = $form_state->getFormObject();
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $formObject->getEntity();

    $defaultValues = $webform->getThirdPartySetting('os2forms', 'os2forms_sync');
    $form['third_party_settings']['os2forms']['os2forms_sync'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('OS2Forms sync'),
      '#tree' => TRUE,
    ];

    $form['third_party_settings']['os2forms']['os2forms_sync']['publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Publish'),
      '#default_value' => (bool) ($defaultValues['publish'] ?? FALSE),
      '#description' => $this->t('If checked this form will be listed on <a href=":url_index">:url_index</a> and details made available on <a href=":url_show">:url_show</a>', [
        ':url_index' => Url::fromRoute('os2forms_sync.jsonapi.webform.index')->toString(),
        ':url_show' => Url::fromRoute('os2forms_sync.jsonapi.webform.show', ['webform' => $webform->id()])->toString(),
      ]),
    ];

    if ($info = $this->importHelper->loadImportedWebform($webform)) {
      $form['third_party_settings']['os2forms']['os2forms_sync']['message'] = [
        '#prefix' => '<div>',
        '#suffix' => '</div>',
        '#markup' => $this->t('Webform updated from <a href=":url">:url</a> at @updated_at.', [
          ':url' => $info->sourceUrl,
          '@updated_at' => $info->updatedAt->format(DrupalDateTime::FORMAT),
        ]),
      ];

      $form['third_party_settings']['os2forms']['os2forms_sync']['update_interval'] = [
        '#type' => 'select',
        '#title' => $this->t('Update'),
        '#options' => [
          60 * 60 => $this->t('hourly'),
          24 * 60 * 60 => $this->t('daily'),
          7 * 24 * 60 * 60 => $this->t('weekly'),
          30 * 24 * 60 * 60 => $this->t('every 30 days'),
        ],
        '#empty_value' => 0,
        '#empty_option' => $this->t('manually'),
        '#default_value' => (bool) ($defaultValues['update_interval'] ?? 0),
      ];

      $form['third_party_settings']['os2forms']['os2forms_sync']['update_now'] = [
        '#prefix' => '<div>',
        '#suffix' => '</div>',
        '#type' => 'button',
        '#value' => $this->t('Update webform now'),
        '#attributes' => [
          'formmethod' => 'post',
          'formaction' => Url::fromRoute('os2forms_sync.webform.import', [
            'url' => $info->sourceUrl,
            'referer' => Url::fromUri($this->requestStack->getCurrentRequest()->getUri(), ['fragment' => 'edit-third-party-settings-os2forms-os2forms-sync'])->toString(TRUE)->getGeneratedUrl(),
          ])->toString(TRUE)->getGeneratedUrl(),
        ],
      ];
    }
  }

}
