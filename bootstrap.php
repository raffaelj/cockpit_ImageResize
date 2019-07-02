<?php
/**
 * Addon for Cockpit CMS to resize uploaded images automatically
 * 
 * @see       https://github.com/raffaelj/cockpit_ImageResize
 * @see       https://github.com/agentejo/cockpit/
 * 
 * @version   0.1.0
 * @author    Raffael Jesche
 * @license   MIT
 */


$this->module('imageresize')->extend([

    'getConfig' => function() {

        $config = array_replace_recursive(
            [
                'enabled'      => false,
                'keepOriginal' => true,       # boolean, default: true
                'moveOriginalTo' => 'full',   # /uploads/full
                'maxWidth'     => 1920,
                'maxHeight'    => 0,          # don't check for maxHeight if 0
                'method'       => 'bestFit',
                'quality'      => 100,
                // 'customFolder' => '',      # overwrite original date pattern, to do...
                // 'profiles'     => []       # add multiple sizes like thumbnail, to do...
            ],
            $this->app->storage->getKey('cockpit/options', 'imageresize', []),
            $this->app->retrieve('imageresize', [])
        );

        return $config;

    },

]);

$this->on('cockpit.assets.save', function(&$assets) {

    $c = $this->module('imageresize')->getConfig();

    if ($c['enabled'] !== true) return;

    if ($c['keepOriginal']) {
        $pathOutOriginal = $this->path('#uploads:') . $c['moveOriginalTo'] . '/';
        if (!is_dir($pathOutOriginal)) mkdir($pathOutOriginal);
    }

    foreach ($assets as &$asset) {

        if (isset($asset['width']) && isset($asset['height'])) {

            // use orginal size if 0
            $maxWidth  = $c['maxWidth']  ? $c['maxWidth']  : $asset['width'];
            $maxHeight = $c['maxHeight'] ? $c['maxHeight'] : $asset['height'];

            if (!($asset['width'] > $maxWidth || $asset['height'] > $maxHeight)) {
                continue;
            }

            $path = $this->path('#uploads:' . ltrim($asset['path'], '/'));

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

        }

    }

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
