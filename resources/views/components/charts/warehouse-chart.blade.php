@props(['datas', 'chartId' => null])

<x-charts.bar-chart :datas="$datas" :chart-id="$chartId ?? ($attributes->get('id') ?? 'warehouseChart')" heightClass="h-96" />
