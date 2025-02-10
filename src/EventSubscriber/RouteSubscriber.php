<?php

declare(strict_types=1);

namespace Drupal\stanford_migrate\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('migrate_tools.messages')) {
      $route->setRequirements(['_entity_access' => 'migration.csv']);
    }
  }

}
