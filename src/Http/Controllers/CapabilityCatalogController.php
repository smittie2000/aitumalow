<?php

namespace Aitumalow\Http\Controllers;

use Aitumalow\Plugin\PluginManager;
use Aitumalow\Registry\NodeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CapabilityCatalogController extends Controller
{
    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly PluginManager $pluginManager,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->registry->all()]);
    }

    public function editorScripts(): JsonResponse
    {
        $scripts = [];

        foreach ($this->pluginManager->plugins()->all() as $plugin) {
            foreach ($plugin->editorScripts() as $url) {
                $scripts[] = $url;
            }
        }

        return response()->json(['data' => ['scripts' => $scripts]]);
    }
}
