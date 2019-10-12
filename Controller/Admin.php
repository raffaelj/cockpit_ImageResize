<?php

namespace ImageResize\Controller;

class Admin extends \Cockpit\AuthController {

    public function index() {}

    public function settings() {
        
        if (!$this->app->module('cockpit')->hasaccess('imageresize', 'manage')) {
            return $this('admin')->denyRequest();
        }

        $config = $this->app->module('imageresize')->getConfig();

        return $this->render('imageresize:views/settings.php', compact('config'));

    }

    public function saveConfig() {

        if (!$this->app->module('cockpit')->hasaccess('imageresize', 'manage')) {
            return $this('admin')->denyRequest();
        }

        $config = $this->param('config', false);

        if ($config) {
            $this->app->storage->setKey('cockpit/options', 'imageresize', $config);
        }

        return $config;

    }

    public function getProfiles() {

        return $this->app->module('imageresize')->getConfig('profiles');

    }

    public function addResizedAsset() {

        $asset = $this->app->param('asset');
        $name  = $this->app->param('name');

        $profiles = $this->app->module('imageresize')->getConfig('profiles');

        if (!isset($profiles[$name])) return false;

        // force rebuilding thumbnail when calling from admin ui
        $profiles[$name]['rebuild'] = true;

        return $this->app->module('imageresize')->addResizedAsset($asset, $name, $profiles[$name]);

    }

    public function replaceAssets() {

        if (!$this->app->module('cockpit')->hasaccess('imageresize', 'manage')) {
            return $this('admin')->denyRequest();
        }

        if (!$this->app->module('imageresize')->getConfig('enabled')) {
            return $this('admin')->denyRequest();
        }

        $assets = $this->app->storage->find('cockpit/assets')->toArray();

        $assets = $this->app->module('imageresize')->replaceAssets($assets);

        return $this->app->module('cockpit')->updateAssets($assets);

    }

}
