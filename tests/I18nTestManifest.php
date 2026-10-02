<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ClassManifest;
use SilverStripe\Core\Manifest\ClassLoader;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Core\Manifest\ModuleManifest;
use SilverStripe\i18n\i18n;
use SilverStripe\i18n\Messages\MessageProvider;
use SilverStripe\i18n\Messages\Symfony\ModuleYamlLoader;
use SilverStripe\i18n\Messages\Symfony\SymfonyMessageProvider;
use SilverStripe\i18n\Messages\YamlReader;
use SilverStripe\View\SSViewer;
use SilverStripe\TemplateEngine\ScopeManager;
use SilverStripe\View\ThemeResourceLoader;
use SilverStripe\View\ThemeManifest;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * Helper trait for bootstrapping test manifest for i18n tests
 */
trait I18nTestManifest
{
    /**
     * Fake webroot with a single module /i18ntestmodule which contains some files with _t() calls.
     */
    protected string $alternateBasePath = '';

    /**
     * Number of test manifests
     */
    protected int $manifests = 0;

    /**
     * Number of module manifests
     */
    protected int $moduleManifests = 0;

    protected ?ThemeResourceLoader $oldThemeResourceLoader = null;

    protected ?string $originalLocale = null;

    public function setupManifest(): void
    {
        // force ScopeManager to cache global template vars before we switch to the
        // test-project class manifest (since it will lose visibility of core classes)
        $scopeManager = new ScopeManager(null);
        unset($scopeManager);

        // Switch to test manifest
        $s = DIRECTORY_SEPARATOR;
        $this->alternateBasePath = __DIR__ . $s . 'i18nTest' . $s . "_fakewebroot";
        Director::config()->set('alternate_base_folder', $this->alternateBasePath);

        // New module manifest
        $moduleManifest = new ModuleManifest($this->alternateBasePath);
        $moduleManifest->init(true);
        $this->pushModuleManifest($moduleManifest);

        // Replace old template loader with new one with alternate base path
        $this->oldThemeResourceLoader = ThemeResourceLoader::inst();
        ThemeResourceLoader::set_instance($loader = new ThemeResourceLoader($this->alternateBasePath));
        $loader->addSet(
            '$default',
            $themeManifest = new ThemeManifest($this->alternateBasePath, project())
        );
        $themeManifest->init(true);

        SSViewer::set_themes([
            'testtheme1',
            '$default',
        ]);

        $this->originalLocale = i18n::get_locale();
        i18n::set_locale('en_US');

        // Set new manifest against the root
        $classManifest = new ClassManifest($this->alternateBasePath);
        $classManifest->init(true);
        $this->pushManifest($classManifest);

        // Setup uncached translator
        // This should pull the module list from the above manifest
        $translator = new Translator('en');
        $translator->setFallbackLocales(['en']);

        $loader = new ModuleYamlLoader();
        $loader->setReader(new YamlReader());

        $translator->addLoader('ss', $loader); // Standard ss module loader
        // Note: array loader isn't added by default
        $translator->addLoader('array', new ArrayLoader());

        $symfonyMessageProvider = SymfonyMessageProvider::create();
        $symfonyMessageProvider->setTranslator($translator);
        Injector::inst()->registerService($symfonyMessageProvider, MessageProvider::class);
    }

    public function tearDownManifest(): void
    {
        ThemeResourceLoader::set_instance($this->oldThemeResourceLoader);
        i18n::set_locale($this->originalLocale);

        // Reset any manifests pushed during this test
        $this->popManifests();
    }

    /**
     * Safely push a new class manifest.
     * These will be cleaned up on tearDown()
     */
    protected function pushManifest(ClassManifest $classManifest)
    {
        $this->manifests++;
        ClassLoader::inst()->pushManifest($classManifest);
    }

    protected function pushModuleManifest(ModuleManifest $moduleManifest)
    {
        $this->moduleManifests++;
        ModuleLoader::inst()->pushManifest($moduleManifest);
    }

    /**
     * Pop off all extra manifests
     */
    protected function popManifests()
    {
        // Reset any manifests pushed during this test
        while ($this->manifests > 0) {
            ClassLoader::inst()->popManifest();
            $this->manifests--;
        }

        while ($this->moduleManifests > 0) {
            ModuleLoader::inst()->popManifest();
            $this->moduleManifests--;
        }
    }
}
