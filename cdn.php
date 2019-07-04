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
        $format = $this->grav['uri']->extension() ?: 'html';
        // only process for HTML pages
        if (!in_array($format, (array) $config['valid_formats'])) {
            return;
        }

        // set the protocol to HTTPS if you access that way
        if ( (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ||
           ( isset($config['forcehttps']) && $config['forcehttps'] == true))
           {
            $protocol =  'https://';
            $pullzone = isset($config['pullzone_ssl']) ? $config['pullzone_ssl'] : $config['pullzone'];
        } else {
            $protocol = 'http://';
            $pullzone = $config['pullzone'];
        }

        $pullzone       = $protocol . $pullzone;
        $base           = str_replace('/', '\/', $this->grav['base_url_relative']);
        $extensions     = $config['extensions'];
        $tag_attributes = $config['tag_attributes'];
        $tags           = $config['tags'];

        // match all pre/code blocks
        preg_match_all("/<(pre|code)((?:(?!<\/\\1).)*?)<\/\\1>/uis", $this->grav->output, $blocks);

        // https://regex101.com/r/pI3tF7/5 -> (<(?:a|img|link|script)[^>]+(?:href|src)=\"(?:(?!(?:[a-z-+]{1,}?:)?\/{2})))([^\"]+(?:)(\.(?:jpe?g|png|gif|ttf|otf|svg|woff|xml|js|css)(?:(?!(?:\?|&)nocdn).*?))(?<!(\?|&)nocdn))\"
        $regex = "/(<(?:" . $tags . ")[^>]+(?:" . $tag_attributes . ")=\"(?:(?!(?:[a-z-+]{1,}?:)?\/{2})))([^\"]+(\.(?:" . $extensions . ")(?:(?!(?:\?|&)nocdn).*?))(?<!(\?|&)nocdn))\"/i";

        $this->grav->output = preg_replace_callback(
            $regex,
            function ($matches) use ($blocks, $pullzone) {
                $isBlock = $this->array_search_partial($blocks[0], $matches[0]);
                return $isBlock ? $matches[0] : $matches[1] . $pullzone . $matches[2] . '"';
            },
            $this->grav->output
        );

        // replacements for inline CSS url() style references
        if ($config['inline_css_replace']) {

            // https://regex101.com/r/8zAnec/2 -> (url\([\'\"])(?:)(.*?\.(?:jpe?g|png|gif|ttf|otf|svg|woff|xml|js|css))(.*?\);)/i
            // or with $base
            // https://regex101.com/r/g0R6sj/2 -> (url\([\'\"])(?:http:\/\/github\.com)(.*?\.(?:jpe?g|png|gif|ttf|otf|svg|woff|xml|js|css))(.*?\);)/i
            $regex = "/(url\([\'\"]?)(?:" . $base . ")(.*?\.(?:" . $extensions . "))(.*?\);)/i";

            $this->grav->output = preg_replace_callback(
                $regex,
                function ($matches) use ($blocks, $pullzone) {
                    $isBlock = $this->array_search_partial($blocks[0], $matches[0]);
                    return $isBlock ? $matches[0] : $matches[1] . $pullzone . $matches[2] . $matches[3];
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
