<?php
namespace Grav\Plugin;

use \Grav\Common\Plugin;

class CdnPlugin extends Plugin
{
    /** @var Config $config */
    protected $config;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize configuration
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        $this->enable([
            'onOutputGenerated' => ['onOutputGenerated', 0]
        ]);
    }


    public function onOutputGenerated()
    {
        $config = $this->grav['config']->get('plugins.cdn');

        $cache = $this->grav['cache'];
        $key = '?' . $cache->getKey();

        $pullzone = 'http://'.$config['pullzone'];
        $base = str_replace('/', '\/', $this->grav['base_url_relative']);
        $replace = '$1'.$pullzone.'$2"';

        $regex = "/((?:<(?:".$config['tags'].")\b)[^>]*?(?:href|src)=\")(?:(?!\/{2}))(?:".$base.")(.*?\.(?:".$config['extensions'].")(?:(?!(?:\?|&)nocdn).*?))(?<!(\?|&)nocdn)\"/i";

        $this->grav->output = preg_replace($regex, $replace, $this->grav->output);

        // replacements for inline CSS url() style references
        if ($config['inline_css_replace']) {
            $replace = '$1'.$pullzone.'$2);"';
            $regex = "/(url\()(?:".$base.")(.*?\.(?:".$config['extensions'].")(?:(?!(?:\?|&)nocdn).*?))(?<!(\?|&)nocdn)\);\"/i";
            $this->grav->output = preg_replace($regex, $replace, $this->grav->output);
        }
    }
}
