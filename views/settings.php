
<div>
    <ul class="uk-breadcrumb">
        <li><a href="@route('/settings')">@lang('Settings')</a></li>
        <li class="uk-active"><span>@lang('ImageResize')</span></li>
    </ul>
</div>


<div class="uk-container-center uk-width-xlarge-3-4 uk-position-relative" riot-view>
    
    <div style="position:absolute;top:-2em;right:0;">
    <field-boolean bind="displayJson" label="JSON"></field-boolean>
    </div>

    <div class="uk-width-1-1 uk-grid uk-grid-small">

        <div class="{displayJson ? 'uk-width-medium-2-3' : 'uk-width-1-1'}">

          <form class="" onsubmit="{ submit }">

              <div class="uk-width-1-1">

                  <div class="uk-panel-box uk-panel-card uk-margin">

                      <div class="uk-grid uk-grid-small uk-grid-match">

                          <div class="uk-panel-box uk-width-1-3">
                              <label class="uk-display-block uk-margin-small">
                                  @lang('Enable automatic image resizing')
                              </label>
                              <field-boolean bind="config.enabled" label="@lang('enabled')"></field-boolean>
                          </div>

                          <div class="uk-panel-box uk-width-1-3">
                              <label class="uk-display-block uk-margin-small">
                                  @lang('Keep original file')
                                  <i class="uk-icon-info-circle uk-margin-small-left" title="@lang('if disabled, the uploaded file will be overwritten with the resized file')" data-uk-tooltip></i>
                              </label>
                              <field-boolean bind="config.keepOriginal" label="@lang('keepOriginal')"></field-boolean>
                          </div>

                          <div class="uk-panel-box uk-width-1-3">
                              <label class="uk-display-block uk-margin-small">
                                  @lang('Folder name for original files')
                                  <i class="uk-icon-info-circle uk-margin-small-left" title="@lang('Your original files will be stored in /uploads/folder_name')" data-uk-tooltip></i>
                              </label>
                              <field-text bind="config.moveOriginalTo"></field-text>
                          </div>

                          <div class="uk-panel-box uk-width-1-4">
                              <label class="uk-display-block uk-margin-small">
                                  @lang('maxWidth')
                                  <i class="uk-icon-info-circle uk-margin-small-left" title="@lang('Set to 0 to deactivate')" data-uk-tooltip></i>
                              </label>
                              <field-text bind="config.maxWidth" type="number"></field-text>
                          </div>

                          <div class="uk-panel-box uk-width-1-4">
                              <label class="uk-display-block uk-margin-small">
                                  @lang('maxHeight')
                                  <i class="uk-icon-info-circle uk-margin-small-left" title="@lang('Set to 0 to deactivate')" data-uk-tooltip></i>
                              </label>
                              <field-text bind="config.maxHeight" type="number"></field-text>
                          </div>

                          <div class="uk-panel-box uk-width-1-4">
                              <label class="uk-display-block uk-margin-small">
                                  @lang('method')
                              </label>
                              <select bind="config.method">
                                  <option value="bestFit">bestFit</option>
                                  <option value="thumbnail">thumbnail</option>
                              </select>
                          </div>

                          <div class="uk-panel-box uk-width-1-4">
                              <label class="uk-display-block uk-margin-small">
                                  @lang('Quality')
                              </label>
                              <field-text bind="config.quality" type="number"></field-text>
                          </div>

                      </div>

                  </div>

              </div>

              <cp-actionbar>
                  <div class="uk-container uk-container-center">
                      <button class="uk-button uk-button-large uk-button-primary">@lang('Save')</button>
                      <a class="uk-button uk-button-link" href="@route('/settings')">
                          <span>@lang('Cancel')</span>
                      </a>
                  </div>
              </cp-actionbar>

          </form>
        </div>

        <div class="uk-width-medium-1-3" show="{displayJson}">
            <pre>{ JSON.stringify(config, null, 2) }</pre>
        </div>

    </div>


    <script type="view/script">

        var $this = this;

        riot.util.bind(this);

        this.config = {{ !empty($config) ? json_encode($config) : '{}' }};
        displayJson = false;

        this.on('mount', function() {

            // bind global command + save
            Mousetrap.bindGlobal(['command+s', 'ctrl+s'], function(e) {
                e.preventDefault();
                $this.submit();
                return false;
            });

            this.update();

        });

        this.on('update', function() {

        });

        submit(e) {

            if (e) e.preventDefault();

            App.request('/imageresize/saveConfig', {config:this.config}).then(function(data) {

               if (data) {
                    App.ui.notify("Saving successful", "success");
                } else {
                    App.ui.notify("Saving failed.", "danger");
                }

            });

        }

    </script>

</div>
