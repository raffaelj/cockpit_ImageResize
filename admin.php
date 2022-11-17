<?php

$this->on('admin.init', function() {

    $this('admin')->addAssets([
        'imageresize:assets/cp-thumbnail.tag',
    ]);

    if ($this->module('imageresize')->getConfig('replaceAssetsManager')) {
        $this('admin')->addAssets([
            'imageresize:assets/cp-assets.tag',
            'imageresize:assets/assetspanel-imageresize.tag',
        ]);
    }

    // bind admin routes
    $this->bindClass('ImageResize\\Controller\\Admin', 'imageresize');

    if ($this->module('cockpit')->hasaccess('imageresize', 'manage')) {

        // add settings entry
        $this->on('cockpit.view.settings.item', function () {
            $this->renderView('imageresize:views/partials/settings.php');
        });

    }

});
