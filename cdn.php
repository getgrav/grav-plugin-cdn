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

        // No pullzone configured — skip rewriting entirely. Lets the plugin
        // ship enabled by default without breaking assets on a fresh install
        // where the site owner hasn't set a CDN domain yet.
        if (empty($config['pullzone'])) {
            return;
        }

        $format = $this->grav['uri']->extension() ?: 'html';
        // only process for HTML pages
        if (!in_array($format, (array) $config['valid_formats'])) {
            return;
        }

        // set the protocol to HTTPS if you access that way
        if ( (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) == 'on') ||
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

        // `srcset` holds a comma separated list of candidates rather than a single URL,
        // so it gets its own pass further down. Dropping it here also stops an existing
        // config that lists it from swallowing `src=` in the single URL regex below.
        $tag_attributes = implode('|', array_filter(
            array_map('trim', explode('|', (string) $tag_attributes)),
            function ($attribute) {
                return $attribute !== '' && !preg_match('/^(?:data-)?srcset$/i', $attribute);
            }
        ));

        // `srcset` only ever appears on `img` and on `picture`'s `source`, and `source`
        // isn't part of the `tags` config, so add it whenever images are being rewritten
        $srcset_tags = array_filter(array_map('trim', explode('|', (string) $tags)));
        if (in_array('img', array_map('strtolower', $srcset_tags), true)) {
            $srcset_tags[] = 'source';
        }
        $srcset_tags = implode('|', array_unique($srcset_tags));

        // match all pre/code blocks
        preg_match_all("/<(pre|code)((?:(?!<\/\\1).)*?)<\/\\1>/uis", $this->grav->output, $blocks);

        // an empty attribute list would compile to `(?:)=` and match every attribute
        if ($tag_attributes !== '') {
            // https://regex101.com/r/pI3tF7/5 -> (<(?:a|img|link|script)[^>]+(?:href|src)=\"(?:(?!(?:[a-z-+]{1,}?:)?\/{2})))([^\"]+(?:)(\.(?:jpe?g|png|gif|ttf|otf|svg|woff|xml|js|css)(?:(?!(?:\?|&)nocdn).*?))(?<!(\?|&)nocdn))\"
            $regex = "/(<(?:" . $tags . ")[^>]+(?:" . $tag_attributes . ")=\"(?:(?!(?:[a-z-+]{1,}?:)?\/{2})))([^\"]+(\.(?:" . $extensions . ")(?:(?!(?:\?|&)nocdn).*?))(?<!(\?|&)nocdn))\"/i";

            $this->grav->output = preg_replace_callback(
                $regex,
                function ($matches) use ($blocks, $pullzone) {
                    $isBlock = $this->array_search_partial($blocks[0], $matches[0]);
                    return $isBlock !== null ? $matches[0] : $matches[1] . $pullzone . $matches[2] . '"';
                },
                $this->grav->output
            );
        }

        // replacements for srcset/data-srcset candidate lists
        if ($srcset_tags !== '') {
            $regex = "/<(?:" . $srcset_tags . ")\b[^>]*?(?:data-)?srcset=\"[^\"]*\"[^>]*>/i";

            $this->grav->output = preg_replace_callback(
                $regex,
                function ($matches) use ($blocks, $pullzone, $extensions) {
                    if ($this->array_search_partial($blocks[0], $matches[0]) !== null) {
                        return $matches[0];
                    }

                    // a tag can carry both `srcset` and `data-srcset`, so rewrite each one
                    return preg_replace_callback(
                        "/((?:data-)?srcset=\")([^\"]*)(\")/i",
                        function ($attribute) use ($pullzone, $extensions) {
                            return $attribute[1] . $this->cdnify_srcset($attribute[2], $pullzone, $extensions) . $attribute[3];
                        },
                        $matches[0]
                    );
                },
                $this->grav->output
            );
        }

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
                    return $isBlock !== null ? $matches[0] : $matches[1] . $pullzone . $matches[2] . $matches[3];
                },
                $this->grav->output
            );
        }
    }

    /**
     * Rewrite every candidate URL in a single srcset attribute value
     */
    private function cdnify_srcset($srcset, $pullzone, $extensions)
    {
        // candidates are comma separated and each one is a URL followed by an optional
        // descriptor (`400w`, `2x`), so anchor on the start of the value or on a comma
        // rather than trying to spell out the descriptor grammar
        return preg_replace_callback(
            "/(^|,)(\s*)([^\s,]+)/",
            function ($matches) use ($pullzone, $extensions) {
                $url = $matches[3];

                // leave anything already absolute alone: a full URL, a protocol
                // relative one, or an inline data: URI
                if (preg_match("/^(?:[a-z-+]{1,}?:)?\/{2}|^data:/i", $url)) {
                    return $matches[0];
                }

                if (!preg_match("/\.(?:" . $extensions . ")/i", $url)) {
                    return $matches[0];
                }

                // honour the per-URL nocdn opt-out, same as the single URL pass
                if (preg_match("/(?:\?|&)nocdn$/i", $url)) {
                    return $matches[0];
                }

                return $matches[1] . $matches[2] . $pullzone . $url;
            },
            (string) $srcset
        );
    }

    private function array_search_partial($arr, $keyword)
    {
        foreach ($arr as $index => $string) {
            if (strpos((string) $string, (string) $keyword) !== false) {
                return $index;
            }
        }

        return null;
    }
}
