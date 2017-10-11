<?php

namespace Drush\Commands;

use Drupal\features\FeaturesAssignerInterface;
use Drupal\features\FeaturesManagerInterface;
use Drush\Utils\StringUtils;

/**
 * Drush commands for Features.
 */
class FeaturesCommands extends DrushCommands {

  /**
   * The features_assigner service.
   *
   * @var \Drupal\features\FeaturesAssignerInterface
   */
  protected $assigner;

  /**
   * The features.manager service.
   *
   * @var \Drupal\features\FeaturesManagerInterface
   */
  protected $manager;

  /**
   * FeaturesCommands constructor.
   *
   * @param \Drupal\features\FeaturesAssignerInterface $assigner
   * @param \Drupal\features\FeaturesManagerInterface $manager
   */
  public function __construct(
    FeaturesAssignerInterface $assigner,
    FeaturesManagerInterface $manager
  ) {
    $this->assigner = $assigner;
    $this->manager = $manager;
  }

  /**
   * Applies global options for Features drush commands.
   *
   * The option --name="bundle_name" sets the bundle namespace.
   *
   * @return \Drupal\features\FeaturesAssignerInterface
   */
  protected function featuresOptions(array $options) {
    $bundleName = $this->getOption($options, 'bundle');
    if (!empty($bundleName)) {
      $bundle = $this->assigner->applyBundle($bundleName);
      if ($bundle->getMachineName() !== $bundleName) {
        $this->logger()->warning('Bundle {name} not found. Using default.', [
          'name' => $bundleName,
        ]);
      }
    }
    else {
      $this->assigner->assignConfigPackages();
    }
    return $this->assigner;
  }

  protected function getOption(array $options, $name, $default = NULL) {
    return isset($options[$name])
      ? $options[$name]
      : $default;
  }

  /**
   * Display current Features settings.
   *
   * @param string $keys
   *   A possibly empty, comma-separated, list of config information to display.
   *
   * @command features:status
   *
   * @option bundle Use a specific bundle namespace.
   *
   * @aliases fs,features-status
   */
  public function status($keys = NULL, array $options = ['bundle' => null]) {
    $this->featuresOptions($options);

    $currentBundle = $this->assigner->getBundle();
    $export_settings = $this->manager->getExportSettings();
    $methods = $this->assigner->getEnabledAssigners();
    if ($currentBundle->isDefault()) {
      $this->output()->writeln(dt('Current bundle: none'));
    }
    else {
      $this->output()->writeln(dt('Current bundle: @name (@machine_name)', [
        '@name' => $currentBundle->getName(),
        '@machine_name' => $currentBundle->getMachineName(),
      ]));
    }
    $this->output()->writeln(dt('Export folder: @folder', [
      '@folder' => $export_settings['folder'],
    ]));
    $this->output()->writeln(dt('The following assignment methods are enabled:'));
    $this->output()->writeln(dt('  @methods', [
      '@methods' => implode(', ', array_keys($methods)),
    ]));

    if (!empty($keys)) {
      $config = $this->manager->getConfigCollection();
      $keys = StringUtils::csvToArray($keys);
      if (count($keys) > 1) {
        $this->output()->writeln(print_r(array_keys($config), TRUE));
      }
      else {
        $this->output()->writeln($config[$keys[0]], TRUE);
      }
    }
  }

  /**
   * Display a list of all existing features and packages available to be generated.  If a package name is provided as an argument, then all of the configuration objects assigned to that package will be listed.
   *
   * @command features:list:packages
   * @param $Package The package to list. Optional; if specified, lists all configuration objects assigned to that package. If no package is specified, lists all of the features.
   * @option bundle Use a specific bundle namespace.
   * @usage drush features-list-packages
   *   Display a list of all existing featurea and packages available to be generated.
   * @usage drush features-list-packages 'example_article'
   *   Display a list of all configuration objects assigned to the 'example_article' package.
   * @aliases fl,features-list-packages
   */
  public function listPackages($Package, $options = ['bundle' => null])
  {
      // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
      // legacy command.
  }

  /**
   * Import module config from all installed features.
   *
   * @command features:import:all
   * @option bundle Use a specific bundle namespace.
   * @usage drush features-import-all
   *   Import module config from all installed features.
   * @aliases fra,fia,fim-all,features-import-all
   */
  public function importAll($options = ['bundle' => null])
  {
      // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
      // legacy command.
  }

  /**
   * Export the configuration on your site into a custom module.
   *
   * @command features:export
   * @param $Package A space delimited list of features to export.
   * @option add-profile Package features into an install profile.
   * @option bundle Use a specific bundle namespace.
   * @usage drush features-export
   *   Export all available packages.
   * @usage drush features-export example_article example_page
   *   Export the example_article and example_page packages.
   * @usage drush features-export --add-profile
   *   Export all available packages and add them to an install profile.
   * @aliases fex,fu,fua,fu-all,features-export
   */
  public function export($Package, $options = ['add-profile' => null, 'bundle' => null])
  {
      // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
      // legacy command.
  }

  /**
   * Add a config item to a feature package.
   *
   * @command features:add
   * @param $Feature Feature package to export and add config to.
   * @param $Components Patterns of config to add, see features-components for the format of patterns.
   * @option bundle Use a specific bundle namespace.
   * @aliases fa,fe,features-add
   */
  public function add($Feature, $Components, $options = ['bundle' => null])
  {
      // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
      // legacy command.
  }

  /**
   * List features components.
   *
   * @command features:components
   * @param $Patterns The features components type to list. Omit this argument to list all components.
   * @option exported Show only components that have been exported.
   * @option not-exported Show only components that have not been exported.
   * @option bundle Use a specific bundle namespace.
   * @aliases fc,features-components
   */
  public function components($Patterns, $options = ['exported' => null, 'not-exported' => null, 'bundle' => null])
  {
      // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
      // legacy command.
  }

  /**
   * Show the difference between the active config and the default config stored in a feature package.
   *
   * @command features:diff
   * @param $Feature The feature in question.
   * @option ctypes Comma separated list of component types to limit the output to. Defaults to all types.
   * @option lines Generate diffs with <n> lines of context instead of the usual two.
   * @option bundle Use a specific bundle namespace.
   * @aliases fd,features-diff
   */
  public function diff($Feature, $options = ['ctypes' => null, 'lines' => null, 'bundle' => null])
  {
      // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
      // legacy command.
  }

  /**
   * Import a module config into your site.
   *
   * @command features:import
   * @param $Feature A space delimited list of features or feature:component pairs to import.
   * @option force Force import even if config is not overridden.
   * @option bundle Use a specific bundle namespace.
   * @usage drush features-import foo:node.type.page foo:taxonomy.vocabulary.tags bar
   *   Import node and taxonomy config of feature "foo". Import all config of feature "bar".
   * @aliases fim,fr,features-import
   */
  public function import($Feature, $options = ['force' => null, 'bundle' => null])
  {
      // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
      // legacy command.
  }


}
