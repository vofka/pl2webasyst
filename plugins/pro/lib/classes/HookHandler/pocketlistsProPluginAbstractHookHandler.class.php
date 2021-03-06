<?php

/**
 * Class pocketlistsProPluginAbstractHookHandler
 */
abstract class pocketlistsProPluginAbstractHookHandler implements pocketlistsHookHandlerInterface
{
    /**
     * @var pocketlistsProPlugin
     */
    protected $plugin;

    /**
     * pocketlistsProPluginHandlerAbstractHook constructor.
     */
    public function __construct()
    {
        $this->plugin = pocketlistsProPlugin::getInstance();
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function getViewTemplate($name)
    {
        return sprintf('%s/templates/hooks/%s.html', $this->plugin->getPath(), $name);
    }

    /**
     * @return waView
     */
    protected function getView()
    {
        return $this->plugin->getView();
    }
}
