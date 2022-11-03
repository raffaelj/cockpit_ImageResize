# Changelog

## 0.2.2

* fixed compatibility issue with PHP 8.0 (order of optional/required arguments)

## 0.2.1

* added option to rename files to sluggified titles
* improved custom assets manager
  * moved most of the customizations into separate assetspanel
  * improved progress bar
  * improved svg handling
  * improved select-all checkbox
* fixed  some errors if no image or if SVG
* minor fixes
* started to add ability to adjust spatie optimizer options

## 0.2.0

* resizing is enabled by default (before, you had to set `enabled: true`)
* replaced `enabled` option with `resize` option (default: true)
* added option to use pretty file names - without uniqid, but with number suffix for duplicates
* added option for custom folder - still inside `uploads` folder
* added image optimizer (Spatie)
* updated modified assets manager with latest core features
* more effective use of event system --> requires Cockpit v0.10.2 or above
* should be compatible with cloud storage addon (not tested, yet)

## 0.1.2

* rewrote batch replace cli command to avoid memory issues
* disabled ui batch action to avoid runtime or memory issues
* added `composer.json`

## 0.1.1

* added profiles for multiple image sizes, e. g. thumbnails
* added modified assets manager
* added option (UI and cli) to replace existing assets with resized ones

## 0.1.0

* initial release
