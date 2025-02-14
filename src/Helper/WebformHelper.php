<?php

namespace Drupal\os2forms_sync\Helper;

use Drupal\Component\Utility\Random;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformEntityStorageInterface;
use Drupal\webform\WebformInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private RequestStack $requestStack;

  /**
   * The constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ImportHelper $importHelper,
    RequestStack $requestStack,
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
      '#description' => $this->t('If checked this form will be listed on <a href=":url_index">:url_index</a>. Share this url with others that may import all public webforms on this site.', [
        ':url_index' => Url::fromRoute('os2forms_sync.jsonapi.webform.index')->setAbsolute()->toString(),
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

  /**
   * Implements hook_theme().
   *
   * @phpstan-param array<string, mixed> $existing
   * @phpstan-return array<string, mixed>
   */
  public function theme(array $existing, string $type, string $theme, string $path): array {
    return [
      'os2forms_sync_webform_index' => [
        'variables' => [
          'webforms' => NULL,
          'settings_url' => NULL,
        ],
      ],
    ];
  }

  /**
   * Get submission form render array from webform elements.
   *
   * @phpstan-param array<string, mixed> $elements
   * @phpstan-return array<string, mixed>
   */
  public function getSubmissionForm(array $elements): array {
    $webform = Webform::create([
      'id' => (new Random())->name(32),
      'elements' => Yaml::encode($elements),
    ]);

    // Hack: Needed to prevent an error in the webform module:
    // Warning: array_intersect_key(): Expected parameter 2 to be an array, null
    // given in Drupal\webform\Entity\Webform->invokeHandlers() ….
    $prop = new \ReflectionProperty($webform, 'settingsOriginal');
    $prop->setAccessible(TRUE);
    $prop->setValue($webform, []);

    return $webform->getSubmissionForm();
  }

}
