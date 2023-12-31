<?php

namespace Drupal\site\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\site\Entity\SiteDefinition;
use Drupal\site\Entity\SiteEntity;

/**
 * Form controller for the site entity edit forms.
 */
class SiteForm extends ContentEntityForm {

  public function form(array $form, FormStateInterface $form_state)
  {
    // On the site edit page, loadSelf();
    if ($this->entity->isNew() && \Drupal::routeMatch()->getRouteName() == 'site.edit') {
      $this->setEntity(SiteEntity::loadSelf());
    }

    $form = parent::form($form, $form_state); // TODO: Change the autogenerated stub
    $form['revision']['#type'] = 'value';
    $form['revision']['#value'] = TRUE;

    // @TODO: Allow plugins to alter the form.

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $entity = $this->getEntity();

    $message_arguments = ['%label' => $entity->toLink()->toString()];
    $logger_arguments = [
      '%label' => $entity->label(),
      'link' => $entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New site %label has been created.', $message_arguments));
        $this->logger('site')->notice('Created new site %label', $logger_arguments);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The site %label has been updated.', $message_arguments));
        $this->logger('site')->notice('Updated site %label.', $logger_arguments);
        break;
    }

    if (\Drupal::routeMatch()->getRouteName() == 'site.settings') {
      $form_state->setRedirect('site.history');
    }
    else {
      $form_state->setRedirect('entity.site.collection', [
        'site' => $this->entity->id(),
      ]);
    }

    return $result;
  }

}
