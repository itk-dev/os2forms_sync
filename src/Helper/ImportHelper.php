<?php

namespace Drupal\os2forms_sync\Helper;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\os2forms_sync\Entity\AvailableWebform;
use Drupal\os2forms_sync\Entity\ImportedWebform;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformEntityStorageInterface;
use Drupal\webform\WebformInterface;
use GuzzleHttp\ClientInterface;

/**
 * The import helper.
 */
final class ImportHelper {
  use StringTranslationTrait;

  private const TABLE_NAME = 'os2forms_sync_webform';

  /**
   * The client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private ClientInterface $client;

  /**
   * The webform entity storage.
   *
   * @var \Drupal\webform\WebformEntityStorageInterface
   */
  private WebformEntityStorageInterface $webformEntityStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

  /**
   * The settings.
   *
   * @var Settings
   */
  private Settings $settings;

  /**
   * The cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private CacheBackendInterface $cache;

  /**
   * The constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ClientInterface $client,
    Connection $database,
    TimeInterface $time,
    Settings $settings,
    CacheBackendInterface $cache,
  ) {
    $this->webformEntityStorage = $entityTypeManager->getStorage('webform');
    $this->client = $client;
    $this->database = $database;
    $this->time = $time;
    $this->settings = $settings;
    $this->cache = $cache;
  }

  /**
   * Import webform.
   *
   * @phpstan-param array<string, mixed> $options
   */
  public function import(string $url, array $options = []): WebformInterface {
    $response = $this->client->request('GET', $url);
    $contents = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    $data = $contents['data'] ?? [];

    $id = $data['id'] ?? NULL;

    $importId = $this->getImportId($id, $url);
    $webform = $this->loadWebformBySourceUrl($url) ?? $this->loadWebform($importId);

    if (NULL === $webform) {
      $settings = []
        + Webform::getDefaultSettings();

      $webform = Webform::create([
        'id' => $importId,
        'settings' => $settings,
      ]);
    }

    foreach ($data['attributes'] as $name => $value) {
      if (in_array($name, ['id', 'uuid'], TRUE)) {
        continue;
      }
      if ('elements' === $name) {
        $value = Yaml::encode($value);
      }
      $webform->set($name, $value);
    }

    $webform
      ->set('revision', TRUE)
      ->set('revision_log_message', [
        ['value' => $this->t('Updated from <a href=":url">:url</a>', [':url' => $url])],
      ]);

    $isNewWebform = $webform->isNew();
    $webform->save();

    $now = $this->time->getCurrentTime();
    $fields = [
      'source_url' => $url,
      'source' => json_encode($contents),
      'updated' => $now,
    ];
    if ($isNewWebform) {
      $fields['webform_id'] = $webform->id();
      $fields['created'] = $now;

      $this->database
        ->insert(self::TABLE_NAME)
        ->fields($fields)
        ->execute();
    }
    else {
      $this->database
        ->update(self::TABLE_NAME)
        ->fields($fields)
        ->condition('webform_id', $webform->id())
        ->execute();
    }

    return $webform;
  }

  /**
   * Get webform import id.
   */
  private function getImportId(string $id, string $sourceUrl): string {
    $webform = $this->loadWebform($id);
    if (NULL === $webform
      || NULL !== $this->loadImportedWebform($webform)) {
      return $id;
    }

    $importId = substr($id . '_sync', 0, EntityTypeInterface::BUNDLE_MAX_LENGTH);
    if (NULL !== $this->loadWebform($importId)) {
      $suffixLength = 2;
      if (strlen($id) > EntityTypeInterface::BUNDLE_MAX_LENGTH - $suffixLength - 1) {
        $id = substr($id, 0, EntityTypeInterface::BUNDLE_MAX_LENGTH - $suffixLength - 1);
      }
      $i = 0;
      $max = pow(10, $suffixLength);
      while ($i < $max) {
        $importId = sprintf('%s_%02d', $id, $i);
        if (NULL === $this->loadWebform($importId)) {
          break;
        }
        $i++;
      }
      if ($i >= $max) {
        throw new \RuntimeException(sprintf('Cannot generate import id'));
      }
    }

    return $importId;
  }

  /**
   * Load webform.
   */
  public function loadWebform(string $id): ?WebformInterface {
    return $this->webformEntityStorage->load($id);
  }

  /**
   * Load webform by source url.
   */
  private function loadWebformBySourceUrl(string $sourceUrl): ?WebformInterface {
    $results = $this->database
      ->select(self::TABLE_NAME, 't')
      ->fields('t')
      ->condition('source_url', $sourceUrl)
      ->execute()
      ->fetchAll();

    if (!empty($results)) {
      $result = reset($results);
      return $this->loadWebform($result->webform_id);
    }

    return NULL;
  }

  /**
   * Load all imported webforms keyed by sourceUrl.
   *
   * @return \Drupal\os2forms_sync\Entity\ImportedWebform[]|array
   *   The imported webforms.
   */
  public function loadImportedWebforms(): ?array {
    return array_map(
      static function ($result) {
        return new ImportedWebform($result);
      },
      $this->database
        ->select(self::TABLE_NAME, 't')
        ->fields('t')
        ->execute()
        ->fetchAllAssoc('source_url')
    );
  }

  /**
   * Load imported webform.
   *
   * @param string|WebformInterface|null $webformId
   *   The webform or webform id.
   */
  public function loadImportedWebform($webformId): ?ImportedWebform {
    if ($webformId instanceof WebformInterface) {
      $webformId = $webformId->id();
    }
    assert(is_string($webformId));

    $result = $this->database
      ->select(self::TABLE_NAME, 't')
      ->fields('t')
      ->condition('webform_id', $webformId)
      ->execute()
      ->fetchObject();

    return $result ? new ImportedWebform($result) : NULL;
  }

  /**
   * Implement hook_schema().
   *
   * @phpstan-return array<string, mixed>
   */
  public function schema(): array {
    return [
      self::TABLE_NAME => [
        'description' => 'OS2Forms sync webforms',
        'fields' => [
          'webform_id' => [
            'description' => 'The webform id.',
            'type' => 'varchar',
            'length' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
            'not null' => TRUE,
          ],
          'source_url' => [
            'description' => 'The import source url.',
            'type' => 'varchar',
            'length' => 1024,
            'not null' => TRUE,
          ],
          'source' => [
            'description' => 'The import source.',
            'type' => 'text',
            'size' => 'medium',
            'not null' => TRUE,
          ],
          'created' => [
            'description' => 'The Unix timestamp when the webform was created.',
            'type' => 'int',
            'not null' => TRUE,
          ],
          'updated' => [
            'description' => 'The Unix timestamp when the webform was updated.',
            'type' => 'int',
            'not null' => TRUE,
          ],
        ],
        'indexes' => [
          'source_url' => ['source_url'],
        ],
        'primary key' => ['webform_id'],
      ],
    ];
  }

  /**
   * Implements hook_webform_delete().
   */
  public function deleteWebform(WebformInterface $webform): void {
    $this->database
      ->delete(self::TABLE_NAME)
      ->condition('webform_id', $webform->id())
      ->execute();
  }

  /**
   * Get available published webforms.
   *
   * @return \Drupal\os2forms_sync\Entity\AvailableWebform[]|array
   *   The available webforms.
   */
  public function getAvailableWebforms(): array {
    $sources = array_unique($this->settings->getSources());
    $ttl = $this->settings->getSourcesTtl();
    $cacheKey = preg_replace(
      '#[{}()/\\\\@:]+#',
      '_',
      __METHOD__ . '|' . sha1(json_encode([
        'sources' => $sources,
        'source_ttl' => $ttl,
      ]))
    );

    if ($ttl > 0 && $hit = $this->cache->get($cacheKey)) {
      $webforms = $hit->data;
    }
    else {
      $webforms = $this->fetchAvailableWebforms($sources);

      if ($ttl > 0) {
        $this->cache->set($cacheKey, $webforms, time() + $ttl);
      }
    }

    return array_map(
      static function ($webform) {
        return new AvailableWebform($webform);
      },
      $webforms
    );
  }

  /**
   * Fetch available published webforms.
   *
   * @param string[]|array $sources
   *   The sources.
   *
   * @phpstan-return array<mixed>
   */
  public function fetchAvailableWebforms(array $sources): array {
    $webforms = [];
    foreach ($sources as $source) {
      $json = @json_decode(file_get_contents($source), TRUE) ?: [];
      if (isset($json['data'])) {
        $webforms[] = $json['data'];
      }
    }

    return array_merge(...$webforms);
  }

  /**
   * Get available webform by url.
   *
   * @param string $url
   *   The webform url.
   *
   * @return \Drupal\os2forms_sync\Entity\AvailableWebform|null
   *   The webform if any.
   */
  public function getAvailableWebform(string $url): ?AvailableWebform {
    $webforms = $this->getAvailableWebforms();
    foreach ($webforms as $webform) {
      if ($url === ($webform['links']['self'] ?? NULL)) {
        return $webform;
      }
    }

    return NULL;
  }

}
