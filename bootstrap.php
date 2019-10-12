<?php
/**
 * Addon for Cockpit CMS to resize uploaded images automatically
 * 
 * @see       https://github.com/raffaelj/cockpit_ImageResize
 * @see       https://github.com/agentejo/cockpit/
 * 
 * @version   0.1.1
 * @author    Raffael Jesche
 * @license   MIT
 */

$this->module('imageresize')->extend([

    'config' => null,

    'getConfig' => function($key = null) {

        if (!$this->config) {

            $this->config = array_replace_recursive(
                [
                    'enabled'      => false,
                    'keepOriginal' => true,       # boolean, default: true
                    'moveOriginalTo' => 'full',   # /uploads/full
                    'maxWidth'     => 1920,
                    'maxHeight'    => 0,          # don't check for maxHeight if 0
                    'method'       => 'bestFit',
                    'quality'      => 100,
                    'profiles'     => [],         # add multiple sizes like thumbnail
                    'replaceAssetsManager' => false, # modified assets manager
                    // 'prettyNames'  => false,   # remove uniqid from file names, to do...
                    // 'renameToTitle' => false,  # rename image to title (for SEO), to do... (must be unique)
                    // 'customFolder' => '',      # overwrite original date pattern, to do...
                ],
                $this->app->storage->getKey('cockpit/options', 'imageresize', []),
                $this->app->retrieve('imageresize', [])
            );

        }

        if ($key) {
            return isset($this->config[$key]) ? $this->config[$key] : false;
        }

        return $this->config;

    },

    'replaceAssets' => function($assets = []) {

        $c = $this->getConfig();

        if ($c['keepOriginal']) {
            $pathOutOriginal = $this->app->path('#uploads:') . $c['moveOriginalTo'] . '/';
            if (!is_dir($pathOutOriginal)) mkdir($pathOutOriginal);
        }

        $profiles = !empty($c['profiles']) ? $c['profiles'] : [];

        foreach ($assets as &$asset) {

            // skip, if already resized
            if (isset($asset['resized']) && $asset['resized'] == true) {
                continue;
            }

            if (isset($asset['width']) && isset($asset['height'])) {

                // use orginal size if 0
                $maxWidth  = $c['maxWidth']  ? $c['maxWidth']  : $asset['width'];
                $maxHeight = $c['maxHeight'] ? $c['maxHeight'] : $asset['height'];

                if (!($asset['width'] > $maxWidth || $asset['height'] > $maxHeight)) {
                    continue;
                }

                $path = $this->app->path('#uploads:' . ltrim($asset['path'], '/'));

                if ($c['keepOriginal']) {

                    $fileName = basename($asset['path']);
                    $copied = copy($path, $pathOutOriginal.$fileName);

                    // skip resizing if original wasn't copied
                    if ($copied === false) continue;

                    // For some reason, filesize() points to the copied path now...
                    // (Win, Xampp7) - I don't understand it, but it's easy to fix
                    $path = realpath($path);

                    $asset['sizes']['full'] = [
                        'path' => '/' . $c['moveOriginalTo'] . '/' . $fileName,
                        'width' => $asset['width'],
                        'height' => $asset['height'],
                        'size' => $asset['size'],
                    ];

                }

                // resize image
                $img = $this('image')->take($path)->{$c['method']}($maxWidth, $maxHeight);

                // overwrite input file with resized output
                $result = file_put_contents($path, $img->toString(null, $c['quality']));

                unset($img);

                // don't overwrite meta, if write process failed
                if ($result === false) continue;

                $info = getimagesize($path);
                $asset['width']   = $info[0];
                $asset['height']  = $info[1];
                $asset['size']    = filesize($path);
                $asset['resized'] = true;
                
                foreach ($profiles as $name => $options) {

                    if ($resized = $this->addResizedAsset($asset, $name, $options)) {

                        $asset['sizes'][$name] = $resized;

                    }

                }

            }

        }

        return $assets;

    },

    'addResizedAsset' => function($asset, $name, $options) {

        $c = array_replace([
            'width'   => 1920,
            'height'  => 0,
            'method'  => 'thumbnail',
            'quality' => 100,
        ], $options);

        $anchor = $c['fp'] ?? $asset['fp'] ?? 'center';
        if (is_array($anchor)) $anchor = implode(' ', $anchor);

        // use orginal size if 0
        $width  = $c['width']  ? $c['width']  : $asset['width'];
        $height = $c['height'] ? $c['height'] : $asset['height'];

        $dir = !empty($c['folder']) ? $c['folder'] : $name;
        $fileName = basename($asset['path']);

        $pathIn  = $this->app->path('#uploads:' . ltrim($asset['path'], '/'));
        $pathOut = $this->app->path('#uploads:') . $dir . '/';

        $path = $pathOut . $fileName;

        if (!is_dir($pathOut)) mkdir($pathOut);

        // resize image
        if ($c['method'] == 'thumbnail') {
            $img = $this('image')->take($pathIn)->{$c['method']}($width, $height, $anchor);
        } else {
            $img = $this('image')->take($pathIn)->{$c['method']}($width, $height);
        }

        // save resized image
        $result = file_put_contents($path, $img->toString(null, $c['quality']));

        unset($img);

        $info = getimagesize($path);

        return [
            'path' => '/' . $dir . '/' . $fileName,
            'width' => $info[0],
            'height' => $info[1],
            'size' => filesize($path),
        ];

    }

]);

$this->on('cockpit.assets.save', function(&$assets) {

    $c = $this->module('imageresize')->getConfig();

    if ($c['enabled'] !== true) return;

    $assets = $this->module('imageresize')->replaceAssets($assets);

});

// remove original image copy
$this->on('cockpit.assets.remove', function($assets) {

    $c = $this->module('imageresize')->getConfig();

    if ($c['enabled'] !== true) return;

    foreach($assets as $asset) {
        if (isset($asset['sizes']) && is_array($asset['sizes'])) {
            foreach ($asset['sizes'] as $file) {
                if ($this->filestorage->has('assets://'.trim($file['path'], '/'))) {
                    $this->filestorage->delete('assets://'.trim($file['path'], '/'));
                }
            }
        }
    }

});


// acl
$this('acl')->addResource('imageresize', ['manage']);

// admin ui
if (COCKPIT_ADMIN && !COCKPIT_API_REQUEST) {
    include_once(__DIR__ . '/admin.php');
}

// cli
if (COCKPIT_CLI) {
    $this->path('#cli', __DIR__ . '/cli');
}
