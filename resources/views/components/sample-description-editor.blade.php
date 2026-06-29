@props([
    'content' => '',
    'model' => '',
])

{{-- Wraps the same `editor` Alpine component the report editor uses, so the
     admin sample description gets an identical rich-text experience. It runs
     without references/citations (an empty references list is harmless) and
     syncs its HTML into the given Livewire model on input. --}}
<div
    x-data="editor({ initialContent: @js($content), references: [] })"
    x-init="$nextTick(() => mountEditor())"
    class="mt-1 overflow-hidden rounded-md ring-1 ring-gray-300 focus-within:ring-2 focus-within:ring-indigo-500"
>
    <div class="se-toolbar">
        <button type="button" x-on:mousedown.prevent x-on:click="setBlock('p')" class="toolbar-btn">Normal</button>
        <button type="button" x-on:mousedown.prevent x-on:click="setBlock('h2')" class="toolbar-btn font-semibold">Heading 2</button>
        <button type="button" x-on:mousedown.prevent x-on:click="setBlock('h3')" class="toolbar-btn font-semibold">Heading 3</button>
        <span class="toolbar-divider"></span>
        <button type="button" x-on:mousedown.prevent x-on:click="run('bold')" class="toolbar-btn font-bold">B</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('italic')" class="toolbar-btn italic">I</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('underline')" class="toolbar-btn underline">U</button>
        <span class="toolbar-divider"></span>
        <button type="button" x-on:mousedown.prevent x-on:click="run('justifyLeft')" class="toolbar-btn">Left</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('justifyCenter')" class="toolbar-btn">Center</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('justifyRight')" class="toolbar-btn">Right</button>
        <span class="toolbar-divider"></span>
        <button type="button" x-on:mousedown.prevent x-on:click="run('insertUnorderedList')" class="toolbar-btn">&bull; List</button>
        <button type="button" x-on:mousedown.prevent x-on:click="run('insertOrderedList')" class="toolbar-btn">1. List</button>
    </div>

    <div
        x-ref="content"
        contenteditable="true"
        x-on:input="$wire.set(@js($model), getHTML(), false)"
        x-on:blur="$wire.set(@js($model), getHTML(), false)"
        class="tiptap-content min-h-45 max-w-none px-4 py-3 text-sm leading-relaxed focus:outline-none"
    ></div>
</div>
