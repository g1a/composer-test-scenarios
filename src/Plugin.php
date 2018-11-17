<?php

namespace ComposerTestScenarios;

use Composer\Script\Event;
use Composer\Plugin\CommandEvent;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin for handling Composer Test Scenarios.
 *
 * There are two reasons why this tool is a Composer plugin:
 *
 * 1. It provides a convenient way to define a command, e.g. 'composer create-scenario'
 * 2. It adds a new event 'define-scenarios-cmd'
 *
 * We might do other neat things with this as well
 */
class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    /**
     * @var \DrupalComposer\DrupalScaffold\Handler
     */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        // We use a separate PluginScripts object. This way we separate
        // functionality and also avoid some debug issues with the plugin being
        // copied on initialisation.
        // @see \Composer\Plugin\PluginManager::registerPackage()
        $this->handler = new Handler($composer, $io);
    }

    /**
     * {@inheritdoc}
     */
    public function getCapabilities()
    {
        return [
            'Composer\Plugin\Capability\CommandProvider' => 'ComposerTestScenarios\CommandProvider',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_UPDATE_CMD => 'postUpdate',
            PluginEvents::COMMAND => 'cmdBegins',
        ];
    }

    /**
     * Command begins event callback.
     *
     * @param \Composer\Plugin\CommandEvent $event
     */
    public function cmdBegins(CommandEvent $event)
    {
        $this->handler->onCmdBeginsEvent($event);
    }

    /**
     * Post update event callback.
     *
     * @param \Composer\Script\Event $event
     */
    public function postUpdate(Event $event)
    {
        $this->handler->scenariosEvent($event);
    }
}
