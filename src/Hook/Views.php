<?php

namespace Drupal\node_temporary\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\node\NodeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node_temporary\NodeTemporaryHelper;

/**
 * Hook implementations for the Node Temporary module.
 */
class Views {

  public function __construct(
    private RendererInterface $renderer,
    private NodeTemporaryHelper $nodeTemporaryHelper,
  ) {
  }

  /**
   * Implements hook_preprocess_views_view_field().
   */
  #[Hook('preprocess_views_view_field')]
  public function entityDelete(&$variables): void {
    if ($variables['field']->field == 'title') {
      // Check entity for process.
      if (!empty($variables['row']->_entity) && $variables['row']->_entity instanceof NodeInterface) {
        $node = $variables['row']->_entity;

        if ($this->nodeTemporaryHelper->getTemporaryEntity($node)) {
          $content = [
            '#theme' => 'node_temporary_icon',
            '#output' => $variables['output'],
            '#title' => Markup::create(
              $this->nodeTemporaryHelper->getMessage($node, TRUE)
            ),
            '#cache' => [
              'tags' => $node->getCacheTags(),
              'max-age' => $node->getCacheMaxAge(),
            ],
          ];

          $variables['#attached']['library'][] = 'node_temporary/icon';
          $variables['output'] = $this->renderer->render($content);
        }
      }

    }
  }

}
