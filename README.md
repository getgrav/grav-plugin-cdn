# Grav CDN Plugin

This is a simple [Grav](http://github.com/getgrav/grav) plugin that takes provides a simple way to integrate a **Pull Zone CDN** service (such as MaxCDN) with minimal fuss.


# Installation

Installing the CDN plugin can be done in one of two ways. Our GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

## GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's Terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install cdn

This will install the CDN plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/cdn`.

## Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `cdn`. You can find these files either on [GitHub](https://github.com/getgrav/grav-plugin-cdn) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/cdn

# Usage

The default configuration provided in the `user/config/plugins/cdn.yaml` file contains sensible defaults:

```
enabled: true                                           # set to false to disable this plugin completely
inline_css_replace: true                                # Replace inline css url() references
pullzone: yourdomain.cdn.com                            # pullzone domain
tags: 'a|link|img|script'                               # HTML tags to search
extensions: 'jpe?g|png|gif|ttf|otf|svg|woff|xml|js|css' # File extensions to replace on
```

To make modifications, please copy this file into `user/config/plugins/cdn.yaml` and edit the fields to tweak settings

HTML tags with the configured list of `tags` will be rewritten with the pullzone domain you configure if one of the `extensions` is found.

### Forcing Local Links

There are occassions where a particular link should not be served via the CDN.  In these cases you can simple pass the query element `nocdn` in the URL.  For example:

##### Markdown Syntax

```
![](myimage.jpg?nocdn)
```

or

```
![](myimage.jpg?nocdn&cropResize=500,200&grayscale)
```

##### Twig Syntax

```
{{ page.media['myimage.jpg'].nocdn }}
```

or

```
{{ page.media['myimage.jpg'].nocdn.cropResize(500,200).grayscale }}
```

### Important note about font files

If you are hosting custom font files (eot, ttf, otf, or woff) you need to be aware that it requires setting the Access-Control-Allow-Origin header to the domain serving your Grav site or use wildcard-origin (*).

```
# Apache config
<FilesMatch ".(eot|ttf|otf|woff)">
	Header set Access-Control-Allow-Origin "*"
</FilesMatch>

# nginx config
if ($filename ~* ^.*?\.(eot)|(ttf)|(woff)$){
	add_header Access-Control-Allow-Origin *;
}
```