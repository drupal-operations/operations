<?php

namespace Drupal\site\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\options\Plugin\Field\FieldType\ListItemBase;
use Drupal\site\Entity\SiteDefinition;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;

/**
 * Site Definition form.
 *
 * @property \Drupal\site\SiteEntityIn $entity
 */
class SiteDefinitionForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if (!$this->entity->id()) {
      $this->setEntity(SiteDefinition::load('self'));
    }
    $form = parent::form($form, $form_state);
    $form['site_title'] = [
      '#type' => 'item',
      '#title' => $this->t('Site Title'),
      '#markup' => $this->entity->get('site_title'),
      '#description' => $this->t('The title of this site. Edit on <a href=":url">Basic site settings</a> page.', [
          ':url' => Url::fromRoute('system.site_information_settings')
              ->setOption('query', \Drupal::destination()->getAsArray())
              ->toString(),
      ]),
    ];
    $form['site_uuid'] = [
      '#type' => 'item',
      '#title' => $this->t('Site UUID'),
      '#markup' => $this->entity->get('site_uuid'),
      '#description' => $this->t('The UUID of this site.'),
    ];
    $form['site_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('Site URI'),
      '#markup' => $this->entity->get('site_uri'),
      '#description' => $this->t('The URI of this site.'),
    ];
    $form['id'] = [
      '#value' => $this->entity->id(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Description of the site definition.'),
    ];

    $form['configs_load'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration items to load.'),
      '#default_value' => implode(PHP_EOL, $this->entity->get('configs_load')),
      '#description' => $this->t('A list of configuration items that should be made available in the Site entity. Use the main config key, or get a specific item by separating with a color. For example: <pre>system.site</pre> or <pre>core.extension:theme</pre>.'),
    ];
    $form['state_factors'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('State Factors'),
      '#description' => $this->t('Choose the factors that will affect site state.'),
      '#options' => [
        'system' => $this->t('Status Report <a href=":url">view</a>', [
          ':url' => '/admin/reports/status',
        ])
      ],
      '#default_value' => $this->entity->get('state_factors') ?? [],
    ];

    $form['settings'] = [
      '#tree' => true,
      '#weight' => 10,
    ];

    $form['settings']['site_entity'] = [
      '#title' => $this->t('Historical Site Data'),
      '#type' => 'details',
      '#open' => TRUE,
    ];

    $intervals = [60, 900, 1800, 3600, 7200, 10800, 21600, 32400, 43200, 64800, 86400, 172800, 259200, 604800, 1209600, 2419200];
    $period = array_map([\Drupal::service('date.formatter'), 'formatInterval'], array_combine($intervals, $intervals));
    $options = [0 => t('Never')] + $period;
    $settings = $this->getEntity()->get('settings');

    $form['settings']['site_entity']['save_interval'] = [
        '#type' => 'select',
        '#title' => t('Save site data every'),
        '#description' => t('Regularly save site data for later review.'),
        '#default_value' => $settings['site_entity']['save_interval'] ?? [0],
        '#options' => $options,
    ];
    $form['settings']['site_entity']['send_interval'] = [
        '#type' => 'select',
        '#title' => t('Send site data every'),
        '#description' => t('Regularly send site data to the configured remote server.'),
        '#default_value' => $settings['site_entity']['send_interval']  ?? [0],
        '#options' => $options,
    ];

    $form['settings']['site_entity']['send_destinations'] = [
      '#title' => $this->t('Site Data Destinations'),
      '#description' => $this->t('Enter the URLs to POST site data to, one per line. To connect to a Site Manager instance, use the path "https://site_manager_url/api/site?api_key=xyz".'),
      '#default_value' => $settings['site_entity']['send_destinations']  ?? "",
      '#type' => 'textarea',
      '#states' => [
          'invisible' => [
              ':input[name="settings[site_entity][send_interval]"]' => ['value' => 0]
          ]
      ]
    ];
    return $form;
  }

  public function buildEntity(array $form, FormStateInterface $form_state) {
    $config_load = $form_state->getValue('configs_load');
    if (is_string($config_load)) {
      $config_load = explode(PHP_EOL, $config_load);
    }
    $form_state->setValue('configs_load', $config_load);
    return parent::buildEntity($form, $form_state); // TODO: Change the autogenerated stub
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $settings = $form_state->getValue('settings');

    // Validate URLs
    $urls = explode("\n", $settings['site_entity']['send_destinations']);
    foreach (array_filter($urls) as $url) {
      $url = trim($url);
      try {
        $client = new Client([
          'base_url' => $url,
          'allow_redirects' => TRUE,
        ]);
        $payload = $this->getEntity()->toArray();
        $response = $client->post($url, [
          'headers' => [
            'Accept' => 'application/json',
          ],
          'json' => [],
        ]);

        \Drupal::messenger()->addStatus($this->t("Successfully connected to :url: :code", [
            ':url' => $url,
            ':code' => $response->getStatusCode(),
        ]));

      } catch (GuzzleException $e) {
        $form_state->setErrorByName('send_destinations', $e->getMessage());

      } catch (\Exception $e) {

        $form_state->setErrorByName('send_destinations', $e->getMessage());
      }

    }

    parent::validateForm($form, $form_state); // TODO: Change the autogenerated stub
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];

    $message = $result == SAVED_NEW
      ? $this->t('Created new site definition %label.', $message_args)
      : $this->t('Updated site definition %label.', $message_args);

    $this->messenger()->addStatus($message);

    $form_state->setRedirectUrl(Url::fromRoute('site.advanced'));
    return $result;
  }

}