<?php

namespace Drupal\os2forms_sync\Twig;

use Drupal\Component\Utility\Random;
use Drupal\Core\Render\RendererInterface;
use Drupal\webform\Entity\Webform;
use Symfony\Component\Yaml\Yaml;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension.
 */
class TwigExtension extends AbstractExtension {
  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private RendererInterface $renderer;

  /**
   * Constructor.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('yaml_encode', [$this, 'yamlEncode']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('render_webform_elements', [
        $this,
        'renderWebformElements',
      ]),
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

  /**
   * Render webform elements.
   */
  public function renderWebformElements(array $elements): string {
    $webform = Webform::create([
      'id' => (new Random())->name(32),
      'elements' => Yaml::dump($elements),
    ]);

    // Hack: Needed to prevent an error in the webform module.
    $prop = new \ReflectionProperty($webform, 'settingsOriginal');
    $prop->setAccessible(TRUE);
    $prop->setValue($webform, []);

    $submissionForm = $webform->getSubmissionForm();
    $content = $this->renderer->render($submissionForm);

    // Make sure that the form cannot be submitted (hopefully).
    $content = str_replace('<form', '<form onsubmit="return false"', $content);

    return $content;
  }

}
