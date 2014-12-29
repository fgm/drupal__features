<?php

/**
 * @file
 * Contains \Drupal\config_packager\Plugin\ConfigPackagerGeneration\ConfigPackagerGenerationArchive.
 */

namespace Drupal\config_packager\Plugin\ConfigPackagerGeneration;

use Drupal\config_packager\ConfigPackagerGenerationMethodBase;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class for generating a compressed archive of packages.
 *
 * @Plugin(
 *   id = \Drupal\config_packager\Plugin\ConfigPackagerGeneration\ConfigPackagerGenerationArchive::METHOD_ID,
 *   weight = -2,
 *   name = @Translation("Archive"),
 *   description = @Translation("Generate packages and optional profile as a compressed archive for download."),
 * )
 */
class ConfigPackagerGenerationArchive extends ConfigPackagerGenerationMethodBase {

  /**
   * The package generation method id.
   */
  const METHOD_ID = 'archive';

  /**
   * {@inheritdoc}
   */
  public function prepare($add_profile = FALSE, array $packages = array()) {

  }

  /**
   * {@inheritdoc}
   */
  public function generate($add_profile = FALSE, array $packages = array()) {
    // If no packages were specified, get all packages.
    if (empty($packages)) {
      $packages = $this->configPackagerManager->getPackages();
    }

    $return = [];

    // Remove any previous version of the exported archive.
    $machine_name = $this->configFactory->get('config_packager.settings')->get('profile.machine_name');
    $archive_name = file_directory_temp() . '/' . $machine_name . '.tar.gz';
    if (file_exists($archive_name)) {
      file_unmanaged_delete($archive_name);
    }

    $archiver = new ArchiveTar($archive_name);

    if ($add_profile) {
      $profile = $this->configPackagerManager->getProfile();
      $this->archivePackage($return, $profile, $archiver);
    }

    // Add package files.
    foreach ($packages as $package) {
      $this->archivePackage($return, $package, $archiver);
    }

    return $return;
  }

  /**
   * Write a package or profile's files to an archive.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   * @param ArchiveTar $archiver
   *   The archiver.
   */
  protected function archivePackage(array &$return, array $package, ArchiveTar $archiver) {
    $success = TRUE;
    foreach ($package['files'] as $file) {
      try {
        $this->archiveFile($archiver, $file);
      }
      catch(\Exception $exception) {
        $this->archiveFailure($return, $package, $exception);
        $success = FALSE;
        break;
      }
    }
    if ($success) {
      $this->archiveSuccess($return, $package);
    }
  }

  /**
   * Register a successful package or profile archive operation.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   */
  protected function archiveSuccess(array &$return, array $package) {
    $type = $package['type'] == 'module' ? $this->t('Package') : $this->t('Profile');
    $return[] = [
      'success' => TRUE,
      // Archive writing doesn't merit a message, and if done through the UI
      // would appear on the subsequent page load.
      'display' => FALSE,
      'message' => $this->t('!type @package written to archive.'),
      'variables' => [
        '!type' => $type,
        '@package' => $package['name']
      ],
    ];
  }

  /**
   * Register a failed package or profile archive operation.
   *
   * @param array &$return
   *   The return value, passed by reference.
   * @param array $package
   *   The package or profile.
   * @param Exception $exception
   *   The exception object.
   */
  protected function archiveFailure(&$return, array $package, Exception $exception) {
    $type = $package['type'] == 'package' ? $this->t('Package') : $this->t('Profile');
    $return[] = [
      'success' => FALSE,
      // Archive writing doesn't merit a message, and if done through the UI
      // would appear on the subsequent page load.
      'display' => FALSE,
      'message' => $this->t('!type @package not written to archive. Error: @error.'),
      'variables' => [
        '!type' => $type,
        '@package' => $package['name'],
        '@error' => $exception->getMessage()
      ],
    ];
  }

  /**
   * Write a file to the file system, creating its directory as needed.
   *
   * @param ArchiveTar $archiver
   *   The archiver.
   * @param array $file
   *   Array with keys 'filename' and 'string'.
   *
   * @throws Exception
   */
  protected function archiveFile(ArchiveTar $archiver, array $file) {
    if ($archiver->addString($file['filename'], $file['string']) === FALSE) {
      throw new \Exception($this->t('Failed to archive file @filename.', ['@filename' => basename($file['filename'])]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function exportFormSubmit(array &$form, FormStateInterface $form_state) {
    // Redirect to the archive file download.
    $form_state->setRedirect('config_packager.export_download');
  }

}
