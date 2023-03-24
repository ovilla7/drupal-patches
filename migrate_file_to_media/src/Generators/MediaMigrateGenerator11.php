<?php

namespace Drupal\migrate_file_to_media\Generators;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use DrupalCodeGenerator\Command\ModuleGenerator;
use DrupalCodeGenerator\ValidatorTrait;

/**
 * Automatically generates yml files for migrations.
 */
class MediaMigrateGenerator11 extends ModuleGenerator {

  protected string $name = 'migrate_file_to_media:media_migration_generator11';

  protected string $alias = 'mf2m_media_11';

  protected string $description = 'Generates yml for File to Media Migration';

  protected string $templatePath = __DIR__;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars): void {
    $this->collectDefault($vars);

    $vars['plugin_id'] = $this->ask('Plugin ID', 'Example', '::validatePluginId');
    $vars['migration_group'] = $this->ask('Migration Group', 'media');
    $vars['entity_type'] = $this->ask('Entity Type', 'node');
    $vars['source_bundle'] = $this->ask('Source Bundle', '');
    $vars['source_field_name'] = $this->ask('Source Field Names (comma separated)', 'field_image');
    $vars['target_bundle'] = $this->ask('Target Media Type', 'image');
    $vars['target_field'] = $this->ask('Target Media Type Field', 'field_media_image');
    $vars['lang_code'] = $this->ask('Language Code', 'en');
    $vars['translation_languages'] = $this->ask('Translation languages (comma separated)', 'none');

    $vars['translation_language'] = NULL;

    if ($vars['translation_languages']) {
      $translation_languages = array_map('trim', array_unique(explode(',', strtolower($vars['translation_languages']))));
      // Validate the default language was not included in the translation languages
      foreach ($translation_languages as $key => $language) {
        if ($language == $vars['lang_code']) {
          unset($translation_languages[$key]);
        }
      }
      $vars['translation_languages'] = $translation_languages;
    }

    if ($vars['source_field_name']) {
      $vars['source_field_name'] = array_map('trim', explode(',', strtolower($vars['source_field_name'])));
    }

    // ID Key for the entity type (nid for node, id for paragraphs).
    $entityType = $this->entityTypeManager->getDefinition($vars['entity_type']);
    $vars['id_key'] = $entityType->getKey('id');

    $this->addFile('config/install/migrate_plus.migration.{plugin_id}_step1.yml')
      ->template('media-migration-step1.yml.twig')
      ->vars($vars);

    // Validates if there are translation languages and includes a new variable to add translations or not
    $vars['has_translation'] = (count($vars['translation_languages']) > 0 && $vars['translation_languages'][0] != 'none');
    $this->addFile('config/install/migrate_plus.migration.{plugin_id}_step2.yml')
      ->template('media-migration-step2.yml.twig')
      ->vars($vars);

    foreach ($vars['translation_languages'] as $language) {
      if ($language == 'none' || $language == $vars['lang_code']) {
        continue;
      }
      $vars['source_lang_code'] = $vars['lang_code'];
      $vars['translation_language'] = $vars['lang_code'] = $language;

      $this->addFile("config/install/migrate_plus.migration.{plugin_id}_step1_{$language}.yml")
        ->template('media-migration-step1.yml.twig')
        ->vars($vars);
    }
  }

  /**
   * Plugin id validator.
   */
  public static function validatePluginId($value) {
    // Check the length of the global table name prefix.
    $db_info = array_shift(Database::getConnectionInfo());
    $db_info = Database::parseConnectionInfo($db_info);

    $prefix = strlen($db_info['prefix']);
    if (!empty($db_info['prefix']['default'])) {
      $prefix = strlen($db_info['prefix']['default']);
    }
    $max_length = 42 - $prefix;

    // Check if the plugin machine name is valid.
    ValidatorTrait::validateMachineName($value);

    // Check the maximum number of characters for the migration name. The name
    // should not exceed 42 characters to prevent mysql table name limitation of
    // 64 characters for the table: migrate_message_[PLUGIN_ID]_step1
    // or migrate_message_[PLUGIN_ID]_step2.
    if (strlen($value) > $max_length) {
      throw new \UnexpectedValueException('The plugin id should not exceed more than ' . strval($max_length) . ' characters.');
    }
    return $value;
  }

}
