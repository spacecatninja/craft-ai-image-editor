<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Support;

use CraftCms\Cms\Cms;
use GuzzleHttp\Client;
use GuzzleHttp\Utils;
use Illuminate\Support\Facades\Config;

/**
 * Creates Guzzle clients with Craft's HTTP defaults: its user agent, the `craft.guzzle` config and the `httpProxy`
 * general config setting.
 */
class HttpClient
{
    public static function create(array $config = []): Client
    {
        $defaults = [
            'headers' => [
                'User-Agent' => 'Craft/' . Cms::VERSION . ' ' . Utils::defaultUserAgent(),
            ],
        ];

        $config = array_replace_recursive($defaults, Config::get('craft.guzzle', []), $config);

        if (Cms::config()->httpProxy) {
            $config['proxy'] = Cms::config()->httpProxy;
        }

        return new Client($config);
    }
}
