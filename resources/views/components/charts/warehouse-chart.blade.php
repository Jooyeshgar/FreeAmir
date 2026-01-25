@props(['datas', 'chartId' => null])

<x-charts.bar-chart chart-id="warehouseChart" heightClass="h-96" :datasets="[['data' => $datas]]" />
