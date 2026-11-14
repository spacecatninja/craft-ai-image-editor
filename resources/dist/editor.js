/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

/** global: Craft */
/** global: Garnish */
/** global: $ */

if (typeof Craft.AiImageEditor === 'undefined') {
  Craft.AiImageEditor = {};
}

/**
 * The AI image editor modal. Mirrors the chrome of Craft's native image
 * editor (Craft.AssetImageEditor): a large, image-dominated modal with a
 * bottom tool bar, except the "tool" here is a natural-language prompt.
 */
Craft.AiImageEditor.Editor = Garnish.Modal.extend(
  {
    assetId: null,
    session: null,
    busy: false,
    blocked: false,
    saved: false,
    mode: 'edit',
    compareData: null,
    compareSelection: 'after',
    selectedTurnId: null,

    $body: null,
    $footer: null,
    $imageContainer: null,
    $image: null,
    $img: null,
    $emptyHint: null,
    $imageOverlay: null,
    $imageTools: null,
    $composerWrap: null,
    $strip: null,
    $stripToggle: null,
    $stripWrap: null,
    $revertBtn: null,
    $message: null,
    $promptBar: null,
    $promptInput: null,
    $modelSelect: null,
    $ratioSelect: null,
    $finalResSelect: null,
    $outputFormatSelect: null,
    $presets: null,
    _lastTurnArgs: null,
    $preciseLabel: null,
    $preciseCheckbox: null,
    $applyBtn: null,
    $compareBar: null,
    $note: null,
    $buttons: null,
    $discardBtn: null,
    $acceptBtn: null,
    $backBtn: null,
    $saveNewBtn: null,
    $saveReplaceBtn: null,

    init: function (assetId, settings) {
      this.assetId = assetId;
      this.setSettings(settings, Craft.AiImageEditor.Editor.defaults);

      this.$container = $(
        '<form class="modal fitted imageeditor aiimageeditor" accept-charset="UTF-8"></form>'
      ).appendTo(Garnish.$bod);
      this.$body = $('<div class="body"></div>').appendTo(this.$container);
      this.$footer = $('<div class="footer"/>').appendTo(this.$container);

      this.base(this.$container, this.settings);

      // Like the native image editor: closing is an explicit choice.
      this.removeListener(this.$shade, 'click');

      this._buildImageArea();
      this._buildFooter();

      this.addListener(this.$container, 'submit', 'handleSubmit');

      this.on('fadeOut', () => {
        this.destroy();
      });

      this._createSession();
    },

    // Session lifecycle
    // -------------------------------------------------------------------

    _createSession: function () {
      this._setBusy(true, Craft.t('ai-image-editor', 'Working…'));

      Craft.sendActionRequest('POST', 'ai-image-editor/sessions/create', {
        data: this.assetId
          ? {assetId: this.assetId}
          : {folderId: this.settings.folderId || 0},
      })
        .then((response) => {
          this._setBusy(false);

          if (!response.data.success) {
            this._showBlockedState(response.data.message);
            return;
          }

          this.session = response.data.session;
          this._renderSession();
        })
        .catch((error) => {
          this._setBusy(false);
          this._showBlockedState(this._errorMessage(error));
        });
    },

    _renderSession: function () {
      const providerNames = this.session.providerNames || [this.session.driverName];
      const provider =
        providerNames.length > 1
          ? providerNames.slice(0, -1).join(', ') +
            ' ' +
            Craft.t('ai-image-editor', 'and') +
            ' ' +
            providerNames[providerNames.length - 1]
          : providerNames[0];

      this.$note.text(
        Craft.t('ai-image-editor', 'Images and prompts are sent to {provider} for processing.', {
          provider: provider,
        })
      );

      if (this.session.isGeneration) {
        const placeholder = Craft.t('ai-image-editor', 'Describe the image you want to create…');
        this.$promptInput.attr('placeholder', placeholder).attr('aria-label', placeholder);
      }

      this._populateModelSelect();
      this._populateRatioSelect();
      this._populateOutputFormatSelect();
      this._populateFinalResSelect(this.session.finalResolution);
      this._populatePresets();
      this._rebuildStrip();

      const turns = this._workingTurns();

      if (turns.length) {
        this._showImage(turns[turns.length - 1].imageUrl, turns[turns.length - 1].id);
      } else if (this.session.sourceImageUrl) {
        this._showImage(this.session.sourceImageUrl, 'source');
      } else {
        this._showEmptyState();
      }

      this._updateAcceptState();
      this.$promptInput.trigger('focus');
      this.updateSizeAndPosition();
    },

    // Edit turns
    // -------------------------------------------------------------------

    handleSubmit: function (ev) {
      ev.preventDefault();

      if (this.mode !== 'edit') {
        return;
      }

      this.runTurn();
    },

    runTurn: function (presetPrompt, presetPrecise) {
      if (this.busy || !this.session) {
        return;
      }

      // A preset click passes its prompt directly; otherwise use the textarea.
      const fromPreset = typeof presetPrompt === 'string';
      const prompt = (fromPreset ? presetPrompt : this.$promptInput.val() || '').trim();

      if (!prompt) {
        return;
      }

      const precise =
        fromPreset && typeof presetPrecise === 'boolean'
          ? presetPrecise
          : this.$preciseCheckbox.prop('checked');

      // Remembered so a failed action can be retried with the same input.
      this._lastTurnArgs = fromPreset ? [presetPrompt, presetPrecise] : [];

      this._clearMessage();
      this._setBusy(true, Craft.t('ai-image-editor', 'Working…'));

      Craft.sendActionRequest('POST', 'ai-image-editor/sessions/turn', {
        data: {
          sessionId: this.session.id,
          prompt: prompt,
          precise: precise ? 1 : 0,
          aspectRatio: this.$ratioSelect.val() || '',
          outputFormat: this.$outputFormatSelect.val() || '',
          model: this.$modelSelect.val() || '',
        },
      })
        .then((response) => {
          this._setBusy(false);

          if (!response.data.success) {
            // Keep the prompt text so the user can retry or rephrase.
            this._showMessage(
              response.data.message,
              response.data.refusal ? 'refusal' : 'error',
              true,
              response.data.retryAfter
            );
            return;
          }

          this.session.turns.push(response.data.turn);

          // A preset run must not wipe whatever the user has typed.
          if (!fromPreset) {
            this.$promptInput.val('');
            this._autoGrow();
            this._updateSendState();
          }

          this._rebuildStrip();
          this._showImage(response.data.turn.imageUrl, response.data.turn.id);
          this._updateAcceptState();
          this.$promptInput.trigger('focus');
        })
        .catch((error) => {
          this._setBusy(false);
          this._showMessage(this._errorMessage(error), 'error', true);
        });
    },

    // Finalize flow
    // -------------------------------------------------------------------

    startFinalize: function () {
      const turns = this._workingTurns();

      if (this.busy || !turns.length) {
        return;
      }

      const finalResolution = this.$finalResSelect.val() || this.session.finalResolution;

      // No point regenerating if the working and final tiers are the same,
      // go straight to choosing how to save.
      if (this.session.workingResolution === finalResolution) {
        this._enterCompareMode(turns[turns.length - 1], null);
        return;
      }

      this._clearMessage();
      this._setBusy(true, Craft.t('ai-image-editor', 'Generating the final version…'));

      Craft.sendActionRequest('POST', 'ai-image-editor/sessions/finalize', {
        data: {
          sessionId: this.session.id,
          model: this.$modelSelect.val() || '',
          finalResolution: finalResolution,
          outputFormat: this.$outputFormatSelect.val() || '',
        },
      })
        .then((response) => {
          this._setBusy(false);

          if (!response.data.success) {
            this._showMessage(
              response.data.message ||
                Craft.t('ai-image-editor', 'The high resolution version could not be generated.'),
              'error'
            );
            // The working result can still be saved directly.
            return;
          }

          this._enterCompareMode(response.data.before, response.data.after);
        })
        .catch((error) => {
          this._setBusy(false);
          this._showMessage(this._errorMessage(error), 'error');
        });
    },

    confirmSave: function (replaceOriginal) {
      if (this.busy) {
        return;
      }

      // Save whichever version is selected in the compare toggle. Without a
      // finalize candidate there is only the working result.
      const useWorkingResolution =
        !this.compareData ||
        !this.compareData.after ||
        this.compareSelection === 'before';

      this._clearMessage();
      this._setBusy(true, Craft.t('ai-image-editor', 'Working…'));

      Craft.sendActionRequest('POST', 'ai-image-editor/sessions/confirm', {
        data: {
          sessionId: this.session.id,
          useWorkingResolution: useWorkingResolution ? 1 : 0,
          replaceOriginal: replaceOriginal ? 1 : 0,
        },
      })
        .then((response) => {
          this._setBusy(false);

          if (!response.data.success) {
            this._showMessage(response.data.message, 'error');
            return;
          }

          this.saved = true;
          Craft.cp.displayNotice(
            Craft.t('ai-image-editor', 'Edited image saved as {filename}.', {
              filename: response.data.asset.filename,
            })
          );
          this.settings.onSave(response.data);

          // On the asset's own edit screen, follow the result: reload after a
          // replace, or make the newly created asset the active one.
          if (this.settings.isEditScreen) {
            if (response.data.replaced) {
              window.location.reload();
              return;
            }

            if (response.data.asset && response.data.asset.cpEditUrl) {
              window.location.href = response.data.asset.cpEditUrl;
              return;
            }
          }

          this.hide();
        })
        .catch((error) => {
          this._setBusy(false);
          this._showMessage(this._errorMessage(error), 'error');
        });
    },

    _enterCompareMode: function (before, after) {
      this.mode = 'compare';
      this.compareData = {before: before, after: after};
      this.compareSelection = after ? 'after' : 'before';

      this.$promptBar.addClass('hidden');
      this.$stripWrap.addClass('hidden');
      this.$acceptBtn.addClass('hidden');
      this.$discardBtn.addClass('hidden');
      this.$backBtn.removeClass('hidden');
      this.$saveNewBtn.removeClass('hidden');

      // A generated image has no original to replace.
      if (!this.session.isGeneration) {
        this.$saveReplaceBtn.removeClass('hidden');
      }

      if (after) {
        this._buildCompareBar();
        this._showImage(after.imageUrl, 'compare-after');
        this._showMessage(
          Craft.t('ai-image-editor', 'Compare the draft and final versions, then choose which one to save.'),
          'info'
        );
      } else if (before) {
        this._showImage(before.imageUrl, 'compare-before');
      }

      this._updateSendState();
    },

    exitCompareMode: function () {
      this.mode = 'edit';
      this.compareData = null;

      if (this.$compareBar) {
        this.$compareBar.remove();
        this.$compareBar = null;
      }

      this.$promptBar.removeClass('hidden');
      this.$stripWrap.removeClass('hidden');
      this.$acceptBtn.removeClass('hidden');
      this.$discardBtn.removeClass('hidden');
      this.$backBtn.addClass('hidden');
      this.$saveNewBtn.addClass('hidden');
      this.$saveReplaceBtn.addClass('hidden');
      this._clearMessage();

      const turns = this._workingTurns();

      if (turns.length) {
        this._showImage(turns[turns.length - 1].imageUrl, turns[turns.length - 1].id);
      }

      this._updateSendState();
    },

    _buildCompareBar: function () {
      if (this.$compareBar) {
        this.$compareBar.remove();
      }

      this.$compareBar = $('<div class="ai-compare"/>').appendTo(this.$composerWrap);

      const $workingBtn = $('<button/>', {
        type: 'button',
        class: 'btn',
        text: Craft.t('ai-image-editor', 'Draft'),
        title: this.compareData.before.resolution,
      }).appendTo(this.$compareBar);

      const $finalBtn = $('<button/>', {
        type: 'button',
        class: 'btn active',
        text: Craft.t('ai-image-editor', 'Final'),
        title: this.compareData.after.resolution,
      }).appendTo(this.$compareBar);

      this.addListener($workingBtn, 'activate', () => {
        $finalBtn.removeClass('active');
        $workingBtn.addClass('active');
        this.compareSelection = 'before';
        this._showImage(this.compareData.before.imageUrl, 'compare-before');
      });

      this.addListener($finalBtn, 'activate', () => {
        $workingBtn.removeClass('active');
        $finalBtn.addClass('active');
        this.compareSelection = 'after';
        this._showImage(this.compareData.after.imageUrl, 'compare-after');
      });
    },

    // Closing
    // -------------------------------------------------------------------

    maybeClose: function () {
      if (this.saved) {
        this.hide();
        return;
      }

      if (
        this.session &&
        this._workingTurns().length &&
        !window.confirm(Craft.t('ai-image-editor', 'Discard this session and all edits?'))
      ) {
        return;
      }

      if (this.session) {
        // Fire and forget; abandoned sessions get purged server side anyway.
        Craft.sendActionRequest('POST', 'ai-image-editor/sessions/discard', {
          data: {sessionId: this.session.id},
        }).catch(() => {});
      }

      this.hide();
    },

    // UI construction
    // -------------------------------------------------------------------

    _buildImageArea: function () {
      this.$imageContainer = $('<div class="image-container"/>').appendTo(this.$body);
      this.$image = $('<div class="image ai-image"/>').appendTo(this.$imageContainer);
      this.$img = $('<img class="ai-current-image" alt=""/>').appendTo(this.$image);
      this.$imageOverlay = $('<div class="ai-image-overlay hidden"/>').appendTo(this.$image);
      const $overlayChip = $('<div class="ai-overlay-chip"/>').appendTo(this.$imageOverlay);
      $overlayChip.append('<div class="spinner big"/>');
      $overlayChip.append('<div class="ai-overlay-label"/>');

      this.$imageTools = $('<div class="image-tools ai-tools"/>').appendTo(this.$imageContainer);

      // Everything below the image lives in one constrained, centered column.
      this.$composerWrap = $('<div class="ai-composer-wrap"/>').appendTo(this.$imageTools);

      // Shown above the composer while a generation session has no draft yet.
      this.$emptyHint = $('<div class="ai-empty-hint hidden"/>').appendTo(this.$composerWrap);
      this.$emptyHint.text(
        Craft.t('ai-image-editor', 'Describe the image you want to create, then apply to generate the first draft.')
      );

      this.$message = $('<div class="ai-message hidden" role="status"/>').appendTo(this.$composerWrap);

      // The collapsible turn strip.
      this.$stripWrap = $('<div class="ai-strip-wrap"/>').appendTo(this.$composerWrap);
      this.$stripToggle = $('<button/>', {
        type: 'button',
        class: 'ai-strip-toggle',
        'aria-expanded': 'true',
      }).appendTo(this.$stripWrap);
      this.$strip = $('<div class="ai-strip"/>').appendTo(this.$stripWrap);

      this.addListener(this.$stripToggle, 'activate', () => {
        const collapsed = this.$strip.toggleClass('hidden').hasClass('hidden');
        this.$stripToggle.attr('aria-expanded', collapsed ? 'false' : 'true');
        this.$stripWrap.toggleClass('collapsed', collapsed);
      });

      // Appears when an earlier draft is selected, to revert the session to it.
      this.$revertBtn = $('<button/>', {
        type: 'button',
        class: 'btn small ai-revert hidden',
        text: Craft.t('ai-image-editor', 'Revert to this version'),
      }).appendTo(this.$stripWrap);

      this.addListener(this.$revertBtn, 'activate', 'revert');

      // The composer, this editor's equivalent of the native tool icons: an
      // integrated card with the prompt on top and the controls below.
      this.$promptBar = $('<div class="ai-composer"/>').appendTo(this.$composerWrap);

      // Config-defined quick actions, sitting above the prompt input.
      this.$presets = $('<div/>', {
        class: 'ai-presets hidden',
        role: 'group',
        'aria-label': Craft.t('ai-image-editor', 'Quick actions'),
      }).appendTo(this.$promptBar);

      this.$promptInput = $('<textarea/>', {
        class: 'ai-prompt-input',
        rows: 1,
        placeholder: Craft.t('ai-image-editor', 'Describe the change you want to make…'),
        'aria-label': Craft.t('ai-image-editor', 'Describe the change you want to make…'),
      }).appendTo(this.$promptBar);

      const $row = $('<div class="ai-composer-row"/>').appendTo(this.$promptBar);
      const $options = $('<div class="ai-composer-options"/>').appendTo($row);

      // The model used for edits and finalize, switchable per session.
      const $modelWrap = $('<div class="ai-pill ai-pill--select ai-model"/>').appendTo($options);
      this.$modelSelect = $('<select/>', {
        'aria-label': Craft.t('ai-image-editor', 'Model'),
      }).appendTo($modelWrap);

      // Output aspect ratio, populated from the session payload. "Match
      // original" (empty value) lets the output follow the working image.
      const $ratioWrap = $('<div class="ai-pill ai-pill--select ai-ratio"/>').appendTo($options);
      this.$ratioSelect = $('<select/>', {
        'aria-label': Craft.t('ai-image-editor', 'Aspect ratio'),
      }).appendTo($ratioWrap);

      // Output image format, defaulting to the source image's format.
      const $formatWrap = $('<div class="ai-pill ai-pill--select ai-format"/>').appendTo($options);
      this.$outputFormatSelect = $('<select/>', {
        'aria-label': Craft.t('ai-image-editor', 'Output format'),
      }).appendTo($formatWrap);

      // The resolution used for the finalize regeneration when accepting.
      const $finalWrap = $('<div class="ai-pill ai-pill--select ai-final"/>').appendTo($options);
      $finalWrap.append(
        $('<span/>', {class: 'ai-pill-prefix', text: Craft.t('ai-image-editor', 'Final')})
      );
      this.$finalResSelect = $('<select/>', {
        'aria-label': Craft.t('ai-image-editor', 'Final resolution'),
      }).appendTo($finalWrap);

      this.addListener(this.$modelSelect, 'change', () => {
        this._populateFinalResSelect();
      });

      // Biases the model toward minimal, faithful edits. On by default.
      this.$preciseLabel = $('<label class="ai-pill ai-precise"/>').appendTo($options);
      this.$preciseCheckbox = $('<input/>', {
        type: 'checkbox',
        checked: true,
      }).appendTo(this.$preciseLabel);
      this.$preciseLabel.append(
        $('<span/>', {text: Craft.t('ai-image-editor', 'Precise edits')})
      );

      this.$applyBtn = $('<button/>', {
        type: 'submit',
        class: 'ai-send',
        disabled: true,
        title: Craft.t('ai-image-editor', 'Apply'),
        'aria-label': Craft.t('ai-image-editor', 'Apply'),
      }).appendTo($row);
      this.$applyBtn.append(
        '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 20V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
      );

      this.addListener(this.$promptInput, 'input', () => {
        this._autoGrow();
        this._updateSendState();
      });

      // Enter sends, Shift+Enter makes a newline, like other AI composers.
      this.addListener(this.$promptInput, 'keydown', (ev) => {
        if (ev.keyCode === Garnish.RETURN_KEY && !ev.shiftKey) {
          ev.preventDefault();
          this.runTurn();
        }
      });
    },

    _buildFooter: function () {
      this.$note = $('<div class="ai-note light"/>').appendTo(this.$footer);
      this.$buttons = $('<div class="buttons right"/>').appendTo(this.$footer);

      this.$discardBtn = $('<button/>', {
        type: 'button',
        class: 'btn cancel',
        text: Craft.t('ai-image-editor', 'Discard'),
      }).appendTo(this.$buttons);

      this.$acceptBtn = Craft.ui
        .createButton({
          class: 'save submit disabled',
          label: Craft.t('ai-image-editor', 'Accept & Save'),
          spinner: true,
        })
        .attr('disabled', true)
        .appendTo(this.$buttons);

      this.$backBtn = $('<button/>', {
        type: 'button',
        class: 'btn hidden',
        text: Craft.t('ai-image-editor', 'Back to editing'),
      }).appendTo(this.$buttons);

      // Mirrors the native editor's two save actions, but with "Save as a
      // new asset" as the primary default. Replacing the original is the
      // destructive choice, so it's secondary and sits behind a confirmation.
      this.$saveReplaceBtn = Craft.ui
        .createButton({
          class: 'save replace hidden',
          label: Craft.t('ai-image-editor', 'Save'),
          spinner: true,
        })
        .appendTo(this.$buttons);

      this.$saveNewBtn = Craft.ui
        .createSubmitButton({
          class: 'save copy hidden',
          label: Craft.t('ai-image-editor', 'Save as a new asset'),
          spinner: true,
        })
        .appendTo(this.$buttons);

      this.addListener(this.$discardBtn, 'activate', 'maybeClose');
      this.addListener(this.$acceptBtn, 'activate', () => {
        this.startFinalize();
      });
      this.addListener(this.$backBtn, 'activate', 'exitCompareMode');
      this.addListener(this.$saveNewBtn, 'activate', () => {
        this.confirmSave(false);
      });
      this.addListener(this.$saveReplaceBtn, 'activate', () => {
        if (
          !window.confirm(
            Craft.t(
              'ai-image-editor',
              'Replace the original image? This will permanently overwrite the current file.'
            )
          )
        ) {
          return;
        }

        this.confirmSave(true);
      });
    },

    _populateModelSelect: function () {
      this.$modelSelect.empty();

      const models = this.session.models || [];

      for (let i = 0; i < models.length; i++) {
        $('<option/>', {value: models[i].value, text: models[i].label}).appendTo(this.$modelSelect);
      }

      this.$modelSelect.val(this.session.model);
      this.$modelSelect.parent().toggleClass('hidden', models.length < 2);
    },

    _populateFinalResSelect: function (preferred) {
      const current = preferred || this.$finalResSelect.val() || this.session.finalResolution;
      const models = this.session.models || [];
      const info = models.find((model) => model.value === this.$modelSelect.val());
      const resolutions =
        info && info.resolutions && info.resolutions.length
          ? info.resolutions
          : [this.session.finalResolution];

      this.$finalResSelect.empty();

      for (let i = 0; i < resolutions.length; i++) {
        $('<option/>', {value: resolutions[i], text: resolutions[i]}).appendTo(this.$finalResSelect);
      }

      this.$finalResSelect.val(
        resolutions.includes(current) ? current : resolutions[resolutions.length - 1]
      );
    },

    _populateRatioSelect: function () {
      this.$ratioSelect.empty();

      $('<option/>', {
        value: '',
        text: this.session.isGeneration
          ? Craft.t('ai-image-editor', 'Auto')
          : Craft.t('ai-image-editor', 'Match original'),
      }).appendTo(this.$ratioSelect);

      const ratios = this.session.aspectRatios || [];

      for (let i = 0; i < ratios.length; i++) {
        $('<option/>', {value: ratios[i], text: ratios[i]}).appendTo(this.$ratioSelect);
      }

      this.$ratioSelect.parent().toggleClass('hidden', !ratios.length);
    },

    _populateOutputFormatSelect: function () {
      this.$outputFormatSelect.empty();

      const formats = this.session.outputFormats || [];

      for (let i = 0; i < formats.length; i++) {
        const label = formats[i] === 'webp' ? 'WebP' : formats[i].toUpperCase();
        $('<option/>', {value: formats[i], text: label}).appendTo(this.$outputFormatSelect);
      }

      if (this.session.defaultOutputFormat) {
        this.$outputFormatSelect.val(this.session.defaultOutputFormat);
      }

      // Only worth showing once there's an actual choice to make.
      this.$outputFormatSelect.parent().toggleClass('hidden', formats.length < 2);
    },

    _populatePresets: function () {
      this.$presets.empty();

      const presets = (this.session && this.session.presets) || [];

      for (let i = 0; i < presets.length; i++) {
        const preset = presets[i];
        const $chip = $('<button/>', {
          type: 'button',
          class: 'ai-preset',
          text: preset.label,
        }).appendTo(this.$presets);

        this.addListener($chip, 'activate', () => {
          this.runTurn(preset.prompt, typeof preset.precise === 'boolean' ? preset.precise : undefined);
        });
      }

      this._updatePresetsState();
    },

    _updatePresetsState: function () {
      if (!this.$presets) {
        return;
      }

      const presets = (this.session && this.session.presets) || [];
      // Quick actions act on an existing image, so hide them in the
      // generation empty state and outside edit mode.
      const hasImage = !this.session.isGeneration || this._workingTurns().length > 0;

      this.$presets.toggleClass('hidden', presets.length === 0 || !hasImage || this.mode !== 'edit');
    },

    _rebuildStrip: function () {
      this.$strip.empty();

      const turns = this._workingTurns();
      const hasSource = !!this.session.sourceImageUrl;

      // The strip only earns its place once there are two versions to flip between.
      this.$stripWrap.toggleClass('hidden', turns.length + (hasSource ? 1 : 0) < 2);

      if (hasSource) {
        this._addThumb(
          this.session.sourceImageUrl,
          'source',
          Craft.t('ai-image-editor', 'Original')
        );
      }

      for (let i = 0; i < turns.length; i++) {
        this._addThumb(
          turns[i].imageUrl,
          turns[i].id,
          turns[i].prompt || Craft.t('ai-image-editor', 'Turn {number}', {number: i + 1})
        );
      }
    },

    _addThumb: function (imageUrl, key, title) {
      const $thumb = $('<button/>', {
        type: 'button',
        class: 'ai-thumb',
        title: title,
        'aria-label': title,
        'data-key': key,
      }).appendTo(this.$strip);

      $('<img/>', {src: imageUrl, alt: ''}).appendTo($thumb);

      this.addListener($thumb, 'activate', () => {
        if (this.mode !== 'edit') {
          return;
        }

        this._showImage(imageUrl, key);
      });
    },

    _showImage: function (url, key) {
      if (!url) {
        return;
      }

      this.$emptyHint.addClass('hidden');
      this.$imageContainer.removeClass('ai-centered');
      this.$img.removeClass('hidden').attr('src', url);
      this.$strip.find('.ai-thumb').removeClass('sel');
      this.$strip.find('.ai-thumb[data-key="' + key + '"]').addClass('sel');
      this.selectedTurnId = key;
      this._updateRevertState();
      this._updatePresetsState();
    },

    _updateRevertState: function () {
      if (!this.$revertBtn) {
        return;
      }

      const turns = this._workingTurns();
      const latestId = turns.length ? turns[turns.length - 1].id : null;
      const key = this.selectedTurnId;
      const isSource = key === 'source';
      const revertable =
        this.mode === 'edit' &&
        key !== null &&
        (isSource
          ? turns.length > 0
          : latestId !== null && String(key) !== String(latestId));

      this.$revertBtn.toggleClass('hidden', !revertable);
      this.$revertBtn.text(
        isSource
          ? Craft.t('ai-image-editor', 'Revert to original')
          : Craft.t('ai-image-editor', 'Revert to this version')
      );
    },

    revert: function () {
      if (this.busy || this.selectedTurnId === null) {
        return;
      }

      const message =
        this.selectedTurnId === 'source'
          ? Craft.t('ai-image-editor', 'Revert to the original image? All edits will be discarded.')
          : Craft.t('ai-image-editor', 'Revert to this version? Any drafts made after it will be discarded.');

      if (!window.confirm(message)) {
        return;
      }

      const turnId = this.selectedTurnId;

      this._clearMessage();
      this._setBusy(true, Craft.t('ai-image-editor', 'Working…'));

      Craft.sendActionRequest('POST', 'ai-image-editor/sessions/revert', {
        data: {
          sessionId: this.session.id,
          turnId: turnId,
        },
      })
        .then((response) => {
          this._setBusy(false);
          this.session.turns = response.data.session.turns;
          this.selectedTurnId = null;
          this._rebuildStrip();

          const turns = this._workingTurns();

          if (turns.length) {
            this._showImage(turns[turns.length - 1].imageUrl, turns[turns.length - 1].id);
          } else if (this.session.sourceImageUrl) {
            this._showImage(this.session.sourceImageUrl, 'source');
          }

          this._updateAcceptState();
          this._updateRevertState();
        })
        .catch((error) => {
          this._setBusy(false);
          this._showMessage(this._errorMessage(error), 'error', true);
        });
    },

    /**
     * Before the first draft exists there is nothing to show, so the composer
     * takes center stage instead, with a hint above it.
     */
    _showEmptyState: function () {
      this.$img.addClass('hidden');
      this.$imageContainer.addClass('ai-centered');
      this.$emptyHint.removeClass('hidden');
    },

    _showBlockedState: function (message) {
      this.blocked = true;
      this.$promptInput.attr('disabled', true);
      this.$applyBtn.attr('disabled', true).addClass('disabled');
      this.$acceptBtn.attr('disabled', true).addClass('disabled');
      this.$image.addClass('ai-blocked');
      this._showMessage(message || Craft.t('ai-image-editor', 'The image could not be loaded.'), 'error');
    },

    _updateAcceptState: function () {
      const hasTurns = this._workingTurns().length > 0;
      this.$acceptBtn.toggleClass('disabled', !hasTurns).attr('disabled', !hasTurns);
    },

    _workingTurns: function () {
      if (!this.session) {
        return [];
      }

      return this.session.turns.filter((turn) => !turn.isFinal);
    },

    _setBusy: function (busy, label) {
      this.busy = busy;
      this.$container.toggleClass('ai-busy', busy);
      this.$imageOverlay.toggleClass('hidden', !busy);
      this.$imageOverlay.find('.ai-overlay-label').text(label || '');
      this.$promptInput.attr('disabled', busy || null);
      this.$applyBtn.toggleClass('loading', busy);
      this._updateSendState();
    },

    /**
     * Grows the prompt textarea with its content, capped by the CSS max-height.
     * The scrollbar only appears once the cap is reached.
     */
    _autoGrow: function () {
      const input = this.$promptInput[0];
      const maxHeight = 140;
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, maxHeight) + 'px';
      input.style.overflowY = input.scrollHeight > maxHeight ? 'auto' : 'hidden';
    },

    /**
     * The send button is only enabled with a non-empty prompt in an idle,
     * usable editor, mirroring other AI composers.
     */
    _updateSendState: function () {
      const empty = !(this.$promptInput.val() || '').trim();
      this.$applyBtn.prop('disabled', empty || this.busy || this.blocked || this.mode !== 'edit');
    },

    _showMessage: function (message, type, withRetry, retryAfter) {
      this.$message
        .removeClass('hidden ai-message--error ai-message--refusal ai-message--info')
        .addClass('ai-message--' + (type || 'info'))
        .text(message || '');

      if (withRetry) {
        const $retry = $('<button/>', {
          type: 'button',
          class: 'ai-retry',
          text: Craft.t('ai-image-editor', 'Retry'),
        }).appendTo(this.$message);

        // Honor the provider's Retry-After window before allowing a retry.
        if (retryAfter && retryAfter > 0) {
          $retry.attr('disabled', true).addClass('disabled');
          setTimeout(() => {
            $retry.attr('disabled', null).removeClass('disabled');
          }, retryAfter * 1000);
        }

        this.addListener($retry, 'activate', () => {
          this.runTurn.apply(this, this._lastTurnArgs || []);
        });
      }
    },

    _clearMessage: function () {
      this.$message.addClass('hidden').empty();
    },

    _errorMessage: function (error) {
      if (error && error.response && error.response.data && error.response.data.message) {
        return error.response.data.message;
      }

      return Craft.t('ai-image-editor', 'The edit could not be performed. Please try again.');
    },

    // Garnish overrides
    // -------------------------------------------------------------------

    /**
     * Fills the whole viewport, like the native image editor, instead of
     * Garnish.Modal's default fit-to-content sizing.
     */
    updateSizeAndPosition: function () {
      if (!this.$container) {
        return;
      }

      const innerWidth = window.innerWidth;
      const innerHeight = window.innerHeight;

      this.$container.css({
        width: innerWidth,
        'min-width': innerWidth,
        left: 0,
        height: innerHeight,
        'min-height': innerHeight,
        top: 0,
      });

      this.$body.css({
        height: innerHeight - (this.$footer.outerHeight() - 1),
      });

      if (innerWidth < innerHeight) {
        this.$container.addClass('vertical');
      } else {
        this.$container.removeClass('vertical');
      }
    },

    show: function () {
      this.base();

      // Route ESC through the discard confirmation instead of a plain hide.
      Garnish.uiLayerManager.registerShortcut(Garnish.ESC_KEY, () => {
        this.maybeClose();
      });
    },

    destroy: function () {
      this.$container.remove();
      this.base();
    },
  },
  {
    defaults: {
      hideOnEsc: false,
      hideOnShadeClick: false,
      isEditScreen: false,
      folderId: null,
      onSave: $.noop,
    },
  }
);

/**
 * Opens the editor for an asset. Convenience entry point used by the
 * element action and asset action menu items.
 */
Craft.AiImageEditor.open = function (assetId, settings) {
  return new Craft.AiImageEditor.Editor(assetId, settings);
};

/**
 * Adds a "Generate" button to asset index toolbars, both on the full index
 * and inside asset selection modals, opening a generation session that saves
 * into the index's current folder. The button follows the upload button's
 * presence, which the index rebuilds per selected source, so it only shows
 * where the user can save assets. In selection modals the generated asset is
 * pre-selected, so one click on Select puts it in the field.
 */
Craft.AiImageEditor.attachGenerateButton = function (elementIndex) {
  if (
    !elementIndex ||
    elementIndex.elementType !== 'craft\\elements\\Asset' ||
    !['index', 'modal'].includes(elementIndex.settings.context)
  ) {
    return;
  }

  // Gated by the "generate" permission, exposed from PHP.
  if (!(Craft.AiImageEditor && Craft.AiImageEditor.canGenerate)) {
    return;
  }

  if (!elementIndex.$aiGenerateBtn) {
    const $generateBtn = $('<button/>', {
      type: 'button',
      class: 'btn ai-generate-btn',
      text: Craft.t('ai-image-editor', 'Generate'),
    });

    elementIndex.addButton($generateBtn);
    elementIndex.$aiGenerateBtn = $generateBtn;

    $generateBtn.on('activate', () => {
      new Craft.AiImageEditor.Editor(null, {
        folderId: elementIndex.currentFolderId,
        onSave: (data) => {
          if (
            data.asset &&
            elementIndex.settings.context === 'modal' &&
            typeof elementIndex.selectElementAfterUpdate === 'function'
          ) {
            elementIndex.selectElementAfterUpdate(data.asset.id);
          }

          elementIndex.updateElements();
        },
      });
    });
  }

  const canUpload = !!(
    elementIndex.$uploadButton &&
    elementIndex.$uploadButton.length &&
    document.body.contains(elementIndex.$uploadButton[0])
  );

  elementIndex.$aiGenerateBtn.toggleClass('hidden', !canUpload);
};

/**
 * Adds an "Edit with AI" button to an asset edit screen's preview thumbnail,
 * alongside Craft's native "Preview" and "Edit Image" buttons. Called from PHP
 * with the asset id when the edit screen renders for a user who may edit it.
 */
Craft.AiImageEditor.attachEditScreenButton = function (assetId) {
  // The preview thumb renders a single `.image-actions` button row (a child of
  // the thumb container on desktop, a sibling on mobile).
  const $actions = $('.image-actions').first();

  if (!$actions.length || $actions.find('.ai-edit-btn').length) {
    return;
  }

  const $btn = $('<button/>', {
    type: 'button',
    class: 'btn ai-edit-btn',
    text: Craft.t('ai-image-editor', 'Edit with AI'),
  });

  $btn.on('activate', () => {
    Craft.AiImageEditor.open(assetId, {isEditScreen: true});
  });

  $actions.append($btn);
};

Garnish.on(Craft.BaseElementIndex, 'afterInit', (ev) => {
  Craft.AiImageEditor.attachGenerateButton(ev.target);
});

// The upload button is (re)created when a source is selected, so the
// generate button's visibility is re-evaluated then too.
Garnish.on(Craft.BaseElementIndex, 'selectSource', (ev) => {
  Craft.AiImageEditor.attachGenerateButton(ev.target);
});

// If this script arrived after the index initialized (e.g. loaded through an
// ajax response), catch up with the existing instance.
if (Craft.elementIndex) {
  Craft.AiImageEditor.attachGenerateButton(Craft.elementIndex);
}
