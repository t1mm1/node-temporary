/**
 * @file
 * Defines JavaScript behaviors for the node_temporary module.
 */

(function ($, Drupal, drupalSettings) {
  /**
   * Behaviors for tabs in the node edit form.
   */
  Drupal.behaviors.nodeTemporaryDetailsSummaries = {
    attach(context) {
      const $context = $(context);

      $context.find('.node-form-temporary-options').drupalSetSummary((context) => {
        const $optionsContext = $(context);
        const values = [];

        if ($optionsContext.find('input:checked').length) {
          $optionsContext
            .find('input:checked')
            .next('label')
            .each(function () {
              values.push(Drupal.checkPlain(this.textContent.trim()));
            });
          return values.join(', ');
        }

        return Drupal.t('Not temporary');
      });
    },
  };
})(jQuery, Drupal, drupalSettings);
