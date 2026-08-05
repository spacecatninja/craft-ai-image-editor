<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\elementactions;

use Craft;
use craft\base\ElementAction;

use spacecatninja\aiimageeditor\assetbundles\EditorAsset;

/**
 * Element action that opens the AI image editor for the selected asset, shown
 * alongside Craft's native Edit Image action on asset indexes.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditWithAi extends ElementAction
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The trigger label.
     */
    public string $label;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        if (!isset($this->label)) {
            $this->label = Craft::t('ai-image-editor', 'Edit with AI');
        }
    }

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritdoc
     */
    public function getTriggerHtml(): ?string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(EditorAsset::class);

        $view->registerJsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        bulk: false,
        validateSelection: (selectedItems, elementIndex) => Garnish.hasAttr(selectedItems.find('.element'), 'data-editable-image'),
        activate: (selectedItems, elementIndex) => {
            const \$element = selectedItems.find('.element:first');
            Craft.AiImageEditor.open(\$element.data('id'), {
                onSave: () => {
                    elementIndex.updateElements();
                },
            });
        },
    });
})();
JS, [static::class]);

        return null;
    }
}
