<?php

// phpcs:disable Drupal.Commenting.DocComment.ParamGroup
// phpcs:disable Drupal.Commenting.FunctionComment.ParamMissingDefinition

namespace Drupal\os2forms_sync\Commands;

use Drupal\Core\Url;
use Drupal\os2forms_sync\Helper\ImportHelper;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

/**
 * Drush import commands.
 */
class ImportCommands extends DrushCommands {
  /**
   * The helper.
   *
   * @var \Drupal\os2forms_sync\Helper\ImportHelper
   */
  private ImportHelper $helper;

  /**
   * The constructor.
   */
  public function __construct(ImportHelper $helper) {
    $this->helper = $helper;
  }

  /**
   * Import form.
   *
   * @param string $url
   *   The url.
   *
   * @command os2forms-sync:import
   *
   * @phpstan-param array<string, mixed> $options
   */
  public function render(string $url, array $options = []): void {
    $webform = $this->helper->import($url, $options);
    $url = Url::fromRoute('entity.webform.edit_form', ['webform' => $webform->id()], ['absolute' => TRUE])->toString();
    $this->writeln(Yaml::dump([
      'id' => $webform->id(),
      'uuid' => $webform->uuid(),
      'url' => $url,
    ]));
  }

}
