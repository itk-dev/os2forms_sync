<?php

namespace Drupal\os2forms_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\os2forms_sync\Helper\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsResolverException;

/**
 * OS2Forms sync settings form.
 */
final class SettingsForm extends FormBase {
  use StringTranslationTrait;

  /**
   * The settings.
   *
   * @var \Drupal\os2forms_sync\Helper\Settings
   */
  private Settings $settings;

  /**
   * Constructor.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(Settings::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'os2forms_sync_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['sources'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sources'),
      '#default_value' => implode(PHP_EOL, $this->settings->getSources()),
      '#description' => $this->t('Source URLs. One per line.'),
    ];

    $form['sources_info'] = [
      '#markup' => $this->t('See <a href=":url">webforms available for import</a>.', [
        ':url' => Url::fromRoute('os2forms_sync.webform.index')->toString(),
      ]),
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return void
   */
  public function submitForm(array &$form, FormStateInterface $formState) {
    try {
      $settings['sources'] = $this->getSources($formState);
      $this->settings->setSettings($settings);
      $this->messenger()->addStatus($this->t('Settings saved'));
    }
    catch (OptionsResolverException $exception) {
      $this->messenger()->addError($this->t('Settings not saved (@message)', ['@message' => $exception->getMessage()]));
    }
  }

  /**
   * Get sources.
   *
   * @return string[]|array
   *   The sources.
   */
  private function getSources(FormStateInterface $formState): array {
    return array_filter(
      array_map(
        'trim',
        explode(PHP_EOL, $formState->getValue('sources', ''))
      )
    );
  }

}
