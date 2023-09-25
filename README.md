# Imgix download transformer for Imager X

A transformer for Imager X that uses [Imgix](https://imgix.com/) for transforms, but stores the transformed images locally.   

## Requirements

This plugin requires Craft CMS 4.0.0 or later, [Imager X 4.0.0](https://github.com/spacecatninja/craft-imager-x/) or later,
and an [Imgix account](https://imgix.com/).
 
## Usage

This plugin is a hybrid between the default `craft` transformer that uses GD or Imagick to generate image transforms
locally on the server, and the `imgix` transformer that uses [Imgix](https://imgix.com/). It uses Imgix to transform the
image, but downloads and stores the image locally on the server.

Why not just use `imgix`? Saves you some $$$.  
Why not just use `craft`? Saves you a lot of CPU cycles and memory, and you're not limited by your server configuration.

All configuration is done through [Imager's standard configuration](https://imager-x.spacecat.ninja/configuration.html), 
just like you would when using the `craft` and `imgix` transformers. This transformer will use the configuration for each
where appropriate, so if you run into problems test that your configuration works for those transformers.

To activate the transformer, set the [`transformer` config setting](https://imager-x.spacecat.ninja/configuration.html#transformer-string)
to `imgixdownload`:

```
'transformer' => 'imgixdownload',
```

### Cave-ats, shortcomings, and tips

The biggest cave-at by not letting Imgix serve the images through its CDN, is that `auto: format` doesn't work anymore. You'll 
have to serve up different formats manually, preferably using `<picture>`, just like you would if you used local transforms.

Even though transforms aren't done on the server, there will still be some latency while waiting for Imgix to deliver the image. So just
like when using the `craft` transformer, you'll benefit from configuring 
[automatic generation of transforms](https://imager-x.spacecat.ninja/usage/generate.html) for your project.

[External storages](https://imager-x.spacecat.ninja/usage/external-storages.html) and [optimizers](https://imager-x.spacecat.ninja/usage/optimizers.html) 
work with this transformer exactly like it does for `craft`.

Imager will use your `imagerUrl` config setting when serving images, so you're free to [add your own pull CDN](https://www.spacecat.ninja/blog/using-a-pull-cdn) if you like.

## Installation

To install the plugin, follow these instructions:

1. Install with composer via `composer require spacecatninja/imager-x-imgix-download-transformer` from your project directory.
2. Install the plugin in the Craft Control Panel under Settings > Plugins, or from the command line via `./craft plugin/install imager-x-imgix-download-transformer`.


## Configuration

There is currently nothing to configure in this plugin, all configuration is done 
in [the Imager X configuration](https://imager-x.spacecat.ninja/configuration.html).


Price, license and support
---
The plugin is released under the MIT license. It requires Imager X, which is a commercial 
plugin [available in the Craft plugin store](https://plugins.craftcms.com/imager-x). If you 
need help, or found a bug, please post an issue in this repo, or in Imager X' repo. 
