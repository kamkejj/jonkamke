<?php

namespace Drupal\Tests\features\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\features\ConfigurationItem;
use Drupal\Core\Config\InstallStorage;

/**
 * @group features
 */
class FeaturesAssignTest extends KernelTestBase {

  const PACKAGE_NAME = 'my_test_package';
  // Installed test feature package
  const TEST_INSTALLED_PACKAGE = 'test_mybundle_core';
  // Uninstalled test feature package
  const TEST_UNINSTALLED_PACKAGE = 'test_feature';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['features', 'node', 'system', 'user', self::TEST_INSTALLED_PACKAGE];

  /**
   * @var \Drupal\features\FeaturesManager
   */
  protected $featuresManager;

  /**
   * @var \Drupal\features\FeaturesAssigner
   */
  protected $assigner;

  /**
   * @var \Drupal\features\FeaturesBundleInterface
   */
  protected $bundle;

  /**
   * @todo Remove the disabled strict config schema checking.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig('features');
    $this->installConfig('system');

    $this->featuresManager = \Drupal::service('features.manager');
    $this->assigner = \Drupal::service('features_assigner');
    $this->bundle = $this->assigner->getBundle();

    // Turn off all assignment plugins.
    $this->bundle->setEnabledAssignments([]);

    // Start with an empty configuration collection.
    $this->featuresManager->setConfigCollection([]);
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentBaseType
   */
  public function testAssignBase() {
    $method_id = 'base';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);

    // Test the default options for the base assignment method.

    // Test node type assignments.
    // Declare the node_type entity 'article'.
    $this->addConfigurationItem('node.type.article', [], [
      'shortName' => 'article',
      'label' => 'Article',
      'type' => 'node_type',
      'dependents' => ['field.field.node.article.body'],
    ]);

    // Add a piece of dependent configuration.
    $this->addConfigurationItem('field.field.node.article.body', [], [
      'shortName' => 'node.article.body',
      'label' => 'Body',
      'type' => 'field_config',
      'dependents' => [],
    ]);

    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();

    $expected_package_names = ['article', 'user'];

    $this->assertEquals($expected_package_names, array_keys($packages), 'Expected packages not created.');

    $expected_config_items = [
      'node.type.article',
      'field.field.node.article.body',
    ];

    $this->assertEquals($expected_config_items, $packages['article']->getConfig(), 'Expected configuration items not present in article package.');
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentCoreType
   */
  public function testAssignCore() {
    $method_id = 'core';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);

    // Test the default options for the core assignment method.

    // Add a piece of configuration of a core type.
    $this->addConfigurationItem('field.storage.node.body', [], [
      'shortName' => 'node.body',
      'label' => 'node.body',
      'type' => 'field_storage_config',
      'dependents' => ['field.field.node.article.body'],
    ]);

    // Add a piece of configuration of a non-core type.
    $this->addConfigurationItem('field.field.node.article.body', [], [
      'shortName' => 'node.article.body',
      'label' => 'Body',
      'type' => 'field_config',
      'dependents' => [],
    ]);

    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();

    $expected_package_names = ['core'];

    $this->assertEquals($expected_package_names, array_keys($packages), 'Expected packages not created.');

    $this->assertTrue(in_array('field.storage.node.body', $packages['core']->getConfig(), 'Expected configuration item not present in core package.'));
    $this->assertFalse(in_array('field.field.node.article.body', $packages['core']->getConfig(), 'Unexpected configuration item present in core package.'));
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentDependency
   */
  public function testAssignDependency() {
    $method_id = 'dependency';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);

    // Test the default options for the base assignment method.

    // Test node type assignments.
    // Declare the node_type entity 'article'.
    $this->addConfigurationItem('node.type.article', [], [
      'shortName' => 'article',
      'label' => 'Article',
      'type' => 'node_type',
      'dependents' => ['field.field.node.article.body'],
    ]);

    // Add a piece of dependent configuration.
    $this->addConfigurationItem('field.field.node.article.body', [], [
      'shortName' => 'node.article.body',
      'label' => 'Body',
      'type' => 'field_config',
      'dependents' => [],
    ]);

    $this->featuresManager->initPackage(self::PACKAGE_NAME, 'My test package');
    $this->featuresManager->assignConfigPackage(self::PACKAGE_NAME, ['node.type.article']);

    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();

    $expected_package_names = [self::PACKAGE_NAME];

    $this->assertEquals($expected_package_names, array_keys($packages), 'Expected packages not created.');

    $expected_config_items = [
      'node.type.article',
      'field.field.node.article.body',
    ];

    $this->assertEquals($expected_config_items, $packages[self::PACKAGE_NAME]->getConfig(), 'Expected configuration items not present in article package.');
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentExclude
   */
  public function testAssignExclude() {
    $method_id = 'exclude';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);
    // Also enable Packages and Core plugins.
    $this->enableAssignmentMethod('packages', FALSE);
    $this->enableAssignmentMethod('core', FALSE);

    // Apply the bundle
    $this->bundle = $this->assigner->loadBundle('test_mybundle');

    $this->assigner->applyAssignmentMethod('packages');
    $packages = $this->featuresManager->getPackages();
    $this->assertNotEmpty($packages[self::TEST_INSTALLED_PACKAGE], 'Expected package not created.');

    // 1. When Required is set to True, config should stay with the module
    // First, test with "Required" set to True.
    $packages[self::TEST_INSTALLED_PACKAGE]->setRequired(true);
    $this->featuresManager->setPackages($packages);

    $this->assigner->applyAssignmentMethod('exclude');
    $this->assigner->applyAssignmentMethod('core');
    $this->assigner->applyAssignmentMethod('existing');
    $packages = $this->featuresManager->getPackages();

    $expected_config_items = [
      'core.date_format.long',
    ];

    $this->assertEquals($expected_config_items, $packages[self::TEST_INSTALLED_PACKAGE]->getConfig(), 'Expected configuration items not present in existing test_core package.');

    // 2. When Required is set to False, config still stays with module
    // Because the module is installed.
    $this->reset();
    $this->bundle = $this->assigner->loadBundle('test_mybundle');

    $this->assigner->applyAssignmentMethod('packages');
    $packages = $this->featuresManager->getPackages();
    $this->assertNotEmpty($packages[self::TEST_INSTALLED_PACKAGE], 'Expected test_mybundle_core package not created.');

    // Set "Required" set to False
    $packages[self::TEST_INSTALLED_PACKAGE]->setRequired(false);
    $this->featuresManager->setPackages($packages);

    $this->assigner->applyAssignmentMethod('exclude');
    $this->assigner->applyAssignmentMethod('core');
    $this->assigner->applyAssignmentMethod('existing');
    $packages = $this->featuresManager->getPackages();
    $this->assertFalse(array_key_exists('core', $packages), 'Core package should not be created.');

    $expected_config_items = [
      'core.date_format.long',
    ];
    $this->assertEquals($expected_config_items, $packages[self::TEST_INSTALLED_PACKAGE]->getConfig(), 'Expected configuration items not present in existing test_core package.');

    // 3. When Required is set to False and module is NOT installed,
    // Config stays with module if it doesn't match the current namespace
    $this->reset();
    // Load a bundle different from TEST_UNINSTALLED_PACKAGE
    $this->bundle = $this->assigner->loadBundle('test_mybundle');

    $this->assigner->applyAssignmentMethod('packages');
    $packages = $this->featuresManager->getPackages();
    $this->assertNotEmpty($packages[self::TEST_UNINSTALLED_PACKAGE], 'Expected test_feature package not created.');
    $this->assertNotEmpty($packages[self::TEST_INSTALLED_PACKAGE], 'Expected test_mybundle_core package not created.');

    // Mark package as uninstalled, set "Required" set to False
    $packages[self::TEST_UNINSTALLED_PACKAGE]->setRequired(false);
    $this->featuresManager->setPackages($packages);

    $this->assigner->applyAssignmentMethod('exclude');
    $this->assigner->applyAssignmentMethod('core');
    $this->assigner->applyAssignmentMethod('existing');
    $packages = $this->featuresManager->getPackages();
    $this->assertFalse(array_key_exists('core', $packages), 'Core package should not be created.');

    $expected_config_items = [
      'core.date_format.short',
      'system.cron',
    ];
    $this->assertEquals($expected_config_items, $packages[self::TEST_UNINSTALLED_PACKAGE]->getConfig(), 'Expected configuration items not present in existing test_feature package.');

    // 4. When Required is set to False and module is NOT installed,
    // Config is reassigned within modules that match the namespace.
    $this->reset();
    // Load the bundle used in TEST_UNINSTALLED_PACKAGE
    $this->bundle = $this->assigner->loadBundle('test');
    if (empty($this->bundle) || $this->bundle->isDefault()) {
      // Since we uninstalled the test_feature, we probably need to create
      // an empty "test" bundle
      $this->bundle = $this->assigner->createBundleFromDefault('test');
    }

    $this->assigner->applyAssignmentMethod('packages');
    $packages = $this->featuresManager->getPackages();
    $this->assertNotEmpty($packages[self::TEST_UNINSTALLED_PACKAGE], 'Expected test_feature package not created.');

    // Set "Required" set to False
    $packages[self::TEST_UNINSTALLED_PACKAGE]->setRequired(false);
    $this->featuresManager->setPackages($packages);

    $this->assigner->applyAssignmentMethod('exclude');
    $this->assigner->applyAssignmentMethod('core');
    $this->assigner->applyAssignmentMethod('existing');
    $packages = $this->featuresManager->getPackages();
    $this->assertNotEmpty($packages['core'], 'Expected Core package not created.');

    // Ensure "core" package is not confused with "test_core" module
    // Since we are in a bundle
    $this->assertEmpty($packages['core']->getExtension(), 'Autogenerated core package should not have an extension');

    // Core config should be reassigned from TEST_UNINSTALLED_PACKAGE into Core
    $expected_config_items = [
      'system.cron',
    ];
    $this->assertEquals($expected_config_items, $packages[self::TEST_UNINSTALLED_PACKAGE]->getConfig(), 'Expected configuration items not present in existing test_feature package.');
    $expected_config_items = [
      'core.date_format.short',
    ];
    $this->assertEquals($expected_config_items, $packages['core']->getConfig(), 'Expected configuration items not present in core package.');
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentExclude
   */
  public function testAssignExisting() {
    $method_id = 'existing';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);
    // Also enable Packages plugin.
    $this->enableAssignmentMethod('packages', FALSE);

    // First create the existing packages.
    $this->assigner->applyAssignmentMethod('packages');
    // Now move config into those existing packages.
    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();
    $this->assertNotEmpty($packages[self::TEST_INSTALLED_PACKAGE], 'Expected package not created.');
    $this->assertNotEmpty($packages[self::TEST_UNINSTALLED_PACKAGE], 'Expected package not created.');

    // Turn off any "required" option in package to let config get reassigned
    $package = $packages[self::TEST_INSTALLED_PACKAGE];
    $package->setRequired(true);

    $expected_config_items = [
      'core.date_format.long',
    ];
    $this->assertEquals($expected_config_items, $packages[self::TEST_INSTALLED_PACKAGE]->getConfig(), 'Expected configuration items not present in existing package.');

    $expected_config_items = [
      'core.date_format.short',
      'system.cron',
    ];
    $this->assertEquals($expected_config_items, $packages[self::TEST_UNINSTALLED_PACKAGE]->getConfig(), 'Expected configuration items not present in existing package.');
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentForwardDependency
   */
  public function testAssignForwardDependency() {
    $method_id = 'forward_dependency';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);

    // Add some configuration.
    // Two parent items.
    $this->addConfigurationItem('parent1', [], [
      'type' => 'node_type',
      'dependents' => ['grandparent'],
    ]);
    $this->addConfigurationItem('parent2', [], [
      'type' => 'node_type',
      'dependents' => [],
    ]);
    // Something that belongs to just one parent.
    $this->addConfigurationItem('child1', [], [
      'type' => 'node_type',
      'dependents' => ['parent1'],
    ]);
    // Something that belongs to both parents.
    $this->addConfigurationItem('child2', [], [
      'type' => 'node_type',
      'dependents' => ['parent1', 'parent2'],
    ]);
    // Something that indirectly belongs to parent1.
    $this->addConfigurationItem('grandchild', [], [
      'type' => 'node_type',
      'dependents' => ['child1'],
    ]);
    // A dependent, not a dependency.
    $this->addConfigurationItem('grandparent', [], [
      'type' => 'node_type',
      'dependents' => [],
    ]);
    // Something completely unrelated.
    $this->addConfigurationItem('stranger', [], [
      'type' => 'node_type',
      'dependents' => [],
    ]);

    $this->featuresManager->initPackage(self::PACKAGE_NAME, 'My test package');
    $this->featuresManager->assignConfigPackage(self::PACKAGE_NAME, ['parent1']);

    $other_package_name = 'other_package';
    $this->featuresManager->initPackage($other_package_name, 'Other package');
    $this->featuresManager->assignConfigPackage($other_package_name, ['parent2']);

    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();
    $expected_package_names = [self::PACKAGE_NAME, $other_package_name];
    sort($expected_package_names);
    $actual_package_names = array_keys($packages);
    sort($actual_package_names);
    $this->assertEquals($expected_package_names, $actual_package_names, 'Expected packages not created.');

    $expected_config_items = [
      'parent1',
      'child1',
      'grandchild',
    ];
    sort($expected_config_items);
    $actual_config_items = $packages[self::PACKAGE_NAME]->getConfig();
    sort($actual_config_items);

    $this->assertEquals($expected_config_items, $actual_config_items, 'Expected configuration items not present in article package.');
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentNamespace
   */
  public function testAssignNamespace() {
    $method_id = 'namespace';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);

    // Add some configuration.
    $this->addConfigurationItem('node.type.prefix_article', [], [
      'type' => 'node_type',
      'shortName' => 'prefix_article',
    ]);
    $this->addConfigurationItem('node.type.non_prefix_article', [], [
      'type' => 'node_type',
      'shortName' => 'non_prefix_article',
    ]);

    $this->featuresManager->initPackage('prefix', 'My test package');
    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();
    $this->assertNotEmpty($packages['prefix'], 'Expected package not created.');

    $expected_config_items = [
      'node.type.prefix_article',
    ];
    $this->assertEquals($expected_config_items, $packages['prefix']->getConfig(), 'Expected configuration items not present in prefix package.');
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentOptionalType
   */
  public function testAssignOptionalType() {
    $method_id = 'optional';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);

    $settings = [
      'types' => [
        'config' => ['image_style'],
      ],
    ];
    $this->bundle->setAssignmentSettings($method_id, $settings);

    // Add some configuration.
    $this->addConfigurationItem('node.type.article', [], [
      'type' => 'node_type',
    ]);
    $this->addConfigurationItem('image.style.test', [], [
      'type' => 'image_style',
    ]);
    $this->featuresManager->initPackage(self::PACKAGE_NAME, 'My test package');
    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();
    $this->assertNotEmpty($packages[self::PACKAGE_NAME], 'Expected package not created.');

    $config = $this->featuresManager->getConfigCollection();
    $this->assertNotEmpty($config['node.type.article'], 'Expected config not created.');
    $this->assertNotEmpty($config['image.style.test'], 'Expected config not created.');

    $this->assertNull($config['node.type.article']->getSubdirectory(), 'Expected package subdirectory not set to default.');
    $this->assertEquals($config['image.style.test']->getSubdirectory(), InstallStorage::CONFIG_OPTIONAL_DIRECTORY, 'Expected package subdirectory not set to optional.');
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentPackages
   */
  public function testAssignPackages() {
    $method_id = 'packages';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);

    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();

    $this->assertNotEmpty($packages[self::TEST_INSTALLED_PACKAGE], 'Expected package not created.');
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentProfile
   */
  public function testAssignProfile() {
    $method_id = 'profile';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);

    // Add some configuration.
    $this->addConfigurationItem('shortcut.myshortcut', [], [
      'type' => 'shortcut_set',
    ]);
    $this->addConfigurationItem('node.type.article', [], [
      'type' => 'node_type',
    ]);
    $this->addConfigurationItem('image.style.test', [], [
      'type' => 'image_style',
    ]);
    $this->addConfigurationItem('system.cron', [], [
      'type' => 'simple',
    ]);
    $this->bundle = $this->assigner->createBundleFromDefault('myprofile');
    $this->bundle->setProfileName('myprofile');
    $this->bundle->setIsProfile(TRUE);

    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();
    $this->assertNotEmpty($packages['myprofile'], 'Expected package not created.');

    $expected_config_items = [
      'shortcut.myshortcut',
      'system.cron',
      'system.theme',
    ];
    $this->assertEquals($expected_config_items, $packages['myprofile']->getConfig(), 'Expected configuration items not present in package.');
  }

  /**
   * @covers Drupal\features\Plugin\FeaturesAssignment\FeaturesAssignmentSiteType
   */
  public function testAssignSiteType() {
    $method_id = 'site';

    // Enable the method.
    $this->enableAssignmentMethod($method_id);

    // Test the default options for the site assignment method.

    // Add a piece of configuration of a site type.
    $this->addConfigurationItem('filter.format.plain_text', [], [
      'shortName' => 'plain_text',
      'label' => 'Plain text',
      'type' => 'filter_format',
    ]);

    // Add a piece of configuration of a non-site type.
    $this->addConfigurationItem('field.field.node.article.body', [], [
      'shortName' => 'node.article.body',
      'label' => 'Body',
      'type' => 'field_config',
      'dependents' => [],
    ]);

    $this->assigner->applyAssignmentMethod($method_id);

    $packages = $this->featuresManager->getPackages();

    $expected_package_names = ['site'];

    $this->assertEquals($expected_package_names, array_keys($packages), 'Expected packages not created.');

    $this->assertTrue(in_array('filter.format.plain_text', $packages['site']->getConfig(), 'Expected configuration item not present in site package.'));
    $this->assertFalse(in_array('field.field.node.article.body', $packages['site']->getConfig(), 'Unexpected configuration item present in site package.'));
  }

  /**
   * Enables a specified assignment method.
   *
   * @param string $method_id
   *   The ID of an assignment method.
   * @param bool $exclusive
   *   (optional) Whether to set the method as the only enabled method.
   *   Defaults to TRUE.
   */
  protected function enableAssignmentMethod($method_id, $exclusive = TRUE) {
    if ($exclusive) {
      $this->bundle->setEnabledAssignments([$method_id]);
    }
    else {
      $enabled = array_keys($this->bundle->getEnabledAssignments());
      $enabled[] = $method_id;
      $this->bundle->setEnabledAssignments($enabled);
    }
  }

  /**
   * Adds a configuration item.
   *
   * @param string $name
   *   The config name.
   * @param array $data
   *   The config data.
   * @param array $properties
   *   (optional) Additional properties set on the object.
   */
  protected function addConfigurationItem($name, array $data = [], array $properties = []) {
    $config_collection = $this->featuresManager->getConfigCollection();
    $config_collection[$name] = new ConfigurationItem($name, $data, $properties);
    $this->featuresManager->setConfigCollection($config_collection);
  }

  /**
   * Reset the config to reapply assignment plugins
   */
  protected function reset() {
    $this->assigner->reset();
    // Start with an empty configuration collection.
    $this->featuresManager->setConfigCollection([]);
  }
}
