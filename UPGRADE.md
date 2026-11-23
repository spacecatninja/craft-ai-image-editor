# Upgrading to AI Image Editor 2 (Craft 6)

This guide lists what changed in AI Image Editor 2 and what you need to update in your project and custom code. It applies when upgrading from AI Image Editor 1 (Craft 5).

Projects that only configure the plugin through its config file need to move and convert that file (see below), and nothing else. The other changes affect plugins and modules that extend AI Image Editor: custom edit drivers, event listeners, and code that calls its services or opens the editor.

## Requirements

- Craft CMS 6.0.0-beta.2 or later
- PHP 8.5 or later
- The plugin doesn't need the Yii 2 adapter (`craftcms/yii2-adapter`).

## Upgrade steps

1. Upgrade your project to Craft 6 first.
2. Update the plugin: `composer require spacecatninja/ai-image-editor:^2.0.0`.
3. Move your config file to `config/craft/ai-image-editor.php`, and convert it (see below).
4. Review the breaking changes below and update your code.

The plugin's database tables keep their names and columns, so sessions are kept. Working images of sessions that were still in progress when you upgraded aren't carried over, so those sessions can't be continued: the editor asks to start a new one, and the purge removes them.

## Breaking changes

### The config file

Craft 6 loads config files as plain Laravel config, from `config/craft/`. Move `config/ai-image-editor.php` to `config/craft/ai-image-editor.php`, return a flat array instead of the multi-environment `'*'` format, and use `env()` instead of `craft\helpers\App::env()`. The settings themselves are unchanged.

Before:

```php
use craft\helpers\App;

return [
    '*' => [
        'driver' => 'gemini',
        'driverConfig' => [
            'gemini' => ['apiKey' => App::env('GEMINI_API_KEY')],
        ],
    ],
];
```

After:

```php
return [
    'driver' => 'gemini',
    'driverConfig' => [
        'gemini' => ['apiKey' => env('GEMINI_API_KEY')],
    ],
];
```

Config files are loaded before Laravel knows the environment, so use `env()` for per-environment values, not `app()->environment()`. For example: `'driver' => env('APP_ENV') === 'production' ? 'gemini' : 'openai'`.

### Namespace and classes

The plugin moved from `spacecatninja\aiimageeditor` to the `SpaceCatNinja\AiImageEditor` namespace, with Laravel-style folders.

| Before | After |
| --- | --- |
| `spacecatninja\aiimageeditor\AiImageEditor` | `SpaceCatNinja\AiImageEditor\AiImageEditor` |
| `spacecatninja\aiimageeditor\models\Settings` | `SpaceCatNinja\AiImageEditor\Settings` |
| `spacecatninja\aiimageeditor\models\EditRequest`, `EditResult`, `EditSession`, `EditTurn` | `SpaceCatNinja\AiImageEditor\Data\…` |
| `spacecatninja\aiimageeditor\records\EditSession`, `EditTurn` (Active Record) | `SpaceCatNinja\AiImageEditor\Models\…` (Eloquent models) |
| `spacecatninja\aiimageeditor\drivers\…` | `SpaceCatNinja\AiImageEditor\Drivers\…` |
| `spacecatninja\aiimageeditor\clients\…` | `SpaceCatNinja\AiImageEditor\Clients\…` |
| `spacecatninja\aiimageeditor\services\…` | `SpaceCatNinja\AiImageEditor\Services\…` |
| `spacecatninja\aiimageeditor\exceptions\…` | `SpaceCatNinja\AiImageEditor\Exceptions\…` |
| `spacecatninja\aiimageeditor\enums\SessionStatus` | `SpaceCatNinja\AiImageEditor\Enums\SessionStatus` |
| `spacecatninja\aiimageeditor\helpers\AnalysisHelper` | `SpaceCatNinja\AiImageEditor\Support\AnalysisHelper` |
| `spacecatninja\aiimageeditor\jobs\PurgeStaleSessions` | `SpaceCatNinja\AiImageEditor\Jobs\PurgeStaleSessions` |
| `spacecatninja\aiimageeditor\db\Table` | `SpaceCatNinja\AiImageEditor\Database\Table` |
| `spacecatninja\aiimageeditor\controllers\SessionsController` | `SpaceCatNinja\AiImageEditor\Http\Controllers\SessionsController` |
| `spacecatninja\aiimageeditor\console\controllers\…` | Artisan commands, see below |
| `spacecatninja\aiimageeditor\elementactions\EditWithAi` | Removed, see "The editor's entry points" |
| `spacecatninja\aiimageeditor\assetbundles\EditorAsset` | Removed, the editor script is loaded on every control panel page |
| `spacecatninja\aiimageeditor\events\EditEvent`, `SaveEvent`, `RegisterEditDriversEvent` | Removed, see below |

`Table::SESSIONS` and `Table::TURNS` are now the table names without the prefix (`aiimageeditor_sessions`, `aiimageeditor_turns`), since Laravel's connection adds it.

### Registering edit drivers

`AiImageEditor::EVENT_REGISTER_EDIT_DRIVERS` and `RegisterEditDriversEvent` were removed, along with `DriversService::registerDriver()` and `DriversService::getRegisteredDrivers()`. Drivers are registered in the `SpaceCatNinja\AiImageEditor\Drivers\EditDrivers` registry instead. It's a singleton, so it doesn't matter whether your plugin boots before or after AI Image Editor.

Before:

```php
use spacecatninja\aiimageeditor\AiImageEditor;
use spacecatninja\aiimageeditor\events\RegisterEditDriversEvent;
use yii\base\Event;

Event::on(
    AiImageEditor::class,
    AiImageEditor::EVENT_REGISTER_EDIT_DRIVERS,
    static function(RegisterEditDriversEvent $event) {
        $event->drivers['my-driver'] = MyEditDriver::class;
    }
);
```

After, in your plugin's or service provider's `boot()`:

```php
use SpaceCatNinja\AiImageEditor\Drivers\EditDrivers;

public function boot(EditDrivers $drivers): void
{
    $drivers->register('my-driver', MyEditDriver::class);
}
```

The registry has `register($handle, $class)`, `remove($handle)`, `has($handle)`, `get($handle)` and `all()`. `register()` throws an `InvalidArgumentException` if the class doesn't implement `EditDriverInterface`.

Drivers are now created through the container, so their constructors can take dependencies. `BaseEditDriver` no longer extends `craft\base\Component`, and reads the settings with `$this->settings()`. Its `writeResultImage()` throws a `RuntimeException` (or `ErrorException`) instead of `yii\base\ErrorException` when the file can't be written. `DriversService` throws an `EditDriverException` instead of `yii\base\InvalidConfigException` for an unknown driver handle.

### Lifecycle events

The Yii events on the services were replaced by Laravel events in the `SpaceCatNinja\AiImageEditor\Events` namespace:

| Before | After |
| --- | --- |
| `SessionsService::EVENT_BEFORE_EDIT` (`EditEvent`) | `EditingImage` |
| `SessionsService::EVENT_AFTER_EDIT` (`EditEvent`) | `ImageEdited` |
| `FinalizeService::EVENT_BEFORE_SAVE` (`SaveEvent`) | `SavingImage` |
| `FinalizeService::EVENT_AFTER_SAVE` (`SaveEvent`) | `ImageSaved` |

The properties are the same: `EditingImage` has `session`, `request` and `isValid`, `ImageEdited` has `session`, `request` and `result`, and `SavingImage` and `ImageSaved` have `session`, `asset` and `replace` (`SavingImage` also has `isValid`). Set `isValid = false` to cancel, as before. You can still replace `$event->request` in an `EditingImage` listener. The other properties can't be reassigned, but the objects they hold can be changed, like setting field values on `$event->asset`.

Before:

```php
use spacecatninja\aiimageeditor\events\EditEvent;
use spacecatninja\aiimageeditor\services\SessionsService;
use yii\base\Event;

Event::on(SessionsService::class, SessionsService::EVENT_BEFORE_EDIT, static function(EditEvent $event) {
    $event->request->prompt .= ' Keep the brand colors intact.';
});
```

After:

```php
use Illuminate\Support\Facades\Event;
use SpaceCatNinja\AiImageEditor\Events\EditingImage;

Event::listen(function(EditingImage $event) {
    $event->request->prompt .= ' Keep the brand colors intact.';
});
```

### Services

`AiImageEditor::$plugin`, and the `getDrivers()`, `getSessions()` and `getFinalize()` service getters, were removed. The services are container singletons, so inject them, or resolve them with `app()`:

| Before | After |
| --- | --- |
| `AiImageEditor::$plugin->getDrivers()` | `app(SpaceCatNinja\AiImageEditor\Services\DriversService::class)` |
| `AiImageEditor::$plugin->getSessions()` | `app(SpaceCatNinja\AiImageEditor\Services\SessionsService::class)` |
| `AiImageEditor::$plugin->getFinalize()` | `app(SpaceCatNinja\AiImageEditor\Services\FinalizeService::class)` |
| `AiImageEditor::$plugin->getSettings()` | `app(SpaceCatNinja\AiImageEditor\Settings::class)` |

`SessionsService::createSession()` and `createGenerationSession()` take a `CraftCms\Cms\User\Elements\User`.

Session working images moved from Craft's `Temp` filesystem to Laravel's temporary asset upload disk (`tempAssetUploadDisk` in the general config). On Craft Cloud and load-balanced setups, make sure that disk is shared between instances.

### Permissions

The permission handles are unchanged (`ai-image-editor:edit` and `ai-image-editor:generate`). The constants moved from the controller to the plugin class:

| Before | After |
| --- | --- |
| `SessionsController::PERMISSION_EDIT` | `AiImageEditor::PERMISSION_EDIT` |
| `SessionsController::PERMISSION_GENERATE` | `AiImageEditor::PERMISSION_GENERATE` |

### Console commands

The console controllers are Artisan commands now:

| Before | After |
| --- | --- |
| `php craft ai-image-editor/test-connection` | `php artisan ai-image-editor:test-connection` |
| `php craft ai-image-editor/sessions/purge --hours=24` | `php artisan ai-image-editor:sessions:purge --hours=24` |

The Craft 5 names still work through Craft 6's `craft` script, like `php craft ai-image-editor/test-connection`.

### The editor's endpoints

The editor's JSON endpoints moved from action URLs to control panel routes. They're only used by the editor itself:

| Before | After |
| --- | --- |
| `actions/ai-image-editor/sessions/<action>` | `<cpTrigger>/ai-image-editor/sessions/<action>` |

They still require a logged-in user with control panel access, and the `turn-image` URLs in session payloads point at the new route.

### The editor's entry points

Craft 6's control panel has no jQuery, Garnish or `Craft.sendActionRequest`, so the editor was rewritten as a dependency-free script, loaded on every control panel page.

| Before | After |
| --- | --- |
| `Craft.AiImageEditor.open(assetId, settings)` | `window.AiImageEditor.open(assetId, settings)`, or the `ai-image-editor:edit` window event with `{assetId}` in the detail |
| `new Craft.AiImageEditor.Editor(null, {folderId})` | `window.AiImageEditor.generate(folderId)`, or the `ai-image-editor:generate` window event with `{folderId}` in the detail |
| `Craft.AiImageEditor.Editor` (a `Garnish.Modal`) | `window.AiImageEditor.Editor` (a plain class, rendering a native `<dialog>`) |
| `Craft.AiImageEditor.attachGenerateButton()`, `attachEditScreenButton()` | Removed. The plugin adds its buttons through Craft 6's events instead. |
| `settings.onSave(data)` | Unchanged, and the `ai-image-editor:saved` window event is dispatched as well |

The **Edit with AI** element action is a Craft 6 client action (`ai-image-editor:edit`). On the asset's edit screen, **Edit with AI** is in the action menu and the editor's footer, instead of next to the preview's **Edit Image** button.

**Generate** is in the menu beside **Upload files** on asset indexes, instead of a button in the toolbar. In asset selection modals, it's still a button beside **Upload files**, but the modal doesn't show or preselect the generated image until it's opened again, since Craft 6 doesn't yet let plugins refresh a modal's list.

### Queue job

`PurgeStaleSessions` is a Laravel job (`CraftCms\Cms\Queue\Job`). Pass the age threshold to the constructor: `new PurgeStaleSessions(hours: 24)`.

## Removed

- The `ecs.php` and `phpstan.neon` configs, and the `craftcms/ecs` and `craftcms/phpstan` dev dependencies, which only support Craft 5.
