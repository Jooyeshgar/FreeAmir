@props(['datas', 'chartId' => null])

<x-charts.bar-chart chart-id="sellChart" :datasets="[['data' => $datas]]" />
