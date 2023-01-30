<?php

namespace Drupal\os2forms_sync\Entity;

/**
 * Imported webform.
 */
class ImportedWebform {
  /**
   * The webform id.
   *
   * @var string
   */
  public string $webformId;

  /**
   * The source url.
   *
   * @var string
   */
  public string $sourceUrl;

  /**
   * The source.
   *
   * @var string
   */
  public string $source;

  /**
   * The creation time.
   *
   * @var \DateTimeInterface|\DateTimeImmutable
   */
  public \DateTimeInterface $createdAt;

  /**
   * The update time.
   *
   * @var \DateTimeInterface|\DateTimeImmutable
   */
  public \DateTimeInterface $updatedAt;

  /**
   * Constructor.
   */
  public function __construct(object $data) {
    $this->webformId = $data->webform_id;
    $this->sourceUrl = $data->source_url;
    $this->source = $data->source;
    $this->createdAt = new \DateTimeImmutable('@' . $data->created);
    $this->updatedAt = new \DateTimeImmutable('@' . $data->updated);
  }

}
