<?php

namespace Drupal\os2forms_sync\Helper;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\os2forms_sync\Exception\InvalidSettingException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * General settings for os2forms_sync.
 */
final class Settings {
  /**
   * The store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  private KeyValueStoreInterface $store;

  /**
   * The key value collection name.
   *
   * @var string
   */
  private $collection = 'os2forms_sync';

  /**
   * Constructor.
   */
  public function __construct(KeyValueFactoryInterface $keyValueFactory) {
    $this->store = $keyValueFactory->get($this->collection);
  }

  /**
   * Get sources.
   *
   * @return string[]
   *   The sources.
   */
  public function getSources(): array {
    $value = $this->get('sources');
    return is_array($value) ? $value : [];
  }

  /**
   * Get sources time to live.
   *
   * @return int
   *   The ttl.
   */
  public function getSourcesTtl(): int {
    $value = $this->get('sources_ttl');
    return (int) (is_numeric($value) ? $value : 0);
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

    return $this->store->get($key, $default);
  }

  /**
   * Set settings.
   *
   * @throws \Symfony\Component\OptionsResolver\Exception\ExceptionInterface
   *
   * @phpstan-param array<string, mixed> $settings
   */
  public function setSettings(array $settings): self {
    $settings = $this->getSettingsResolver()->resolve($settings);
    foreach ($settings as $key => $value) {
      $this->store->set($key, $value);
    }

    return $this;
  }

  /**
   * Get settings resolver.
   */
  private function getSettingsResolver(): OptionsResolver {
    return (new OptionsResolver())
      ->setDefaults([
        'sources' => [],
        'sources_ttl' => 0,
      ])
      ->setAllowedTypes('sources', 'string[]')
      ->setAllowedTypes('sources_ttl', 'int');
  }

}
