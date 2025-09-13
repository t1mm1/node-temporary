<?php

namespace Drupal\node_temporary\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\node\NodeInterface;
use Drupal\node_temporary\NodeTemporaryHelper;

/**
 * Hook implementations for the Node Temporary module.
 */
class Views {

  public function __construct(
    private NodeTemporaryHelper $helper,
  ) {
  }

  /**
   * Implements hook_preprocess_views_view_field().
   */
  #[Hook('preprocess_views_view_field')]
  public function entityDelete(&$variables): void {
    if ($variables['field']->field == 'title') {
      if (!empty($variables['row']->_entity) && $variables['row']->_entity instanceof NodeInterface) {
        $entity = $variables['row']->_entity;

        if ($this->helper->getTemporaryEntity($entity)) {
          $variables['#attached']['library'][] = 'node_temporary/icon';
          $variables['output'] = Markup::create($variables['output'] . '<i class="icon-temporary"></i>');
        }
      }

    }
  }

}
