/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

/**
 * The AI image editor: a full-viewport dialog in the style of Craft's image
 * editor, with a natural-language prompt as its tool.
 *
 * Loaded on every control panel page, with no dependencies. It opens on:
 *
 * - the `ai-image-editor:edit` window event, with the asset ID in the event
 *   detail as `elementIds` (Craft's element actions) or `assetId`;
 * - the `ai-image-editor:generate` window event, with the target folder ID in
 *   the detail as `folderId`, for a generation session;
 * - a `#ai-image-editor:edit:{id}` or `#ai-image-editor:edit-screen:{id}` URL
 *   hash, for the Craft menus that can only link. The `edit-screen` form is used
 *   on the asset's own edit screen, which the editor follows after saving.
 *   Remove this once those menus can dispatch events:
 *   https://github.com/craftcms/cms/issues/20688
 *
 * `window.AiImageEditor.open(assetId, settings)` and
 * `window.AiImageEditor.generate(folderId, settings)` open it from code.
 */
(() => {
  if (window.AiImageEditor) {
    return;
  }

  const EDIT_EVENT = 'ai-image-editor:edit';
  const GENERATE_EVENT = 'ai-image-editor:generate';
  const SAVED_EVENT = 'ai-image-editor:saved';
  const HASH_PATTERN = /^#ai-image-editor:(edit|edit-screen):(\d+)$/;

  // Helpers
  // ---------------------------------------------------------------------------

  let messages = {};
  let configPromise = null;

  /**
   * Translates an interface string, with `{placeholder}` replacement. The
   * translations come from the editor config endpoint, untranslated strings
   * fall back to the English source.
   */
  function t(message, params = {}) {
    let translated = messages[message] || message;

    for (const [key, value] of Object.entries(params)) {
      translated = translated.split('{' + key + '}').join(String(value));
    }

    return translated;
  }

  function cpUrl(path, params = null) {
    const craft = window.Craft || {};
    const base = (craft.baseCpUrl || '/' + (craft.cpTrigger || 'admin')).replace(/\/+$/, '');
    const url = new URL(base + '/' + path, window.location.origin);

    for (const [key, value] of Object.entries(params || {})) {
      url.searchParams.set(key, value);
    }

    return url.toString();
  }

  /**
   * Sends a JSON request to one of the plugin's control panel endpoints.
   * Resolves with `{data}`, and rejects with `{response: {status, data}}` for
   * error responses, so callers can read a server message from either.
   */
  async function request(method, path, data = null) {
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    const options = {method, headers, credentials: 'same-origin'};
    let url = cpUrl(path);

    if (method === 'GET') {
      url = cpUrl(path, data);
    } else {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-TOKEN'] = (window.Craft && window.Craft.csrfTokenValue) || '';
      options.body = JSON.stringify(data || {});
    }

    const response = await fetch(url, options);
    let body = null;

    try {
      body = await response.json();
    } catch (e) {
      body = null;
    }

    if (!response.ok) {
      throw {response: {status: response.status, data: body}};
    }

    return {data: body || {}};
  }

  function loadConfig() {
    if (!configPromise) {
      configPromise = request('GET', 'ai-image-editor/editor-config')
        .then((response) => {
          messages = response.data.messages || {};
          return response.data;
        })
        .catch((error) => {
          // Retry on the next open rather than caching the failure.
          configPromise = null;
          throw error;
        });
    }

    return configPromise;
  }

  /**
   * Shows a control panel notification, through the queue Craft's message
   * center reads (it also carries a message across a page reload).
   */
  function notify(type, message) {
    window.CraftMessageQueue = window.CraftMessageQueue || [];
    window.CraftMessageQueue.push({type, message});
  }

  /**
   * Creates an element. `attrs` are set as properties when the element has
   * one by that name (`className`, `disabled`, `textContent`, …), and as
   * attributes otherwise.
   */
  function h(tag, attrs = {}, children = []) {
    const el = document.createElement(tag);

    for (const [key, value] of Object.entries(attrs)) {
      if (value === null || value === undefined || value === false) {
        continue;
      }

      if (key in el && typeof el[key] !== 'function' && !key.startsWith('aria') && !key.startsWith('data')) {
        el[key] = value;
      } else {
        el.setAttribute(key, value === true ? '' : value);
      }
    }

    for (const child of [].concat(children)) {
      if (child !== null && child !== undefined) {
        el.append(child);
      }
    }

    return el;
  }

  function toggle(el, show) {
    el.classList.toggle('ai-hidden', !show);
  }

  // The editor
  // ---------------------------------------------------------------------------

  class Editor {
    static defaults = {
      isEditScreen: false,
      folderId: null,
      onSave: () => {},
    };

    constructor(assetId, settings = {}) {
      this.assetId = assetId;
      this.settings = Object.assign({}, Editor.defaults, settings);
      this.session = null;
      this.busy = false;
      this.blocked = false;
      this.saved = false;
      this.mode = 'edit';
      this.compareData = null;
      this.compareSelection = 'after';
      this.selectedTurnId = null;
      this.lastTurnArgs = null;
      this.compareBar = null;

      this.build();
      document.body.append(this.dialog);
      this.dialog.showModal();
      this.setBusy(true, t('Working…'));

      loadConfig()
        .then(() => {
          this.translateStaticLabels();
          this.createSession();
        })
        .catch((error) => {
          this.setBusy(false);
          this.showBlockedState(this.errorMessage(error));
        });
    }

    // Session lifecycle
    // -------------------------------------------------------------------------

    createSession() {
      request('POST', 'ai-image-editor/sessions/create', this.assetId
        ? {assetId: this.assetId}
        : {folderId: this.settings.folderId || 0})
        .then((response) => {
          this.setBusy(false);

          if (!response.data.success) {
            this.showBlockedState(response.data.message);
            return;
          }

          this.session = response.data.session;
          this.renderSession();
        })
        .catch((error) => {
          this.setBusy(false);
          this.showBlockedState(this.errorMessage(error));
        });
    }

    renderSession() {
      const providerNames = this.session.providerNames || [this.session.driverName];
      const provider = providerNames.length > 1
        ? providerNames.slice(0, -1).join(', ') + ' ' + t('and') + ' ' + providerNames[providerNames.length - 1]
        : providerNames[0];

      this.note.textContent = t('Images and prompts are sent to {provider} for processing.', {provider});

      if (this.session.isGeneration) {
        const placeholder = t('Describe the image you want to create…');
        this.promptInput.placeholder = placeholder;
        this.promptInput.setAttribute('aria-label', placeholder);
      }

      this.populateModelSelect();
      this.populateRatioSelect();
      this.populateOutputFormatSelect();
      this.populateFinalResSelect(this.session.finalResolution);
      this.populatePresets();
      this.rebuildStrip();

      const turns = this.workingTurns();

      if (turns.length) {
        this.showImage(turns[turns.length - 1].imageUrl, turns[turns.length - 1].id);
      } else if (this.session.sourceImageUrl) {
        this.showImage(this.session.sourceImageUrl, 'source');
      } else {
        this.showEmptyState();
      }

      this.updateAcceptState();
      this.promptInput.focus();
    }

    // Edit turns
    // -------------------------------------------------------------------------

    runTurn(presetPrompt, presetPrecise) {
      if (this.busy || !this.session || this.mode !== 'edit') {
        return;
      }

      // A preset click passes its prompt directly; otherwise use the textarea.
      const fromPreset = typeof presetPrompt === 'string';
      const prompt = (fromPreset ? presetPrompt : this.promptInput.value || '').trim();

      if (!prompt) {
        return;
      }

      const precise = fromPreset && typeof presetPrecise === 'boolean'
        ? presetPrecise
        : this.preciseCheckbox.checked;

      // Remembered so a failed action can be retried with the same input.
      this.lastTurnArgs = fromPreset ? [presetPrompt, presetPrecise] : [];

      this.clearMessage();
      this.setBusy(true, t('Working…'));

      request('POST', 'ai-image-editor/sessions/turn', {
        sessionId: this.session.id,
        prompt,
        precise: precise ? 1 : 0,
        aspectRatio: this.ratioSelect.value || '',
        outputFormat: this.outputFormatSelect.value || '',
        model: this.modelSelect.value || '',
      })
        .then((response) => {
          this.setBusy(false);

          if (!response.data.success) {
            // Keep the prompt text so the user can retry or rephrase.
            this.showMessage(
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
            this.promptInput.value = '';
            this.autoGrow();
            this.updateSendState();
          }

          this.rebuildStrip();
          this.showImage(response.data.turn.imageUrl, response.data.turn.id);
          this.updateAcceptState();
          this.promptInput.focus();
        })
        .catch((error) => {
          this.setBusy(false);
          this.showMessage(this.errorMessage(error), 'error', true);
        });
    }

    // Finalize flow
    // -------------------------------------------------------------------------

    startFinalize() {
      const turns = this.workingTurns();

      if (this.busy || !turns.length) {
        return;
      }

      const finalResolution = this.finalResSelect.value || this.session.finalResolution;

      // No point regenerating if the working and final tiers are the same,
      // go straight to choosing how to save.
      if (this.session.workingResolution === finalResolution) {
        this.enterCompareMode(turns[turns.length - 1], null);
        return;
      }

      this.clearMessage();
      this.setBusy(true, t('Generating the final version…'));

      request('POST', 'ai-image-editor/sessions/finalize', {
        sessionId: this.session.id,
        model: this.modelSelect.value || '',
        finalResolution,
        outputFormat: this.outputFormatSelect.value || '',
      })
        .then((response) => {
          this.setBusy(false);

          if (!response.data.success) {
            // The working result can still be saved directly.
            this.showMessage(
              response.data.message || t('The high resolution version could not be generated.'),
              'error'
            );
            return;
          }

          this.enterCompareMode(response.data.before, response.data.after);
        })
        .catch((error) => {
          this.setBusy(false);
          this.showMessage(this.errorMessage(error), 'error');
        });
    }

    confirmSave(replaceOriginal) {
      if (this.busy) {
        return;
      }

      // Save whichever version is selected in the compare toggle. Without a
      // finalize candidate there is only the working result.
      const useWorkingResolution = !this.compareData || !this.compareData.after || this.compareSelection === 'before';

      this.clearMessage();
      this.setBusy(true, t('Working…'));

      request('POST', 'ai-image-editor/sessions/confirm', {
        sessionId: this.session.id,
        useWorkingResolution: useWorkingResolution ? 1 : 0,
        replaceOriginal: replaceOriginal ? 1 : 0,
      })
        .then((response) => {
          this.setBusy(false);

          if (!response.data.success) {
            this.showMessage(response.data.message, 'error');
            return;
          }

          this.saved = true;
          notify('success', t('Edited image saved as {filename}.', {filename: response.data.asset.filename}));
          this.settings.onSave(response.data);
          window.dispatchEvent(new CustomEvent(SAVED_EVENT, {detail: response.data}));

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

          this.close();
        })
        .catch((error) => {
          this.setBusy(false);
          this.showMessage(this.errorMessage(error), 'error');
        });
    }

    enterCompareMode(before, after) {
      this.mode = 'compare';
      this.compareData = {before, after};
      this.compareSelection = after ? 'after' : 'before';

      toggle(this.promptBar, false);
      toggle(this.stripWrap, false);
      toggle(this.acceptBtn, false);
      toggle(this.discardBtn, false);
      toggle(this.backBtn, true);
      toggle(this.saveNewBtn, true);

      // A generated image has no original to replace.
      toggle(this.saveReplaceBtn, !this.session.isGeneration);

      if (after) {
        this.buildCompareBar();
        this.showImage(after.imageUrl, 'compare-after');
        this.showMessage(t('Compare the draft and final versions, then choose which one to save.'), 'info');
      } else if (before) {
        this.showImage(before.imageUrl, 'compare-before');
      }

      this.updateSendState();
      this.updateRevertState();
      this.updatePresetsState();
    }

    exitCompareMode() {
      this.mode = 'edit';
      this.compareData = null;

      if (this.compareBar) {
        this.compareBar.remove();
        this.compareBar = null;
      }

      toggle(this.promptBar, true);
      toggle(this.acceptBtn, true);
      toggle(this.discardBtn, true);
      toggle(this.backBtn, false);
      toggle(this.saveNewBtn, false);
      toggle(this.saveReplaceBtn, false);
      this.clearMessage();
      this.rebuildStrip();

      const turns = this.workingTurns();

      if (turns.length) {
        this.showImage(turns[turns.length - 1].imageUrl, turns[turns.length - 1].id);
      }

      this.updateSendState();
    }

    buildCompareBar() {
      if (this.compareBar) {
        this.compareBar.remove();
      }

      const draftBtn = h('button', {
        type: 'button',
        textContent: t('Draft'),
        title: this.compareData.before.resolution,
        'aria-pressed': 'false',
      });
      const finalBtn = h('button', {
        type: 'button',
        className: 'active',
        textContent: t('Final'),
        title: this.compareData.after.resolution,
        'aria-pressed': 'true',
      });

      const select = (selection) => {
        this.compareSelection = selection;
        draftBtn.classList.toggle('active', selection === 'before');
        finalBtn.classList.toggle('active', selection === 'after');
        draftBtn.setAttribute('aria-pressed', String(selection === 'before'));
        finalBtn.setAttribute('aria-pressed', String(selection === 'after'));
        const turn = selection === 'before' ? this.compareData.before : this.compareData.after;
        this.showImage(turn.imageUrl, 'compare-' + selection);
      };

      draftBtn.addEventListener('click', () => select('before'));
      finalBtn.addEventListener('click', () => select('after'));

      this.compareBar = h('div', {className: 'ai-compare'}, [draftBtn, finalBtn]);
      this.composerWrap.prepend(this.compareBar);
    }

    // Closing
    // -------------------------------------------------------------------------

    maybeClose() {
      if (this.saved) {
        this.close();
        return;
      }

      const hasWork = this.busy || (this.session && this.workingTurns().length > 0);

      if (hasWork && !window.confirm(t('Discard this session and all edits?'))) {
        return;
      }

      if (this.session) {
        // Fire and forget; abandoned sessions get purged server side anyway.
        request('POST', 'ai-image-editor/sessions/discard', {sessionId: this.session.id}).catch(() => {});
      }

      this.close();
    }

    close() {
      if (this.dialog.open) {
        this.dialog.close();
      }

      this.dialog.remove();
    }

    // UI construction
    // -------------------------------------------------------------------------

    build() {
      this.dialog = h('dialog', {
        className: 'aiimageeditor',
        'aria-label': t('Edit with AI'),
      });

      // Closing is an explicit choice, so Escape runs through the discard confirmation.
      this.dialog.addEventListener('cancel', (ev) => {
        ev.preventDefault();
        this.maybeClose();
      });

      this.form = h('form', {className: 'ai-shell', method: 'dialog', acceptCharset: 'UTF-8'});
      this.form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        this.runTurn();
      });

      this.body = h('div', {className: 'ai-body'});
      this.footer = h('div', {className: 'ai-footer'});
      this.form.append(this.body, this.footer);
      this.dialog.append(this.form);

      this.buildImageArea();
      this.buildFooter();
    }

    buildImageArea() {
      this.imageContainer = h('div', {className: 'ai-image-container'});
      this.image = h('div', {className: 'ai-image'});
      this.img = h('img', {className: 'ai-current-image', alt: ''});
      this.overlayLabel = h('div', {className: 'ai-overlay-label'});
      this.imageOverlay = h('div', {className: 'ai-image-overlay ai-hidden'}, [
        h('div', {className: 'ai-overlay-chip'}, [h('div', {className: 'ai-spinner', 'aria-hidden': 'true'}), this.overlayLabel]),
      ]);
      this.image.append(this.img, this.imageOverlay);

      this.imageTools = h('div', {className: 'ai-tools'});
      this.imageContainer.append(this.image, this.imageTools);
      this.body.append(this.imageContainer);

      // Everything below the image lives in one constrained, centered column.
      this.composerWrap = h('div', {className: 'ai-composer-wrap'});
      this.imageTools.append(this.composerWrap);

      // Shown above the composer while a generation session has no draft yet.
      this.emptyHint = h('div', {
        className: 'ai-empty-hint ai-hidden',
        textContent: t('Describe the image you want to create, then apply to generate the first draft.'),
      });
      this.message = h('div', {className: 'ai-message ai-hidden', role: 'status'});

      // The collapsible turn strip.
      this.stripToggle = h('button', {
        type: 'button',
        className: 'ai-strip-toggle',
        'aria-expanded': 'true',
        'aria-label': t('Versions'),
      });
      this.strip = h('div', {className: 'ai-strip'});

      // Appears when an earlier draft is selected, to revert the session to it.
      this.revertBtn = h('button', {
        type: 'button',
        className: 'ai-revert ai-hidden',
        textContent: t('Revert to this version'),
      });
      this.stripWrap = h('div', {className: 'ai-strip-wrap ai-hidden'}, [this.stripToggle, this.strip, this.revertBtn]);

      this.stripToggle.addEventListener('click', () => {
        const collapsed = this.strip.classList.toggle('ai-hidden');
        this.stripToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        this.stripWrap.classList.toggle('collapsed', collapsed);
      });
      this.revertBtn.addEventListener('click', () => this.revert());

      // The composer: an integrated card with the prompt on top and the controls below.
      this.presets = h('div', {
        className: 'ai-presets ai-hidden',
        role: 'group',
        'aria-label': t('Quick actions'),
      });

      const promptPlaceholder = t('Describe the change you want to make…');
      this.promptInput = h('textarea', {
        className: 'ai-prompt-input',
        rows: 1,
        placeholder: promptPlaceholder,
        'aria-label': promptPlaceholder,
      });

      // The model used for edits and finalize, switchable per session.
      this.modelSelect = h('select', {'aria-label': t('Model')});
      // Output aspect ratio. "Match original" (empty value) lets the output follow the working image.
      this.ratioSelect = h('select', {'aria-label': t('Aspect ratio')});
      // Output image format, defaulting to the source image's format.
      this.outputFormatSelect = h('select', {'aria-label': t('Output format')});
      // The resolution used for the finalize regeneration when accepting.
      this.finalResSelect = h('select', {'aria-label': t('Final resolution')});
      this.finalPrefix = h('span', {className: 'ai-pill-prefix', textContent: t('Final')});

      this.modelSelect.addEventListener('change', () => this.populateFinalResSelect());

      // Biases the model toward minimal, faithful edits. On by default.
      this.preciseCheckbox = h('input', {type: 'checkbox', checked: true});
      this.preciseText = h('span', {textContent: t('Precise edits')});

      this.applyBtn = h('button', {
        type: 'submit',
        className: 'ai-send',
        disabled: true,
        title: t('Apply'),
        'aria-label': t('Apply'),
      });
      this.applyBtn.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 20V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

      const options = h('div', {className: 'ai-composer-options'}, [
        h('div', {className: 'ai-pill ai-pill--select ai-model'}, this.modelSelect),
        h('div', {className: 'ai-pill ai-pill--select ai-ratio'}, this.ratioSelect),
        h('div', {className: 'ai-pill ai-pill--select ai-format'}, this.outputFormatSelect),
        h('div', {className: 'ai-pill ai-pill--select ai-final'}, [this.finalPrefix, this.finalResSelect]),
        h('label', {className: 'ai-pill ai-precise'}, [this.preciseCheckbox, this.preciseText]),
      ]);

      this.promptBar = h('div', {className: 'ai-composer'}, [
        this.presets,
        this.promptInput,
        h('div', {className: 'ai-composer-row'}, [options, this.applyBtn]),
      ]);

      this.composerWrap.append(this.emptyHint, this.message, this.stripWrap, this.promptBar);

      this.promptInput.addEventListener('input', () => {
        this.autoGrow();
        this.updateSendState();
      });

      // Enter sends, Shift+Enter makes a newline, like other AI composers.
      this.promptInput.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) {
          ev.preventDefault();
          this.runTurn();
        }
      });
    }

    buildFooter() {
      this.note = h('div', {className: 'ai-note'});

      this.discardBtn = h('button', {type: 'button', className: 'ai-btn', textContent: t('Discard')});
      this.acceptBtn = h('button', {
        type: 'button',
        className: 'ai-btn ai-btn--primary',
        textContent: t('Accept & Save'),
        disabled: true,
      });
      this.backBtn = h('button', {type: 'button', className: 'ai-btn ai-hidden', textContent: t('Back to editing')});

      // "Save as a new asset" is the primary default. Replacing the original is
      // the destructive choice, so it's secondary and sits behind a confirmation.
      this.saveReplaceBtn = h('button', {type: 'button', className: 'ai-btn ai-hidden', textContent: t('Save')});
      this.saveNewBtn = h('button', {
        type: 'button',
        className: 'ai-btn ai-btn--primary ai-hidden',
        textContent: t('Save as a new asset'),
      });

      this.buttons = h('div', {className: 'ai-buttons'}, [
        this.discardBtn,
        this.acceptBtn,
        this.backBtn,
        this.saveReplaceBtn,
        this.saveNewBtn,
      ]);
      this.footer.append(this.note, this.buttons);

      this.discardBtn.addEventListener('click', () => this.maybeClose());
      this.acceptBtn.addEventListener('click', () => this.startFinalize());
      this.backBtn.addEventListener('click', () => this.exitCompareMode());
      this.saveNewBtn.addEventListener('click', () => this.confirmSave(false));
      this.saveReplaceBtn.addEventListener('click', () => {
        if (!window.confirm(t('Replace the original image? This will permanently overwrite the current file.'))) {
          return;
        }

        this.confirmSave(true);
      });
    }

    /**
     * Labels built before the translations loaded are re-set once they have.
     */
    translateStaticLabels() {
      this.dialog.setAttribute('aria-label', t('Edit with AI'));
      this.emptyHint.textContent = t('Describe the image you want to create, then apply to generate the first draft.');
      this.stripToggle.setAttribute('aria-label', t('Versions'));
      this.revertBtn.textContent = t('Revert to this version');
      this.presets.setAttribute('aria-label', t('Quick actions'));

      const promptPlaceholder = t('Describe the change you want to make…');
      this.promptInput.placeholder = promptPlaceholder;
      this.promptInput.setAttribute('aria-label', promptPlaceholder);

      this.modelSelect.setAttribute('aria-label', t('Model'));
      this.ratioSelect.setAttribute('aria-label', t('Aspect ratio'));
      this.outputFormatSelect.setAttribute('aria-label', t('Output format'));
      this.finalResSelect.setAttribute('aria-label', t('Final resolution'));
      this.finalPrefix.textContent = t('Final');
      this.preciseText.textContent = t('Precise edits');
      this.applyBtn.title = t('Apply');
      this.applyBtn.setAttribute('aria-label', t('Apply'));
      this.discardBtn.textContent = t('Discard');
      this.acceptBtn.textContent = t('Accept & Save');
      this.backBtn.textContent = t('Back to editing');
      this.saveReplaceBtn.textContent = t('Save');
      this.saveNewBtn.textContent = t('Save as a new asset');
      this.overlayLabel.textContent = this.busy ? t('Working…') : '';
    }

    fillSelect(select, options) {
      select.replaceChildren(...options.map(([value, label]) => h('option', {value, textContent: label})));
    }

    populateModelSelect() {
      const models = this.session.models || [];
      this.fillSelect(this.modelSelect, models.map((model) => [model.value, model.label]));
      this.modelSelect.value = this.session.model;
      toggle(this.modelSelect.parentElement, models.length >= 2);
    }

    populateFinalResSelect(preferred) {
      const current = preferred || this.finalResSelect.value || this.session.finalResolution;
      const models = this.session.models || [];
      const info = models.find((model) => model.value === this.modelSelect.value);
      const resolutions = info && info.resolutions && info.resolutions.length
        ? info.resolutions
        : [this.session.finalResolution];

      this.fillSelect(this.finalResSelect, resolutions.map((resolution) => [resolution, resolution]));
      this.finalResSelect.value = resolutions.includes(current) ? current : resolutions[resolutions.length - 1];
    }

    populateRatioSelect() {
      const ratios = this.session.aspectRatios || [];

      this.fillSelect(this.ratioSelect, [
        ['', this.session.isGeneration ? t('Auto') : t('Match original')],
        ...ratios.map((ratio) => [ratio, ratio]),
      ]);

      toggle(this.ratioSelect.parentElement, ratios.length > 0);
    }

    populateOutputFormatSelect() {
      const formats = this.session.outputFormats || [];

      this.fillSelect(this.outputFormatSelect, formats.map((format) => [
        format,
        format === 'webp' ? 'WebP' : format.toUpperCase(),
      ]));

      if (this.session.defaultOutputFormat) {
        this.outputFormatSelect.value = this.session.defaultOutputFormat;
      }

      // Only worth showing once there's an actual choice to make.
      toggle(this.outputFormatSelect.parentElement, formats.length >= 2);
    }

    populatePresets() {
      const presets = (this.session && this.session.presets) || [];

      this.presets.replaceChildren(...presets.map((preset) => {
        const chip = h('button', {type: 'button', className: 'ai-preset', textContent: preset.label});
        chip.addEventListener('click', () => {
          this.runTurn(preset.prompt, typeof preset.precise === 'boolean' ? preset.precise : undefined);
        });

        return chip;
      }));

      this.updatePresetsState();
    }

    updatePresetsState() {
      const presets = (this.session && this.session.presets) || [];
      // Quick actions act on an existing image, so hide them in the
      // generation empty state and outside edit mode.
      const hasImage = this.session && (!this.session.isGeneration || this.workingTurns().length > 0);

      toggle(this.presets, presets.length > 0 && hasImage && this.mode === 'edit');
    }

    rebuildStrip() {
      const turns = this.workingTurns();
      const hasSource = !!this.session.sourceImageUrl;
      const thumbs = [];

      if (hasSource) {
        thumbs.push(this.thumb(this.session.sourceImageUrl, 'source', t('Original')));
      }

      turns.forEach((turn, i) => {
        thumbs.push(this.thumb(turn.imageUrl, turn.id, turn.prompt || t('Turn {number}', {number: i + 1})));
      });

      this.strip.replaceChildren(...thumbs);

      // The strip only earns its place once there are two versions to flip between.
      toggle(this.stripWrap, this.mode === 'edit' && turns.length + (hasSource ? 1 : 0) >= 2);
    }

    thumb(imageUrl, key, title) {
      const thumb = h('button', {
        type: 'button',
        className: 'ai-thumb',
        title,
        'aria-label': title,
        'data-key': String(key),
      }, h('img', {src: imageUrl, alt: ''}));

      thumb.addEventListener('click', () => {
        if (this.mode === 'edit') {
          this.showImage(imageUrl, key);
        }
      });

      return thumb;
    }

    showImage(url, key) {
      if (!url) {
        return;
      }

      toggle(this.emptyHint, false);
      this.imageContainer.classList.remove('ai-centered');
      toggle(this.img, true);
      this.img.src = url;

      for (const thumb of this.strip.querySelectorAll('.ai-thumb')) {
        thumb.classList.toggle('sel', thumb.dataset.key === String(key));
      }

      this.selectedTurnId = key;
      this.updateRevertState();
      this.updatePresetsState();
    }

    updateRevertState() {
      const turns = this.workingTurns();
      const latestId = turns.length ? turns[turns.length - 1].id : null;
      const key = this.selectedTurnId;
      const isSource = key === 'source';
      const revertable = this.mode === 'edit' && key !== null && (isSource
        ? turns.length > 0
        : latestId !== null && String(key) !== String(latestId));

      toggle(this.revertBtn, revertable);
      this.revertBtn.textContent = isSource ? t('Revert to original') : t('Revert to this version');
    }

    revert() {
      if (this.busy || this.selectedTurnId === null) {
        return;
      }

      const message = this.selectedTurnId === 'source'
        ? t('Revert to the original image? All edits will be discarded.')
        : t('Revert to this version? Any drafts made after it will be discarded.');

      if (!window.confirm(message)) {
        return;
      }

      this.clearMessage();
      this.setBusy(true, t('Working…'));

      request('POST', 'ai-image-editor/sessions/revert', {
        sessionId: this.session.id,
        turnId: this.selectedTurnId,
      })
        .then((response) => {
          this.setBusy(false);
          this.session.turns = response.data.session.turns;
          this.selectedTurnId = null;
          this.rebuildStrip();

          const turns = this.workingTurns();

          if (turns.length) {
            this.showImage(turns[turns.length - 1].imageUrl, turns[turns.length - 1].id);
          } else if (this.session.sourceImageUrl) {
            this.showImage(this.session.sourceImageUrl, 'source');
          }

          this.updateAcceptState();
          this.updateRevertState();
        })
        .catch((error) => {
          this.setBusy(false);
          this.showMessage(this.errorMessage(error), 'error');
        });
    }

    /**
     * Before the first draft exists there is nothing to show, so the composer
     * takes center stage instead, with a hint above it.
     */
    showEmptyState() {
      toggle(this.img, false);
      this.imageContainer.classList.add('ai-centered');
      toggle(this.emptyHint, true);
    }

    showBlockedState(message) {
      this.blocked = true;
      this.promptInput.disabled = true;
      this.acceptBtn.disabled = true;
      this.image.classList.add('ai-blocked');
      this.updateSendState();
      this.showMessage(message || t('The image could not be loaded.'), 'error');
    }

    updateAcceptState() {
      this.acceptBtn.disabled = this.workingTurns().length === 0;
    }

    workingTurns() {
      return this.session ? this.session.turns.filter((turn) => !turn.isFinal) : [];
    }

    setBusy(busy, label) {
      this.busy = busy;
      this.dialog.classList.toggle('ai-busy', busy);
      toggle(this.imageOverlay, busy);
      this.overlayLabel.textContent = label || '';
      this.promptInput.disabled = busy || this.blocked;
      this.applyBtn.classList.toggle('loading', busy);

      for (const button of [this.acceptBtn, this.saveNewBtn, this.saveReplaceBtn, this.backBtn]) {
        button.classList.toggle('ai-btn--busy', busy);
      }

      this.updateSendState();
    }

    /**
     * Grows the prompt textarea with its content, capped by the CSS max-height.
     * The scrollbar only appears once the cap is reached.
     */
    autoGrow() {
      const input = this.promptInput;
      const maxHeight = 140;
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, maxHeight) + 'px';
      input.style.overflowY = input.scrollHeight > maxHeight ? 'auto' : 'hidden';
    }

    /**
     * The send button is only enabled with a non-empty prompt in an idle,
     * usable editor, mirroring other AI composers.
     */
    updateSendState() {
      const empty = !(this.promptInput.value || '').trim();
      this.applyBtn.disabled = empty || this.busy || this.blocked || this.mode !== 'edit';
    }

    showMessage(message, type = 'info', withRetry = false, retryAfter = null) {
      this.message.className = 'ai-message ai-message--' + type;
      this.message.replaceChildren(document.createTextNode(message || ''));

      if (!withRetry) {
        return;
      }

      const retry = h('button', {type: 'button', className: 'ai-retry', textContent: t('Retry')});

      // Honor the provider's Retry-After window before allowing a retry.
      if (retryAfter && retryAfter > 0) {
        retry.disabled = true;
        setTimeout(() => {
          retry.disabled = false;
        }, retryAfter * 1000);
      }

      retry.addEventListener('click', () => this.runTurn(...(this.lastTurnArgs || [])));
      this.message.append(retry);
    }

    clearMessage() {
      this.message.className = 'ai-message ai-hidden';
      this.message.replaceChildren();
    }

    errorMessage(error) {
      if (error && error.response && error.response.data && error.response.data.message) {
        return error.response.data.message;
      }

      return t('The edit could not be performed. Please try again.');
    }
  }

  // Entry points
  // ---------------------------------------------------------------------------

  let current = null;

  function open(assetId, settings = {}) {
    // One editor at a time.
    if (current && current.dialog.isConnected) {
      return current;
    }

    current = new Editor(assetId, settings);

    return current;
  }

  function generate(folderId, settings = {}) {
    return open(null, Object.assign({}, settings, {folderId}));
  }

  /**
   * Refreshes the current control panel page's data (e.g. an element index)
   * after a save, without a full reload.
   */
  function refreshPage() {
    const router = window.Cp && window.Cp.$router;

    if (router && typeof router.reload === 'function') {
      router.reload();
    }
  }

  window.addEventListener(EDIT_EVENT, (ev) => {
    const detail = ev.detail || {};
    const assetId = detail.assetId || (Array.isArray(detail.elementIds) ? detail.elementIds[0] : null);

    if (assetId) {
      open(Number(assetId), {onSave: refreshPage});
    }
  });

  window.addEventListener(GENERATE_EVENT, (ev) => {
    const detail = ev.detail || {};

    if (detail.folderId) {
      generate(Number(detail.folderId), {onSave: refreshPage});
    }
  });

  function openFromHash() {
    const match = window.location.hash.match(HASH_PATTERN);

    if (!match) {
      return;
    }

    // Drop the hash, so a reload or a later save doesn't reopen the editor.
    const url = window.location.href.replace(/#.*$/, '');
    window.history.replaceState(window.history.state, '', url);

    const isEditScreen = match[1] === 'edit-screen';
    open(Number(match[2]), {isEditScreen, onSave: isEditScreen ? () => {} : refreshPage});
  }

  window.addEventListener('hashchange', openFromHash);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', openFromHash);
  } else {
    openFromHash();
  }

  window.AiImageEditor = {open, generate, Editor};
})();
