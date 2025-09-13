<?php

namespace Drupal\node_temporary\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the Node Temporary module.
 */
class Theme {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'node_temporary_icon' => [
        'variables' => [
          'output' => NULL,
          'title' => NULL,
        ],
      ],
    ];
  }

}
