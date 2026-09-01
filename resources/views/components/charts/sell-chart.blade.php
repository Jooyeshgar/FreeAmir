@props(['datas', 'chartId' => null])

<x-charts.bar-chart chart-id="sellChart" :datasets="[['data' => $datas]]" height-class="h-56 sm:h-64 lg:h-72" />
