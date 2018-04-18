<?php

namespace Drupal\filtered_options_select_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;

/**
 * Plugin implementation of the 'options_select' widget.
 *
 * @FieldWidget(
 *   id = "filtered_options_select",
 *   label = @Translation("Filtered select list"),
 *   field_types = {
 *     "entity_reference",
 *     "list_integer",
 *     "list_float",
 *     "list_string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class FilteredOptionsSelectWidget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Filter the available options.
    if ($selected_options = $this->getSetting('selected_options')) {
      if (($excluded = $this->getSetting('operation')) && $excluded) {
        foreach ($selected_options as $key) {
          unset($element['#options'][$key]);
        }
      }
      else {
        // Don't remove the _none option.
        if (isset($element['#options']['_none'])) {
          $selected_options[] = '_none';
        }
        $element['#options'] = array_intersect_key($element['#options'], array_flip($selected_options));
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [
      'selected_options' => [],
      'operation' => 0,
    ];
    $settings += parent::defaultSettings();

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $options = $this->getAvailableOptions();
    $element = parent::settingsForm($form, $form_state);
    $element['selected_options'] = [
      '#type' => 'select',
      '#default_value' => $this->getSetting('selected_options'),
      '#title' => t('Available options'),
      '#description' => t('Only the selected options will be displayed Items'),
      '#options' => $options,
      '#multiple' => TRUE,
    ];
    $element['operation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Operation'),
      '#default_value' => $this->getSetting('operation'),
      '#options' => [
        0 => $this->t('Include'),
        1 => $this->t('Exclude'),
      ],
    ];

    return $element;
  }

  /**
   * Builds the list of available options.
   *
   * @return array|mixed
   *   The available options.
   */
  public function getAvailableOptions() {
    $options = [];

    $field_settings = $this->getFieldSettings();
    if (isset($field_settings['allowed_values_function']) && ($function = $field_settings['allowed_values_function']) && is_callable($function)) {
      // TODO test if this works correctly.
      $options = call_user_func($function);
    } elseif (isset($field_settings['allowed_values'])) {
      $options = $field_settings['allowed_values'];
    }
    elseif ($this->fieldDefinition->getType() == "entity_reference") {
      // The entity is optional, pass NULL to get all the options.
      $entity = NULL;
      $selection_handler = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($this->fieldDefinition, $entity);
      $referencableEntities = $selection_handler->getReferenceableEntities();
      foreach ($referencableEntities as $entity_type => $values) {
        $options = array_merge($options, $values);
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $options_summary = [];
    // Get the full options array to generate the labels.
    $available_options = $this->getAvailableOptions();
    // Get the selected options.
    $selected_options = $this->getSetting('selected_options');

    // Build the options summary.
    if (!empty($selected_options)) {
      $selected_options = array_slice($selected_options, 0, 5);
      foreach ($selected_options as $key) {
        $options_summary[$key] = $available_options[$key];
      }
      $summary[] = t('Options (@operation): @selected_options', [
        '@operation' => $this->getSetting('operation') == 0 ? $this->t("included") : $this->t("excluded"),
        '@selected_options' => implode(', ', $options_summary)
      ]);
    }

    return $summary;
  }


}
