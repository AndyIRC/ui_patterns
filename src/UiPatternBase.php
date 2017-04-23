<?php

namespace Drupal\ui_patterns;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\ui_patterns\Definition\PatternDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UiPatternBase.
 *
 * @package Drupal\ui_patterns
 */
abstract class UiPatternBase extends PluginBase implements UiPatternInterface, ContainerFactoryPluginInterface {

  /**
   * Prefix for locally defined libraries.
   */
  const LIBRARY_PREFIX = 'ui_patterns';

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * Typed data manager service.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedDataManager;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * UiPatternsManager constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $root, TypedDataManager $typed_data_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->root = $root;
    $this->typedDataManager = $typed_data_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('app.root'),
      $container->get('typed_data_manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsAsOptions() {
    $options = [];
    foreach ($this->getPluginDefinition()['fields'] as $field) {
      $options[$field['name']] = $field['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getThemeImplementation() {
    $definition = $this->getPluginDefinition();
    $item = [];
    $item += $this->processVariables($definition);
    $item += $this->processUseProperty($definition);
    return [
      $definition['theme hook'] => $item,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraryDefinitions() {
    // @codingStandardsIgnoreStart
    $libraries = [];
    $definition = $this->getPluginDefinition();

    // Get only locally defined libraries.
    $items = array_filter($definition['libraries'], function ($library) {
      return is_array($library);
    });

    // Attach pattern base path to assets.
    if (!empty($definition['base path'])) {
      $base_path = str_replace($this->root, '', $definition['base path']);
      $this->processLibraries($items, $base_path);
    }

    // Produce final libraries array.
    $id = $definition['id'];
    array_walk($items, function ($value) use (&$libraries, $id) {
      $libraries[$id . '.' . key($value)] = reset($value);
    });

    // @codingStandardsIgnoreEnd
    return $libraries;
  }

  /**
   * Process libraries.
   *
   * @param array $libraries
   *    Libraries array.
   * @param string $base_path
   *    Pattern base path.
   * @param string $parent
   *    Item parent set in previous recursive iteration, if any.
   */
  protected function processLibraries(array &$libraries, $base_path, $parent = '') {
    $parents = ['js', 'base', 'layout', 'component', 'state', 'theme'];
    $_libraries = $libraries;
    foreach ($_libraries as $name => $values) {
      $is_asset = in_array($parent, $parents, TRUE);
      $is_external = isset($values['type']) && $values['type'] == 'external';
      if ($is_asset && !$is_external) {
        $libraries[$base_path . DIRECTORY_SEPARATOR . $name] = $values;
        unset($libraries[$name]);
      }
      elseif (!$is_asset) {
        $this->processLibraries($libraries[$name], $base_path, $name);
      }
    }
  }

  /**
   * Process 'use' definition property.
   *
   * @param \Drupal\ui_patterns\Definition\PatternDefinition $definition
   *    Pattern definition array.
   *
   * @return array
   *    Processed hook definition portion.
   */
  protected function processUseProperty(PatternDefinition $definition) {
    $return = [];
    if ($definition->hasUse()) {
      $return = [
        'path' => $this->moduleHandler->getModule('ui_patterns')->getPath() . '/templates',
        'template' => 'patterns-use-wrapper',
      ];
    }
    return $return;
  }

  /**
   * Process theme variables.
   *
   * @param \Drupal\ui_patterns\Definition\PatternDefinition $definition
   *    Pattern definition array.
   *
   * @return array
   *    Processed hook definition portion.
   */
  protected function processVariables(PatternDefinition $definition) {
    $return = [];
    foreach ($definition->getFields() as $field) {
      $return['variables'][$field->getName()] = NULL;
    }
    $return['variables']['attributes'] = [];
    $return['variables']['context'] = [];
    $return['variables']['use'] = '';
    return $return;
  }

}
