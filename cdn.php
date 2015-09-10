<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;

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
        $key   = '?' . $cache->getKey();

        $pullzone   = 'http://' . $config['pullzone'];
        $base       = str_replace('/', '\/', $this->grav['base_url_relative']);
        $extensions = $config['extensions'];
        $tags       = $config['tags'];
        $replace    = '$1' . $pullzone . '$2"';

        // match all pre/code blocks
        preg_match_all("/<(pre|code)((?:(?!<\/\\1).)*?)<\/\\1>/uis", $this->grav->output, $blocks);

        $regex = "/((?:<(?:" . $tags . ")\b)[^>]*?(?:href|src)=\")(?:(?!\/{2}))(?:" . $base . ")(.*?\.(?:" . $extensions
            . ")(?:(?!(?:\?|&)nocdn).*?))(?<!(\?|&)nocdn)\"/i";

        $this->grav->output = preg_replace_callback(
            $regex,
            function ($matches) use ($blocks, $replace) {
                $isBlock = $this->array_search_partial($blocks[0], $matches[0]);
                return $isBlock ? $matches[0] : $replace;
            },
            $this->grav->output
        );

        // replacements for inline CSS url() style references
        if ($config['inline_css_replace']) {
            $regex  = "/(url\()(?:" . $base . ")(.*?\.(?:" . $extensions . "\)))/i";

            $this->grav->output = preg_replace_callback(
                $regex,
                function ($matches) use ($blocks, $replace) {
                    $isBlock = $this->array_search_partial($blocks[0], $matches[0]);
                    return $isBlock ? $matches[0] : $replace;
                },
                $this->grav->output
            );
        }
    }

    private function array_search_partial($arr, $keyword)
    {
        foreach ($arr as $index => $string) {
            if (strpos($string, $keyword) !== false) {
                return $index;
            }
        }
    }
}
