<?php

namespace Drupal\os2forms_sync\Helper;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\os2forms_sync\Entity\ImportedWebform;
use Drupal\os2forms_sync\Exception\JsonAPIException;
use Drupal\webform\WebformInterface;

/**
 * The JSON:API serializer.
 *
 * @see https://jsonapi.org/format/
 */
final class JsonAPISerializer {

  /**
   * Serialize.
   *
   * @param mixed $value
   *   The value.
   *
   * @return array
   *   The serialized value.
   *
   * @phpstan-return array<string, mixed>
   */
  public function serialize($value): array {
    return $this->serializeValue($value);
  }

  /**
   * Serialize value.
   *
   * @param mixed $value
   *   The value.
   * @param bool $inline
   *   If the serialized value must be inlined.
   *
   * @return array
   *   The serialized value.
   *
   * @phpstan-return array<string, mixed>
   */
  private function serializeValue($value, bool $inline = FALSE): array {
    if (is_array($value) && !$this->isAssoc($value)) {
      return [
        'data' => array_map(function ($item) {
          return $this->serializeValue($item, TRUE);
        }, $value),
      ];
    }

    if ($value instanceof WebformInterface) {
      return $this->serializeWebform($value, $inline);
    }
    elseif ($value instanceof ImportedWebform) {
      return $this->serializeImportedWebform($value, $inline);
    }

    throw new JsonAPIException(sprintf('Cannot serialize %s', is_object($value) ? get_class($value) : gettype($value)));
  }

  /**
   * Serialize webform.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   * @param bool $inline
   *   If the serialized value must be inlined.
   *
   * @phpstan-return array<string, mixed>
   */
  public function serializeWebform(WebformInterface $webform, $inline = FALSE): array {
    $attributes = array_filter(
      $webform->toArray(),
      static function ($key) {
        return in_array($key, [
          'uuid',
          'title',
          'description',
          'category',
          'elements',
        ]);
      },
      ARRAY_FILTER_USE_KEY
    );

    $attributes = array_map('html_entity_decode', $attributes);

    if (isset($attributes['elements'])) {
      try {
        $attributes['elements'] = Yaml::decode($attributes['elements']);
      }
      catch (InvalidDataTypeException $invalidDataTypeException) {
      }
    }

    $serialized = [
      'id' => $webform->id(),
      'type' => 'webform',
      'attributes' => $attributes,
    ];
    if (!$inline) {
      $serialized = ['data' => $serialized];
    }

    $url = Url::fromRoute('os2forms_sync.jsonapi.webform.show', ['webform' => $webform->id()],
      ['absolute' => TRUE])->toString();

    return $serialized + [
      'links' => [
        'self' => $url,
      ],
    ];
  }

  /**
   * Serialize imported webform.
   *
   * @param \Drupal\os2forms_sync\Entity\ImportedWebform $webform
   *   The imported webform.
   * @param bool $inline
   *   If the serialized value must be inlined.
   *
   * @phpstan-return array<string, mixed>
   */
  public function serializeImportedWebform(ImportedWebform $webform, $inline = FALSE): array {
    $serialized = [
      'id' => $webform->webformId,
      'type' => 'imported_webform',
      'attributes' => [
        'sourceUrl' => $webform->sourceUrl,
        'source' => $webform->source,
        'createdAt' => $webform->createdAt->format(\DateTimeInterface::ATOM),
        'updatedAt' => $webform->updatedAt->format(\DateTimeInterface::ATOM),
      ],
    ];

    if (!$inline) {
      $serialized = ['data' => $serialized];
    }

    return $serialized;
  }

  /**
   * Decide if an array is associative.
   *
   * @param array $arr
   *   The array.
   *
   * @see https://stackoverflow.com/a/173479/2502647
   * @phpstan-ignore-next-line
   */
  private function isAssoc(array $arr): bool {
    if ([] === $arr) {
      return FALSE;
    }
    return array_keys($arr) !== range(0, count($arr) - 1);
  }

}
