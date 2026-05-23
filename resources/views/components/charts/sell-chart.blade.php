@props(['datas', 'chartId' => null])

<x-charts.bar-chart chart-id="sellChart" :datasets="[['data' => $datas]]" height-class="h-72" />
