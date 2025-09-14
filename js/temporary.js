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

          $optionsContext
            .find('input[type=date]')
            .each(function () {
              const val = this.value;
              Drupal.checkPlain(val);
              if (val) {
                const dateObj = new Date(val);
                const formatted = dateObj.toLocaleDateString(undefined, {
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric'
                });
                values.push(formatted);
              }
            });
          return values.join(', ');
        }

        return Drupal.t('Not temporary');
      });
    },
  };
})(jQuery, Drupal, drupalSettings);
