<?php

$this->on('admin.init', function() {

    // bind admin routes
    $this->bindClass('ImageResize\\Controller\\Admin', 'imageresize');

    if ($this->module('cockpit')->hasaccess('imageresize', 'manage')) {

        // add settings entry
        $this->on('cockpit.view.settings.item', function () {
            $this->renderView('imageresize:views/partials/settings.php');
        });

    }

});
