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
        $extensions = $config['extensions'];
        $tags = $config['tags'];
        $replace = '$1'.$pullzone.'$2"';

        $skip_fail = "(?s)(?:<pre[^<]*>.*?<\/pre>|<code[^<]*>.*?<\/code>)(*SKIP)(*F)|";

        $regex = "/".$skip_fail."((?:<(?:".$tags.")\b)[^>]*?(?:href|src)=\")(?:(?!\/{2}))(?:".$base.")(.*?\.(?:".$extensions.")(?:(?!(?:\?|&)nocdn).*?))(?<!(\?|&)nocdn)\"/i";


        dump($regex);
        
        $this->grav->output = preg_replace($regex, $replace, $this->grav->output);

        // replacements for inline CSS url() style references
        if ($config['inline_css_replace']) {
            $replace = '$1'.$pullzone.'$2';
            $regex = "/".$skip_fail."(url\()(?:".$base.")(.*?\.(?:".$extensions."\)))/i";
            $this->grav->output = preg_replace($regex, $replace, $this->grav->output);
        }
    }
}
