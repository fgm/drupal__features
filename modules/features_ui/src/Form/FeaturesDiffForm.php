<?php

/**
 * @file
 * Contains \Drupal\features_ui\Form\FeaturesDiffForm.
 */

namespace Drupal\features_ui\Form;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Xss;
use Drupal\features\FeaturesAssignerInterface;
use Drupal\features\FeaturesGeneratorInterface;
use Drupal\features\FeaturesManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Diff\DiffFormatter;
use Drupal\config_update\ConfigRevertInterface;
use Drupal\config_update\ConfigDiffInterface;

/**
 * Defines the features differences form.
 */
class FeaturesDiffForm extends FormBase {

  /**
   * The features manager.
   *
   * @var array
   */
  protected $featuresManager;

  /**
   * The package assigner.
   *
   * @var array
   */
  protected $assigner;

  /**
   * The config differ.
   *
   * @var \Drupal\config_update\ConfigDiffInterface
   */
  protected $configDiff;

  /**
   * The diff formatter.
   *
   * @var \Drupal\Core\Diff\DiffFormatter
   */
  protected $diffFormatter;

  /**
   * The config reverter.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configRevert;

  /**
   * Constructs a FeaturesDiffForm object.
   *
   * @param \Drupal\features\FeaturesManagerInterface $features_manager
   *   The features manager.
   */
  public function __construct(FeaturesManagerInterface $features_manager, FeaturesAssignerInterface $assigner,
                              ConfigDiffInterface $config_diff, DiffFormatter $diff_formatter,
                              ConfigRevertInterface $config_revert) {
    $this->featuresManager = $features_manager;
    $this->assigner = $assigner;
    $this->configDiff = $config_diff;
    $this->diffFormatter = $diff_formatter;
    $this->configRevert = $config_revert;
    $this->diffFormatter->show_header = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('features.manager'),
      $container->get('features_assigner'),
      $container->get('config_update.config_diff'),
      $container->get('diff.formatter'),
      $container->get('features.config_update')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'features_diff_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $featurename = '') {
    $current_bundle = $this->assigner->applyBundle();
    $packages = $this->featuresManager->getPackages();
    $form = array();

    $machine_name = '';
    if (!empty($featurename) && empty($packages[$featurename])) {
      drupal_set_message(t('Feature !name does not exist.', array('!name' => $featurename)), 'error');
      return array();
    }
    elseif (!empty($featurename)) {
      $machine_name = $packages[$featurename]['machine_name'];
      $packages = array($packages[$featurename]);
    }
    else {
      $packages = $this->featuresManager->filterPackages($packages, $current_bundle->getMachineName());
    }

    $header = array(
      'row' => array('data' => !empty($machine_name)
        ? t('Differences in !name', array('!name' => $machine_name))
        : ($current_bundle->isDefault() ? t('All differences') : t('All differences in bundle: !bundle', array('!bundle' => $current_bundle->getName()))),
      ),
    );

    $options = array();
    foreach ($packages as $package_name => $package) {
      if ($package['status'] != FeaturesManagerInterface::STATUS_NO_EXPORT) {
        $overrides = $this->featuresManager->detectOverrides($package, TRUE);
        if (!empty($overrides)) {
          $options += array(
            $package['machine_name'] => array(
              'row' => array(
                'data' => array(
                  '#type' => 'html_tag',
                  '#tag' => 'h2',
                  '#value' => String::checkPlain($package['name']),
                ),
              ),
              '#attributes' => array(
                'class' => 'features-diff-header',
              )
            ),
          );
          $options += $this->diffOutput($package, $overrides);
        }
      }
    }

    $form['diff'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#attributes' => array('class' => array('features-diff-listing')),
      '#empty' => t('No differences exist in exported features.'),
    );

    $form['actions'] = array('#type' => 'actions', '#tree' => TRUE);
    $form['actions']['revert'] = array(
      '#type' => 'submit',
      '#value' => t('Import changes'),
    );
    $form['actions']['help'] = array(
      '#markup' =>  t('Import the selected changes above into the active configuration.'),
    );

    $form['#attached']['library'][] = 'system/diff';
    $form['#attached']['library'][] = 'features_ui/drupal.features_ui.admin';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->assigner->assignConfigPackages();
    $config = $this->featuresManager->getConfigCollection();
    $items = array_filter($form_state->getValue('diff'));
    if (empty($items)) {
      drupal_set_message('No configuration was selected for import.', 'warning');
      return;
    }
    foreach ($items as $item) {
      if (isset($config[$item])) {
        $this->configRevert->revert($config[$item]['type'], $config[$item]['name_short']);
        drupal_set_message(t('Imported !name', array('!name' => $item)));
      }
    }
  }

  /**
   * Return a form element for the given overrides
   * @param $package
   * @param $overrides
   * @return array
   */
  protected function diffOutput($package, $overrides) {
    $element = array();

    $header = array(
      array('data' => '', 'class' => 'diff-marker'),
      array('data' => t('Active site config'), 'class' => 'diff-context'),
      array('data' => '', 'class' => 'diff-marker'),
      array('data' => t('Feature code config'), 'class' => 'diff-context'),
    );

    foreach ($overrides as $name) {
      $rows[] = array(array('data' => $name, 'colspan' => 4, 'header' => TRUE));

      $active = $this->featuresManager->getActiveStorage()->read($name);
      $extension = $this->featuresManager->getExtensionStorage()->read($name);
      if (empty($extension)) {
        $details = array(
          '#markup' => t('Dependency detected in active config but not exported to the feature.'),
        );
      }
      else {
        $diff = $this->configDiff->diff($extension, $active);
        $details = array(
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $this->diffFormatter->format($diff),
          '#attributes' => array('class' => array('diff', 'features-diff')),
        );
      }
      $element[$name] = array(
        'row' => array(
          'data' => array(
            '#type' => 'details',
            '#title' => String::checkPlain($name),
            '#open' => TRUE,
            '#description' => array(
              'data' => $details,
            ),
          ),
        ),
        '#attributes' => array(
          'class' => 'diff-' . $package['machine_name'],
        )
      );
    }

    return $element;
  }

}
