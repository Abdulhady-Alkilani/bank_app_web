@props(['options'])

{{-- ApexCharts wrapper: options is a plain array rendered once; the key forces a re-mount when the data changes. --}}
<div wire:ignore wire:key="chart-{{ md5(json_encode($options)) }}" dir="ltr"
     x-data x-init="if (window.ApexCharts) { new window.ApexCharts($refs.canvas, @js($options)).render(); }">
    <div x-ref="canvas"></div>
</div>
