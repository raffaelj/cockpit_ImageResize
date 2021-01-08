<?php
/**
 * Addon for Cockpit CMS to resize uploaded images automatically
 * 
 * @see       https://github.com/raffaelj/cockpit_ImageResize
 * @see       https://github.com/agentejo/cockpit/
 * 
 * @version   0.2.1
 * @author    Raffael Jesche
 * @license   MIT
 */

require_once(__DIR__.'/lib/vendor/autoload.php');

$this->module('imageresize')->extend([

    'config' => null,

    'spatieOptimizers' => [
        'Jpegoptim',
        'Pngquant',
        'Optipng',
        'Svgo',
        'Gifsicle',
        'Cwebp',
    ],

    'spatieOptions' => [
        'Jpegoptim' => [
            '-m85',
            '--strip-all',
            '--all-progressive',
        ],
        'Pngquant' => [
            '--force',
            '--skip-if-larger',
        ],
        'Optipng' => [
            '-i0',
            '-o2',
            '-quiet',
        ],
        'Svgo' => [
            '--disable={cleanupIDs,removeViewBox}',
        ],
        'Gifsicle' => [
            '-b',
            '-O3',
        ],
        'Cwebp' => [
            '-m 6',
            '-pass 10',
            '-mt',
            '-q 80',
        ],
    ],

    'getConfig' => function($key = null) {

        if (!$this->config) {

            $this->config = \array_replace_recursive(
                [
                    'resize'       => true,
                    'keepOriginal' => true,       # boolean, default: true
                    'moveOriginalTo' => 'full',   # /uploads/full
                    'maxWidth'     => 1920,
                    'maxHeight'    => 0,          # don't check for maxHeight if 0
                    'method'       => 'bestFit',
                    'quality'      => 100,
                    'profiles'     => [],         # add multiple sizes like thumbnail
                    'prettyNames'  => false,      # remove uniqid from file names
                    'customFolder' => null,       # overwrite original date pattern
                    'optimize'     => false,      # Use Spatie optimizer
                    'replaceAssetsManager' => false,   # modified assets manager
                    'syncFileNamesWithTitle' => false, # set file name to sluggified title (experimental)
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

    'changePath' => function($asset, $file) {

        $path_parts = \pathinfo($file);
        $name = $path_parts['filename'];
        $ext  = $path_parts['extension'];

        $dir = '/'.\date('Y/m/d').'/';

        if (\is_string($this->config['customFolder'])) {
            $dir = '/'.\trim($this->config['customFolder'], '/').'/';
            if ($dir == '//') $dir = '/';
        }

        $clean = \preg_replace('/[^a-zA-Z0-9-_\.]/','', \str_replace(' ', '-', $name));
        $path  = "{$dir}{$clean}.{$ext}";

        // check for duplicates and increase number suffix
        $num = 0;
        while ($this->app->filestorage->has("assets://{$path}")) {
            $num++;
            $path = "{$dir}{$clean}-{$num}.{$ext}";
        }

        $asset['title'] = $asset['title'] . (($num) ? " ({$num})" : '');
        $asset['path']  = $path;

        return $asset;

    },

    'replaceAsset' => function($asset, $opts = null, $file = null) {

        $isImage = isset($asset['image']) && $asset['image'];
        $isSVG   = \preg_match('/svg/', $asset['mime']);

        if (!$opts) $opts  = ['mimetype' => $asset['mime']];

        if (!$file) {

            $src = $this->app->path("#uploads:{$asset['path']}");

            if (!$src && $this->app->filestorage->has('assets://'.$asset['path'])) {

                $stream = $this->app->filestorage->readStream('assets://'.$asset['path']);

                if ($stream) {
                   $this->app->filestorage->writeStream('uploads://'.$asset['path'], $stream);
                   $src = $this->app->path("#uploads:{$asset['path']}");
                }
                else {
                    return false;
                }
            }

            $file = $src;
        }

        $c = $this->getConfig();

        // skip, if already resized (cli)
        if (isset($asset['resized']) && $asset['resized'] == true) {
            return;
        }

        // create extra images from profiles
        if ($isImage && !$isSVG) {
            foreach ($c['profiles'] as $name => $options) {

                if ($resized = $this->createResizedAssetFromProfile($asset, $file, $opts, $name, $options)) {

                    $asset['sizes'][$name] = $resized;
                }
            }
        }
        // copy original file to full dir
        $dontNeedBackup = $isSVG && (!$c['optimize']
            || ($c['optimize'] && !\in_array('Svgo', $this->spatieOptimizers)));

        if ($c['keepOriginal'] && $isImage && !$dontNeedBackup) {

            $ret = $this->createBackup($asset, $file, $opts);

            if ($ret) $asset = $ret;
        }

        // resize only images, not svg
        if ($isImage && !$isSVG) {

            // use orginal size if 0
            $maxWidth  = $c['maxWidth']  ? $c['maxWidth']  : ($asset['width'] ?? 0);
            $maxHeight = $c['maxHeight'] ? $c['maxHeight'] : ($asset['height'] ?? 0);

            // resize image
            $img = $this->app->helper('image')
                    ->take($file)
                    ->{$c['method']}($maxWidth, $maxHeight)
                    ->toString(null, $c['quality']);

            // overwrite image
            if ($this->app->helper('fs')->write($file, $img)) {

                // update meta
                $info = \getimagesize($file);
                $asset['width']   = $info[0];
                $asset['height']  = $info[1];
                $asset['size']    = \filesize($file);
                $asset['resized'] = true;
            }

            unset($img);
        }

        if ($c['optimize']) {
            $this->optimize($file);
            $asset['size'] = \filesize($file);
            $asset['optimized'] = true;
        }

        return $asset;

    },

    'createBackup' => function($asset, $file, $opts) {

        if (!$opts) $opts = ['mimetype' => $asset['mime']];

        $c = $this->getConfig();

        $destination = '/'.\trim($c['moveOriginalTo'], '/').'/'.\basename($asset['path']);

        // move file
        $stream = \fopen($file, 'r+');
        $this->app->filestorage->writeStream("assets://{$destination}", $stream, $opts);

        if (\is_resource($stream)) {
            \fclose($stream);
        }

        $asset['sizes']['full'] = [
            'path'   => $destination,
            'size'   => $asset['size'],
        ];
        if (isset($asset['width']))  $asset['sizes']['full']['width']  = $asset['width'];
        if (isset($asset['height'])) $asset['sizes']['full']['height'] = $asset['height'];

        return $asset;

    },

    'createResizedAssetFromProfile' => function($asset, $file = null, $opts = null, $name, $options = null) {

        // skip if not image (or svg)
        if (!$asset['image'] || !(isset($asset['width']) && isset($asset['height']))) return;

        if (!$opts) $opts  = ['mimetype' => $asset['mime']];

        if (!$file) {

            $src = $this->app->path("#uploads:{$asset['path']}");

            if (!$src && $this->app->filestorage->has('assets://'.$asset['path'])) {

                $stream = $this->app->filestorage->readStream('assets://'.$asset['path']);

                if ($stream) {
                   $this->app->filestorage->writeStream('uploads://'.$asset['path'], $stream);
                   $src = $this->app->path("#uploads:{$asset['path']}");
                }
                else {
                    return false;
                }
            }

            $file = $src;
        }

        if (!$options) {
            $options = $this->config['profiles'][$name];
        }

        $c = \array_replace([
            'width'   => 1920,
            'height'  => 0,
            'method'  => 'thumbnail',
            'quality' => 100,
        ], $options);

        $rebuild = $options['rebuild'] ?? false;

        $anchor = $c['fp'] ?? $asset['fp'] ?? 'center';
        if (\is_array($anchor)) $anchor = \implode(' ', $anchor);

        // use orginal size if 0
        $width  = $c['width']  ? $c['width']  : $asset['width'];
        $height = $c['height'] ? $c['height'] : $asset['height'];

        if ($c['skipIfSmaller']) {
            if (
                ($asset['width'] <  $width || $asset['height'] <  $height) &&
                ($asset['width'] <= $width && $asset['height'] <= $height)
            ) {
                return;
            }
        }

        $dir = !empty($c['folder']) ? $c['folder'] : $name;
        $dir = '/'.\trim($dir, '/').'/';
        $file_name = \basename($asset['path']);

        $destination = "{$dir}{$file_name}";

        if (!$rebuild && $this->app->filestorage->has("assets://{$destination}")) {
            return false;
        }

        if ($this->app->filestorage->has("assets://{$destination}")) {
            $this->app->filestorage->delete("assets://{$destination}");
        }

        // resize image
        if ($c['method'] == 'thumbnail') {
            $img_opts = [$width, $height, $anchor];
        } else {
            $img_opts = [$width, $height];
        }

        $img = $this->app->helper('image')
                ->take($file)
                ->{$c['method']}(...$img_opts)
                ->toString(null, $c['quality']);

        // write img to tmp file
        $tmp = $this->app->path('#tmp:').\uniqid()."_{$name}_{$file_name}";
        $this->app->helper('fs')->write($tmp, $img);

        unset($img);

        if ($c['optimize'] ?? false) {
            $this->optimize($tmp);
        }

        // move file
        $stream = \fopen($tmp, 'r+');
        $this->app->filestorage->writeStream("assets://{$destination}", $stream, $opts);

        if (\is_resource($stream)) {
            \fclose($stream);
        }

        $info = \getimagesize($tmp);

        $return = [
            'path'   => $destination,
            'width'  => $info[0],
            'height' => $info[1],
            'size'   => \filesize($tmp),
        ];

        if ($c['optimize'] ?? false) {
            $return['optimized'] = true;
        }

        \unlink($tmp);

        return $return;

    },

    'optimize' => function($file) {

//         \Spatie\ImageOptimizer\OptimizerChainFactory::create()->optimize($file);

        $optimizerChain = (new Spatie\ImageOptimizer\OptimizerChain);

        foreach ($this->spatieOptimizers as $optimizer) {

            $name = "Spatie\\ImageOptimizer\\Optimizers\\{$optimizer}";
            $opts = $this->spatieOptions[$optimizer] ?? [];

            $optimizerChain->addOptimizer(new $name($opts));

        }

        $optimizerChain->optimize($file);

    },

    'updateFileName' => function($asset, $fileName = null, $force = false) {

        $isUpdate = isset($asset['_id']);

        if (!$isUpdate) return false;

        $_asset = $this->app->storage->findOne('cockpit/assets', ['_id' => $asset['_id']]);

        $origPath   = $_asset['path'];
        $path_parts = \pathinfo($origPath);
        $dir        = \rtrim(($path_parts['dirname'] ?? ''), '/');
        $name       = $path_parts['filename'];
        $ext        = $path_parts['extension'];
        $basename   = $path_parts['basename'] ?? '';

        // use title by default
        if (!$fileName) {

            if (!isset($asset['title']) || !\is_string($asset['title'])) return false;

            // not changed
            if (!$force && isset($_asset['title']) && $_asset['title'] === $asset['title']) return false;

            $fileName = \trim($asset['title']);

            if (empty($fileName)) return false;

            // don't rename if title matches file name to avoid img.jpg.jpg
            if ($title == \trim($basename, '/')) return false;

        }

        $newFileName = $this->app->helper('utils')->sluggify($fileName);

        $newPath = "{$dir}/{$newFileName}.{$ext}";

        if ($newPath == $origPath) return false;

        if ($this->app->filestorage->has('assets://'.$asset['path'])) {

            $num = 0;
            while ($this->app->filestorage->has("assets://{$newPath}")) {
                $num++;
                $newPath = "{$dir}/{$newFileName}-{$num}.{$ext}";

                if ($newPath == $origPath) return false;
            }

            $this->app->filestorage->rename('assets://'.$asset['path'], $newPath);

        }

        $asset['path'] = $newPath;


        if (isset($asset['sizes']) && \is_array($asset['sizes'])) {

            foreach ($asset['sizes'] as &$profile) {

                $path_parts = \pathinfo($profile['path']);
                $dir = $path_parts['dirname'] ?? '';
                $dir = \rtrim($dir, '/');

                $newPath = "{$dir}/{$newFileName}.{$ext}";

                if ($this->app->filestorage->has('assets://'.$profile['path'])) {

                    $num = 0;
                    while ($this->app->filestorage->has("assets://{$newPath}")) {
                        $num++;
                        $newPath = "{$dir}/{$newFileName}-{$num}.{$ext}";
                    }

                    $this->app->filestorage->rename('assets://'.$profile['path'], $newPath);

                    $profile['path'] = $newPath;

                }

            }
        }

        return ['asset' => $asset];

    },

]);

$this->on('cockpit.asset.upload', function(&$asset, &$_meta, &$opts, &$file, &$path) {

    $c = $this->module('imageresize')->getConfig();

    $isImage = isset($asset['image']) && $asset['image'];
    $isSVG   = \preg_match('/svg/', $asset['mime']);

    // change only custom directory
    if (!$c['prettyNames'] && \is_string($c['customFolder'])) {
        $dir = '/'.\trim($c['customFolder'], '/').'/';
        if ($dir == '//') $dir = '/';
        $path = \str_replace('/'.\date('Y/m/d').'/', $dir, $path);
        $asset['path'] = $path;
    }

    // prettify file names and change custom dir
    if ($c['prettyNames']) {
        $asset = $this->module('imageresize')->changePath($asset, $file);
        $path  = $asset['path'];
    }

    if (!$isImage) return;

    // create extra images from profiles
    if (!$c['resize'] && \is_array($c['profiles']) && !$isSVG) {
        foreach ($c['profiles'] as $name => $options) {
            if ($resized = $this->module('imageresize')->createResizedAssetFromProfile($asset, $file, $opts, $name, $options)) {
                $asset['sizes'][$name] = $resized;
            }
        }
    }

    // replace uploaded file with resized file
    if (!$c['resize'] && $c['optimize']) {
        $this->module('imageresize')->optimize($file);
        $asset['size'] = \filesize($file);
        $asset['optimized'] = true;
    }

    // run all steps
    if ($c['resize']) {
        $ret = $this->module('imageresize')->replaceAsset($asset, $opts, $file);
        if ($ret && \is_array($ret)) $asset = $ret;
    }

});

// remove original image copy and custom sizes (profiles)
$this->on('cockpit.assets.remove', function($assets) {

    $c = $this->module('imageresize')->getConfig();

    if (!$c['resize']) return;

    foreach($assets as $asset) {
        if (isset($asset['sizes']) && \is_array($asset['sizes'])) {
            foreach ($asset['sizes'] as $file) {
                if ($this->filestorage->has('assets://'.\trim($file['path'], '/'))) {
                    $this->filestorage->delete('assets://'.\trim($file['path'], '/'));
                }
            }
        }
    }

});

// sync file names with image titles (useful for SEO) - experimental
$this->on('cockpit.asset.save', function(&$asset) {

    $isUpdate = isset($asset['_id']);

    if (!$isUpdate) return;

    $c = $this->module('imageresize')->getConfig();

    if (!$c['syncFileNamesWithTitle']) return;

    $ret = $this->module('imageresize')->updateFileName($asset);

    if ($ret && \is_array($ret) && isset($ret['asset'])) {

        $asset = $ret['asset'];

        // after changing the file paths, all assets, galleries etc. in collection entries
        // and singletons should be updated...
        $this->trigger('imageresize.asset.path.update', [$asset]);
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
