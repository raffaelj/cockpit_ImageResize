<?php
/**
 * If you run into memory problems, use the script sequential.
 * 
 * First call the following command and read the output, e. g. "151".
 * 
 * ./cp imageresize/replace --count
 * 
 * Then call the following command and replace "151" with your assets count.
 * Now the script processes only 10 files at once.
 * 
 * for i in `seq 0 10 151`; do ./mp imageresize/replace --skip $i --limit 10 --s --loud; done
 * 
 */


if (!COCKPIT_CLI) return;

if (!$app->module('imageresize')->getConfig('enabled')) {
    return CLI::writeln('ImageResize is not enabled', false);
}

$time = time();

$limit = (int) $app->param('limit', false);
$skip  = $app->param('skip', 0);

// bash argument string "0" turns to (bool) true...
$skip = $skip === true ? 0 : (int) $skip;

$returnCount = $app->param('count', false);
$quiet       = $app->param('quiet', false);
$loud        = $app->param('loud', false);
$sequence    = $app->param('s', false);

$count = $app->storage->count('cockpit/assets');

if ($returnCount) {
    return CLI::writeln($count);
}

if (!$sequence || ($sequence && $skip === 0)) {
    CLI::writeln('Start to resize assets. This may take a while...');
}

$options = [];
if ($skip)  $options['skip'] = $skip;
if ($limit) $options['limit'] = $limit;

$assets = $app->storage->find('cockpit/assets', $options)->toArray();
$total  = count($assets);

foreach ($assets as $i => $asset) {

    $current = $app->module('imageresize')->replaceAssets([$asset]);

    if ($current) {
        $app->module('cockpit')->updateAssets($current);
    }

    if (!$quiet) {
        if ($loud) {

            $memory = cockpit()('utils')->formatSize(\memory_get_usage(true));
            $peak   = cockpit()('utils')->formatSize(\memory_get_peak_usage(true));

            CLI::writeln('mem: ' . $memory . ' | peak: ' . $peak . ' | Processed ' . ($i+1+$skip) . ' of ' . $count . ' | id: ' . $current[0]['_id']);

        } else {
            CLI::writeln('Processed ' . ($i+1+$skip) . ' of ' . $count . ' assets');
        }
    }

}

CLI::writeln('Processed '.$total.' assets in ' . (time() - $time) . ' seconds', true);
