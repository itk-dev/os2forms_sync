<?php

namespace Drupal\os2forms_sync\Helper;

use Drupal\Core\State\StateInterface;
use Drupal\os2forms_sync\Exception\InvalidSettingException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * General settings for os2forms_sync.
 */
final class Settings {
  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * The key prefix.
   *
   * @var string
   */
  private $stateKey = 'os2forms_sync';

  /**
   * Constructor.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * Get Sources.
   *
   * @return string[]
   *   The sources.
   */
  public function getSources(): array {
    $value = $this->get('sources');
    return is_array($value) ? $value : [];
  }

  /**
   * Get a setting value.
   *
   * @param string $key
   *   The key.
   * @param mixed|null $default
   *   The default value.
   *
   * @return mixed
   *   The setting value.
   */
  private function get(string $key, $default = NULL) {
    $resolver = $this->getSettingsResolver();
    if (!$resolver->isDefined($key)) {
      throw new InvalidSettingException(sprintf('Setting %s is not defined', $key));
    }

    $settings = $this->state->get($this->stateKey);
    return $settings[$key] ?? $default;
  }

  /**
   * Set setting.
   *
   * @throws \Symfony\Component\OptionsResolver\Exception\ExceptionInterface
   *
   * @phpstan-param array<string, mixed> $settings
   */
  public function setSettings(array $settings): self {
    $settings = $this->getSettingsResolver()->resolve($settings);
    $this->state->set($this->stateKey, $settings);

    return $this;
  }

  /**
   * Get settings resolver.
   */
  private function getSettingsResolver(): OptionsResolver {
    return (new OptionsResolver())
      ->setDefaults([
        'sources' => [],
      ])
      ->setAllowedTypes('sources', 'string[]');
  }

}
