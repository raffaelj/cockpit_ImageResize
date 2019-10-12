<?php

if (!COCKPIT_CLI) return;

if (!$app->module('imageresize')->getConfig('enabled')) {
    return CLI::writeln('ImageResize is not enabled', false);
}

$time = time();

$chunks = (int) $app->param('chunks', 2);

CLI::writeln('Start to resize assets. This may take a while...');

$assets = $app->storage->find('cockpit/assets')->toArray();

$count = count($assets);
$current = 0;

foreach (array_chunk($assets, $chunks) as $i => &$chunk) {

    $chunk = $app->module('imageresize')->replaceAssets($chunk);

    $app->module('cockpit')->updateAssets($chunk);

    $current = $current + count($chunk);
    CLI::writeln('Resized ' . $current . ' of ' . $count . ' assets');

}

CLI::writeln('Finished in ' . (time() - $time) . ' seconds', true);
