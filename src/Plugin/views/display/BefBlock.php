<?php

namespace Drupal\better_exposed_filters\Plugin\views\display;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\views\Plugin\views\display\Block;

/**
 * A block plugin that allows exposed filters to be configured.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "bef_block",
 *   title = @Translation("BEF Block"),
 *   help = @Translation("Display the view as a block."),
 *   theme = "views_view",
 *   register_theme = FALSE,
 *   uses_hook_block = TRUE,
 *   contextual_links_locations = {"block"},
 *   admin = @Translation("BEF Block")
 * )
 *
 * @see \Drupal\views\Plugin\block\block\ViewsBlock
 * @see \Drupal\views\Plugin\Derivative\ViewsBlock
 */
class BefBlock extends Block {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['allow']['contains']['exposed_filters'] = ['default' => 'exposed_filters'];
    return $options;
  }

  /**
   * Returns plugin-specific settings for the block.
   *
   * @param array $settings
   *   The settings of the block.
   *
   * @return array
   *   An array of block-specific settings to override the defaults provided in
   *   \Drupal\views\Plugin\Block\ViewsBlock::defaultConfiguration().
   *
   * @see \Drupal\views\Plugin\Block\ViewsBlock::defaultConfiguration()
   */
  public function blockSettings(array $settings) {
    $settings = parent::blockSettings($settings);
    $settings['exposed_filters'] = [];
    $filters = $this->view->display_handler->getHandlers('filter');
    foreach ($filters as $id => $filter) {
      if (!$filter->options['exposed']) {
        continue;
      }
      $settings['exposed_filters'][$id]['enabled'] = FALSE;
      $settings['exposed_filters'][$id]['value'] = '';
    }
    return $settings;
  }

  /**
   * Provide the summary for page options in the views UI.
   *
   * This output is returned as an array.
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    // @todo: make this more general and not reliant on the fact that
    // items_per_page is currently the only allowed block config setting.
    $filtered_allow = array_filter($this->getOption('allow'));
    $allowed = [];
    if (isset($filtered_allow['items_per_page'])) {
      $allowed[] = $this->t('Items per page');
    }
    if (isset($filtered_allow['exposed_filters'])) {
      $allowed[] = $this->t('Exposed filters');
    }
    $options['allow'] = array(
      'category' => 'block',
      'title' => $this->t('Allow settings'),
      'value' => empty($allowed) ? $this->t('None') : implode(', ', $allowed),
    );
  }

  /**
   * Adds the configuration form elements specific to this views block plugin.
   *
   * This method allows block instances to override the views exposed filters.
   *
   * @param \Drupal\views\Plugin\Block\ViewsBlock $block
   *   The ViewsBlock plugin.
   * @param array $form
   *   The form definition array for the block configuration form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array $form
   *   The renderable form array representing the entire configuration form.
   *
   * @see \Drupal\views\Plugin\Block\ViewsBlock::blockForm()
   */
  public function blockForm(ViewsBlock $block, array &$form, FormStateInterface $form_state) {
    parent::blockForm($block, $form, $form_state);
    $allow_settings = array_filter($this->getOption('allow'));

    $block_configuration = $block->getConfiguration();

    foreach ($allow_settings as $type => $enabled) {
      if (empty($enabled)) {
        continue;
      }
      if ($type == 'exposed_filters') {
        $filters = $this->view->display_handler->getHandlers('filter');
        foreach ($filters as $id => $filter) {
          if (!$filter->options['exposed']) {
            continue;
          }

          if (!empty($filter->options['expose']['label'])) {
            $title = $this->t('Exposed filter: @id (%label)', [
              '@id' => $id,
              '%label' => $filter->options['expose']['label']
            ]);
          }
          else {
            $title = $this->t('Exposed filter: @id', ['@id' => $id]);
          }

          $form['override']['exposed_filters'][$id]['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $title,
            '#default_value' => $block_configuration['exposed_filters'][$id]['enabled'],
          ];
          $form['override']['exposed_filters'][$id]['value'] = [
            '#title' => $this->t('Value for %label', ['%label' => $id]),
            '#type' => 'textfield',
            '#default_value' => $block_configuration['exposed_filters'][$id]['value'],
            '#states' => [
              'visible' => [
                [
                  ':input[name="settings[override][exposed_filters][' . $id . '][enabled]"]' => array('checked' => TRUE),
                ],
              ],
            ],
          ];
        }
      }
    }

    return $form;
  }

  /**
   * Handles form submission for the views block configuration form.
   *
   * @param \Drupal\views\Plugin\Block\ViewsBlock $block
   *   The ViewsBlock plugin.
   * @param array $form
   *   The form definition array for the full block configuration form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\views\Plugin\Block\ViewsBlock::blockSubmit()
   */
  public function blockSubmit(ViewsBlock $block, $form, FormStateInterface $form_state) {
    parent::blockSubmit($block, $form, $form_state);

    $filters = $form_state->getValue(['override', 'exposed_filters']);
    $config = $block->getConfiguration();

    foreach ($filters as $id => $values) {
      if ($values['enabled']) {
        $config['exposed_filters'][$id] = [
          'enabled' => TRUE,
          'value' => $values['value'],
        ];
      }
      else {
        if (isset($config['exposed_filters'][$id])) {
          unset($config['exposed_filters'][$id]);
        }
      }
      $form_state->unsetValue(['override', 'exposed_filters', $id]);
    }

    $block->setConfiguration($config);
  }

  /**
   * Allows to change the display settings right before executing the block.
   *
   * @param \Drupal\views\Plugin\Block\ViewsBlock $block
   *   The block plugin for views displays.
   */
  public function preBlockBuild(ViewsBlock $block) {
    $config = $block->getConfiguration();
    if (!empty($config['exposed_filters'])) {
      $exposedInput = [];
      foreach ($config['exposed_filters'] as $id => $values) {
        if ($values['enabled']) {
          $exposedInput[$id] = $values['value'];
        }
      }
      $this->view->exposed_data = $exposedInput;
    }
  }

  /**
   * Block views use exposed widgets only if AJAX is set.
   */
  public function usesExposed() {
    if ($this->ajaxEnabled()) {
      return parent::usesExposed();
    }
    return FALSE;
  }

}
