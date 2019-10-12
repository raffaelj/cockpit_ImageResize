# ImageResize

Addon for [Cockpit CMS][1] to resize uploaded images automatically

## Installation

Copy this repository into `/addons` and name it `ImageResize` or

```bash
cd path/to/cockpit
git clone https://github.com/raffaelj/cockpit_ImageResize.git addons/ImageResize
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

Use the GUI or add options to `/config/config.yaml`.

If you set `maxWidth` or `maxHeight` to `0` (zero), the value will be ignored.

```yaml
imageresize:
    enabled: true                 # boolean, default: false
    keepOriginal: true            # boolean, default: true
    moveOriginalTo: original      # string, default: "full"
    maxWidth: 2500                # int, default: 1920
    maxHeight: 2500               # int, default: 0
    method: bestFit               # string, default: bestFit
    quality: 80                   # default: 100
    replaceAssetsManager: true    # use modified assets manager
    profiles:                     # create multiple image sizes
        thumbnail:                # save in /uploads/thumbnail/image.jpg
            width: 100
            height: 100
            method: thumbnail     # default: thumbnail
            quality: 70           # default: 100
        headerimage:
            width: 1200
            height: 400
            method: thumbnail
            quality: 70
            folder: header        # save in /uploads/header/image.jpg
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

## To do

* [x] batch action for existing files
* cli commands
  * [x] batch convert all assets
  * [ ] update entries
* [x] multiple profiles, e. g. "thumbnail", "banner"...
* [ ] overwrite default date pattern in uploads folder to custom folder
* [ ] force recreation when changing defaults
* [ ] GUI for profiles

[1]: https://github.com/agentejo/cockpit/
