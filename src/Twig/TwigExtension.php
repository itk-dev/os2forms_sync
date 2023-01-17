<?php

namespace Drupal\os2forms_sync\Twig;

use Symfony\Component\Yaml\Yaml;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension.
 */
class TwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('yaml_encode', [$this, 'yamlEncode']),
    ];
  }

  /**
   * Yaml encode.
   *
   * @param mixed $value
   *   The value.
   */
  public function yamlEncode($value): string {
    return Yaml::dump($value);
  }

}
