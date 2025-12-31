@props(['datas', 'chartId' => null])

<x-charts.bar-chart :datas="$datas" :chart-id="$chartId ?? ($attributes->get('id') ?? 'sellChart')" heightClass="h-64" />
