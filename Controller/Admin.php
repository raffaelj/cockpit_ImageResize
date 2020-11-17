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

        $options = $profiles[$name];

        // force rebuilding thumbnail when calling from admin ui
        $options['rebuild'] = true;

        return $this->app->module('imageresize')->createResizedAssetFromProfile($asset, null, null, $name, $options);

    }

    public function updateFileName() {

        $asset    = $this->app->param('asset');
        $fileName = null;
        $force    = true;

        $ret = $this->app->module('imageresize')->updateFileName($asset, $fileName, $force);

        if ($ret && \is_array($ret) && isset($ret['asset'])) {
            $asset = $ret['asset'];
            $this->app->storage->save('cockpit/assets', $asset);
        }

        return $ret ? $ret : ['error' => 'Renaming failed'];

    }

}
