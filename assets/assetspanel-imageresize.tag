<assetspanel-imageresize>

    <div class="" if="{ !isImage }">
        <div class="uk-width-medium-2-3">
            <div class="uk-panel uk-panel-box uk-panel-card uk-panel-space">
                <div class="uk-form-row">
                    <label class="uk-text-small uk-text-bold">{ App.i18n.get('Title') }</label>
                    <input class="uk-width-1-1" type="text" bind="asset.title" required>
                </div>

                <div class="uk-form-row">
                    <a class="uk-button" onclick="{ updateFileName }">{ App.i18n.get('Rename file to title') }</a>
                    { App.i18n.get('Path') }: <code>{ asset.path }</code>
                </div>
            </div>
        </div>
    </div>

    <div class="uk-grid" if="{ isImage }">

        <div class="uk-width-medium-1-2 uk-width-xlarge-1-3">
            <div class="uk-panel uk-panel-box uk-panel-card uk-panel-space uk-text-center">
                <div class="uk-display-inline-block uk-position-relative asset-fp-image">
                    <cp-thumbnail src="{ASSETS_URL+asset.path}" width="800"></cp-thumbnail>
                    <div class="cp-assets-fp" title="Focal Point" data-uk-tooltip></div>
                </div>
                <div class="uk-margin-top uk-text-small uk-text-muted">
                    <a href="{ASSETS_URL+asset.path}" target="_blank"  title="{ App.i18n.get('Direct link to asset') }" data-uk-tooltip><i class="uk-icon-button uk-icon-button-outline uk-text-primary uk-icon-link"></i></a>

                    <a if="{ asset.sizes }" each="{ options, name in asset.sizes }" href="{ASSETS_URL+options.path}" target="_blank" title="{ App.i18n.get('Direct link to asset')+' ('+name+')' }" data-uk-tooltip><i class="uk-icon-button uk-text-primary uk-icon-link uk-margin-small-left"></i></a>
                </div>
            </div>

            <div class="uk-panel uk-panel-box uk-panel-card uk-panel-space">
                <div class="uk-form-row">
                    <label class="uk-text-small uk-text-bold">{ App.i18n.get('Title') }</label>
                    <input class="uk-width-1-1" type="text" bind="asset.title" required>
                </div>

                <div class="uk-form-row">
                    <a class="uk-button" onclick="{ updateFileName }">{ App.i18n.get('Rename file to title') }</a>
                    { App.i18n.get('Path') }: <code>{ asset.path }</code>
                </div>
            </div>
        </div>

        <div class="uk-width-medium-1-2 uk-width-xlarge-2-3" if="{ profiles && !isSVG }">

            <div ref="dynamicgrid" class="uk-grid uk-grid-small uk-grid-match uk-grid-width-xlarge-1-2" data-uk-grid-margin>
                <div class="" each="{ profile, idx in profiles }">
                    <div class="uk-panel uk-panel-box uk-panel-card uk-panel-header">

                        <div class="uk-panel-title uk-flex uk-flex-middle">
                            <div class="uk-flex-item-1">
                                <span>{ idx }</span>
                                <span class="uk-text-small">{ [profile.width, profile.height].join('x') }</span>
                                <span class="uk-text-small">{ profile.method || 'thumbnail' }</span>
                            </div>
                            <div if="{ asset.sizes && asset.sizes[idx] }">
                                <a href="{ASSETS_URL+asset.sizes[idx].path}" target="_blank" title="{ App.i18n.get('Direct link to asset')+' ('+idx+')' }" data-uk-tooltip><i class="uk-icon-link uk-icon-hover"></i></a>
                            </div>
                            <a class="uk-button-small" data-size="{idx}" onclick="{ generateSize }">{ asset.sizes && asset.sizes[idx] ? App.i18n.get('Regenerate') : App.i18n.get('Create') }</a>
                        </div>

                        <div class="uk-text-center">
                            <cp-thumbnail src="{ ASSETS_URL+asset.path+(cachebreaker[idx] || '') }" width="{ profile.width }" height="{ profile.height }" mode="{ profile.method || 'thumbnail' }"></cp-thumbnail>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>

    var $this = this;

    this.asset    = opts.asset;
    this.isImage  = false;
    this.isSVG    = false;
    this.profiles = this.parent && this.parent.parent && this.parent.parent.profiles
                    ? this.parent.parent.profiles : null;

    this.cachebreaker = {};
    this.isFocalPointSynced = false;

    this.on('mount', function() {

        this.isImage = this.asset.mime.match(/^image\//);
        this.isSVG   = this.isImage && this.asset.mime.match(/svg/);

        if (this.isImage) {

            if (!$this.profiles) {
                App.request('/imageresize/getProfiles').then(function(data) {
                    $this.profiles = data;
                    $this.update();
                });
            }

        }

    });

    this.on('update', function() {

        if (!this.isImage || this.isSVG) return;

        // fix data-uk-grid-margin - data-uk-observe doesn't work :-(
        App.$(this.refs.dynamicgrid).trigger('display.uk.check');

        // display focal point
        if (!this.isFocalPointSynced) {

            setTimeout(function() {
                $this.syncFocalPointWithParent();
            }, 500);

        }

    });

    this.syncFocalPointWithParent = function() {

        var parentFp = App.$(this.parent.root).find('.cp-assets-fp').get(0);
        var targetFp = App.$(this.root).find('.cp-assets-fp').get(0);

        if (parentFp && targetFp) {
            targetFp.style.left       = parentFp.style.left;
            targetFp.style.top        = parentFp.style.top;
            targetFp.style.visibility = parentFp.style.visibility;

            this.isFocalPointSynced = true;
        }

    }

    generateSize(e) {

        if (e) e.preventDefault();

        var size = e.target.dataset.size;

        var data = {
            asset: this.asset,
            name: size
        };

        App.request('/imageresize/addResizedAsset', data).then(function(data) {

            if (typeof $this.asset.sizes == 'undefined') {
                $this.asset.sizes = {};
            }

            $this.asset.sizes[size] = data;

            $this.cachebreaker[size] = '&v='+new Date().getTime();

            $this.parent.updateAsset();

        });

    }

    updateFileName(e) {

        if (e) e.preventDefault();

        App.ui.confirm("Are you sure?", function() {

            App.request('/imageresize/updateFileName', {asset: $this.asset}).then(function(data) {

                if (data && data.asset) {

                    App.$.extend($this.asset, data.asset);

                    // update asset in parent list
                    if ($this.parent && $this.parent.parent && $this.parent.parent.asset) {
                        App.$.extend($this.parent.parent.asset, data.asset);
                    }

                    App.ui.notify("Asset updated", "success");
                    $this.update();

                }

                if (!data || data.error) {
                    App.ui.notify(data && data.error ? data.error : 'Renaming failed', 'danger');
                }

            });
        });

    }

    </script>

</assetspanel-imageresize>
