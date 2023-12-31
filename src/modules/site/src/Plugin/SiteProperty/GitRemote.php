<?php

namespace Drupal\site\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\site\SitePropertyPluginBase;

/**
 * Plugin implementation of the site_property.
 *
 * @SiteProperty(
 *   id = "git_remote",
 *   name = "git_remote",
 *   label = @Translation("Git Remote"),
 *   description = @Translation("The git repository this site was cloned from.")
 * )
 */
class GitRemote extends SitePropertyPluginBase {

  /**
   * Load git remote .git directory, if it exists.
   * @return array|false|mixed|string|string[]|void
   */
  public function value() {
    if (getenv('DRUPAL_SITE_GIT_REMOTE')) {
      return getenv('DRUPAL_SITE_GIT_REMOTE');
    }
    $git_file = \Drupal::root() . '/../.git/config';
    if (file_exists($git_file)) {
      $data = parse_ini_file($git_file, TRUE);
      if ($data['remote origin']['url']) {
        return $data['remote origin']['url'];
      }
    }
  }

  /**
   * Define the Git Reference field.
   *
   * @return static
   *   A new field definition object.
   */
  public function baseFieldDefinitions(EntityTypeInterface $entity_type, &$fields) {
    $fields['git_remote'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Git Reference'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'inline',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);
  }
}
