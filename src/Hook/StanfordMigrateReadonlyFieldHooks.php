<?php

declare(strict_types=1);

namespace Drupal\stanford_migrate\Hook;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\stanford_migrate\StanfordMigrateInterface;

class StanfordMigrateReadonlyFieldHooks {

  use MessengerTrait;

  /**
   */
  public function __construct(protected StanfordMigrateInterface $stanfordMigrate, protected RouteMatchInterface $routeMatch, protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Sets "Empty Fields" module settings to form elements that are imported.
   */
  #[Hook('entity_form_display_alter')]
  public function entityFormDisplayAlter(EntityFormDisplayInterface $form_display, array $context) {
    // We only care about nodes, but this could be expanded later if more entities
    // are imported.
    $node = $this->routeMatch->getParameter('node');
    if ($context['entity_type'] != 'node' || !$node) {
      return;
    }

    $migration = $this->stanfordMigrate->getNodesMigration($node);
    // Check if the current node was imported.
    if (!$migration) {
      return;
    }

    // Grab the default display settings for use later.
    $default_display = $this->entityTypeManager->getStorage('entity_view_display')
      ->load("node.{$node->bundle()}.default");

    $field_definitions = $form_display->get('fieldDefinitions');
    foreach ($form_display->getComponents() as $field_name => $component) {
      // Make sure the field component is one of the field definitions.
      if (empty($field_definitions[$field_name])) {
        continue;
      }

      // When edit an existing node that was imported via migrate module, mark the
      // fields that are mapped from migration as readonly.
      $field_definition = $field_definitions[$field_name];
      $columns = $field_definition->getFieldStorageDefinition()->getColumns();
      $processing = !empty($migration->process[$field_name]) || !empty($migration->process["$field_name/0"]);

      // This will check if a migrate process is mapped to a specific column on
      // the field.
      foreach (array_keys($columns) as $column) {
        $processing = $processing ?: !empty($migration->process["$field_name/$column"]) || !empty($migration->process["$field_name/0/$column"]);
      }

      // If the migration destination has the `overwrite_properties` configured,
      // those fields specifically should be locked, not the other fields that
      // are not designated in the original process configuration.
      if ($processing && !empty($migration->get('destination')['overwrite_properties'])) {
        // If the current field doesn't exist in the overwrite_properties, it
        // should not be considered to be processing since it's a one time only
        // import.
        $processing = FALSE;

        foreach ($migration->get('destination')['overwrite_properties'] as $overwrite_property) {
          // If any part of the field is set to overwrite, lock the whole field
          // down.
          $overwrite_property = strstr($overwrite_property, '/', TRUE) ?: $overwrite_property;
          if ($field_name == $overwrite_property) {
            $processing = TRUE;
          }
        }
      }

      if ($processing) {
        $this->messenger()
          ->addWarning(t('Some fields can not be edited since they contain imported & synced data.'));

        // If the default display is configured with some settings, let's use that
        // for the best display on the entity form. If it's not configured, the
        // readonly_field_widget module will use some default display settings.
        if ($display_component = $default_display->getComponent($field_name)) {
          $component['settings']['formatter_type'] = $display_component['type'];
          $component['settings']['formatter_settings'][$display_component['type']] = $display_component['settings'];
          $component['settings']['formatter_third_party_settings'] = $display_component['third_party_settings'] ?? [];

          // Add the empty fields module settings to display a message.
          $component['settings']['formatter_third_party_settings']['empty_fields']['handler'] = 'text';
          $component['settings']['formatter_third_party_settings']['empty_fields']['settings']['empty_text'] = '<em>' . t('No Data') . '</em>';
          $component['settings']['formatter_third_party_settings']['stanford_migrate']['readonly'] = TRUE;
        }
        $component['type'] = 'readonly_field_widget';
        $form_display->setComponent($field_name, $component);
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_field')]
  public function preprocessField(&$variables) {
    if ($variables['element']['#third_party_settings']['stanford_migrate']['readonly'] ?? FALSE) {
      // Wrap the readonly form fields with classes so that they can be identified
      // more easily to the user.
      $variables['attributes']['class'][] = 'messages';
      $variables['attributes']['class'][] = 'messages--warning';
      $variables['attributes']['class'][] = 'messages--readonly';
      $variables['#attached']['library'][] = 'stanford_migrate/readonly';
    }
  }

}
