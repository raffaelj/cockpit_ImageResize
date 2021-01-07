# ImageResize

Addon for [Cockpit CMS][1] to resize and optimize uploaded images automatically

## Requirements, compatibility

Since v0.2.0, ImageResize requires Cockpit v0.10.1 or above.

I rewrote a lot of the code to make use of the new `cockpit.asset.upload` event. It should now also be compatible with the cloud storage addon, but I wasn't able to test it, yet.

If you enable the `optimize` option, the [ImageOptimizer addon] is obsolute. Don't use both addons together.

## Installation

Copy this repository into `/addons` and name it `ImageResize` or use the cli.

### via git

```bash
cd path/to/cockpit
git clone https://github.com/raffaelj/cockpit_ImageResize.git addons/ImageResize
```

### via cp cli

```bash
cd path/to/cockpit
./cp install/addon --name ImageResize --url https://github.com/raffaelj/cockpit_ImageResize/archive/master.zip
```

### via composer

Make sure, that the path to cockpit addons is defined in your projects' `composer.json` file.

```json
{
    "name": "my/cockpit-project",
    "extra": {
        "installer-paths": {
            "addons/{$name}": ["type:cockpit-module"]
        }
    }
}
```

```bash
cd path/to/cockpit-root
composer create-project --ignore-platform-reqs aheinze/cockpit .
composer config extra.installer-paths.addons/{\$name} "type:cockpit-module"

composer require --ignore-platform-reqs raffaelj/cockpit-imageresize
```

## Usage

A copy of the original file is stored in `/uploads/full/filename.jpg`. The default file will be replaced with the resized file. From now on, you don't have to create thumbnails from 8MB sized files again, but you are still able to use the original file, if you want to.

If users upload very large images, it will take a while to process the files.

There is no option in the assets manager to choose the original file, but your api autput has two additional keys:

**original keys:**

```
"path": "/2019/07/02/filename.jpg",
"title": "DSC07504.JPG",
...
```

**extra keys:**

```
"sizes": {
    "full": {
        "path": "/full/filename.jpg",
        "width": 4912,
        "height": 3264,
        "size": 4390912
    }
},
"resized": true,
```

## Options

The GUI is outdated and doesn't provide all options.

`config/config.php`:

```php
<?php
return [
    'app.name' => 'ImageResize Test',

    'imageresize' => [
        'resize'       => false,        # (bool) default: true

        # create a copy of uploaded files in `/original/img.jpg`
        'keepOriginal' => true,         # (bool) default: true
        'moveOriginalTo' => 'original', # (string) default: full

        # resize options, that are passed to SimpleImage library
        # If you set maxWidth or maxHeight to 0 (zero), the value will be ignored.
        'maxWidth'     => 1920,         # (int) default: 1920
        'maxHeight'    => 0,            # (int) default: 0
        'method'       => 'bestFit',    # (string) default: bestFit
        'quality'      => 100,          # (int) default: 100

        # remove uniqid from file names, duplicates will have increasing number suffixes
        'prettyNames'  => true,         # (bool) default: false

        # overwrite original date pattern - `/2020/10/30/img.jpg` --> `/images/img.jpg`
        'customFolder' => '/images',    # (string|null) default: null

		# String to use to separate the profile name from the file name
		# Defaults to false, which will generate a directory per profile, eg: /small/img.jpg
		# If specified, files will exist in single directory, eg /img@small.jpg
		'profileNameSeparator' => '@', # (string|null) default: null

        # Spatie image optimizer (requires additional binaries)
        'optimize'     => true,         # (bool) default: false

        # use modified assets manager
        'replaceAssetsManager' => true, # (bool) default: false

        # add multiple sizes like thumbnail
        'profiles' => [
            'small' => [                # --> `/small/img.jpg`
                'width'   => 500,
                'height'  => 0,
                'method'  => 'bestFit', # (string) default: thumbnail
                'quality' => 70,        # (int) default: 100
            ],
            'thumbs' => [               # --> `/thumbs/img.jpg`
                'width'   => 100,
                'height'  => 100,
                'method'  => 'thumbnail',
                'quality' => 70,
            ],
            'headerimage' => [
                'width'   => 1200,
                'height'  => 400,
                'method'  => 'thumbnail',
                'quality' => 70,
                # set custom folder, that doesn't match profile name --> `/header/img.jpg`
                'folder'  => 'header'   # (string) if omited, the key name 'headerimage' is used
            ],
        ],
    ],
];
```

## ACL

If users without admin rights should have access to the settings, you have to give them manage rights.

```yaml
groups:
    managers:
        cockpit:
            backend: true
        imageresize:
            manage: true
```

## CLI

Call `./cp imageresize/replace` to replace all existing images.

**Warning**

* Create a backup before processing.
* Make sure to set `memory_limit = 512M` in your `php.ini` if you have to process large files.
* Use a sequential bash command if you still have memory issues

If you run into memory problems, use the script sequentially. First call the following command and read the output, e. g. "151".

`./cp imageresize/replace --count`

Then call the following command and replace "151" with your assets count.
Now the script processes only 10 files at once.

`for i in `seq 0 10 151`; do ./mp imageresize/replace --skip $i --limit 10 --s --loud; done`

## Customized assetsmanager/cp-assets component

**extra features:**

* "select all" checkbox
* `copyright` field for assets
* select different sizes (profiles) in assetsmanager

## Spatie image optimizer

If you wonder, why the optimizer doesn't work, you have to install some binaries in your environment.
See: https://github.com/spatie/image-optimizer#optimization-tools

I tested it successfully on my local devolopment machine with the [raffaelj/php7-apache-imgopt][4] docker image.

## To do

* [x] batch action for existing files
* cli commands
  * [x] batch convert all assets
  * [ ] update entries
* [x] multiple profiles, e. g. "thumbnail", "banner"...
* [x] overwrite default date pattern in uploads folder to custom folder
* [ ] force recreation when changing defaults
* [ ] GUI for profiles
+ [ ] fine tuning for image optimizer (especially SVG)

## Credits and third party libraries

* Spatie image optimizer, MIT Licensed, https://spatie.be/

[1]: https://github.com/agentejo/cockpit/
[2]: https://github.com/pauloamgomes/CockpitCMS-ImageOptimizer
[3]: https://github.com/spatie/image-optimizer
[4]: https://hub.docker.com/r/raffaelj/php7-apache-imgopt
