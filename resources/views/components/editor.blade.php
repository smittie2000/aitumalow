@props([
    'workflowId',
    'apiBaseUrl',
    'height' => 'calc(100vh - 10rem)',
])

<div
    wire:ignore
    x-data="{ editor: null }"
    x-load-css="[@js(\Filament\Support\Facades\FilamentAsset::getStyleHref('editor', package: 'aitumalow/aitumalow'))]"
    x-load-js="[@js(\Filament\Support\Facades\FilamentAsset::getScriptSrc('editor', package: 'aitumalow/aitumalow'))]"
    x-init="$nextTick(() => editor = window.AitumalowEditor.mountAitumalowEditor($refs.target, {
        workflowId: @js((int) $workflowId),
        baseUrl: @js($apiBaseUrl),
    }))"
    x-on:livewire:navigating.window="editor?.unmount()"
    {{ $attributes->class(['w-full overflow-hidden rounded-xl border border-gray-200 dark:border-white/10']) }}
    style="height: {{ $height }}"
>
    <div x-ref="target" class="h-full w-full"></div>
</div>
