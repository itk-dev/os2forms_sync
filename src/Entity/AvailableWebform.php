<?php

namespace Drupal\os2forms_sync\Entity;

/**
 * Available webform.
 */
class AvailableWebform {
  /**
   * The webform id.
   *
   * @var string
   */
  public string $id;

  /**
   * The source url.
   *
   * @var string
   */
  public string $sourceUrl;

  /**
   * The attributes.
   *
   * @var array
   *
   * @phpstan-var array<string, mixed>
   */
  public array $attributes;

  /**
   * Constructor.
   *
   * @phpstan-param array<string, mixed> $data
   */
  public function __construct(array $data) {
    $this->id = $data['id'];
    $this->sourceUrl = $data['links']['self'];
    $this->attributes = $data['attributes'];
  }

}
