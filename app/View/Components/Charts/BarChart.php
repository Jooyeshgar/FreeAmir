<?php

namespace App\View\Components\Charts;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class BarChart extends Component
{
    public array $datas;

    public array $datasets;

    public ?string $chartId;

    public string $heightClass;

    public string $label;

    public string $backgroundColor;

    public string $borderColor;

    public string $negativeColor;

    public ?bool $showLegend;

    public string $datalabelColor;

    public array $labels = [];

    public array $normalizedDatasets = [];

    public bool $resolvedShowLegend = false;

    public string $resolvedChartId = '';

    public function __construct(
        array $datas = [],
        array $datasets = [],
        ?string $chartId = null,
        string $heightClass = 'h-64',
        string $label = 'موجودی انبار',
        string $backgroundColor = '#4bb946c4',
        string $borderColor = '#4bb946',
        string $negativeColor = 'red',
        ?bool $showLegend = null,
        string $datalabelColor = '#166534'
    ) {
        $this->datas = $datas;
        $this->datasets = $datasets;
        $this->chartId = $chartId;
        $this->heightClass = $heightClass;
        $this->label = $label;
        $this->backgroundColor = $backgroundColor;
        $this->borderColor = $borderColor;
        $this->negativeColor = $negativeColor;
        $this->showLegend = $showLegend;
        $this->datalabelColor = $datalabelColor;

        $this->prepareDatasets();
    }

    public function render(): View|Closure|string
    {
        $this->resolvedChartId = $this->chartId ?? ($this->attributes->get('id') ?? 'barChart_'.uniqid());

        return view('components.charts.bar-chart', [
            'resolvedChartId' => $this->resolvedChartId,
            'resolvedShowLegend' => $this->resolvedShowLegend,
            'labels' => $this->labels,
            'normalizedDatasets' => $this->normalizedDatasets,
            'heightClass' => $this->heightClass,
            'backgroundColor' => $this->backgroundColor,
            'negativeColor' => $this->negativeColor,
            'datalabelColor' => $this->datalabelColor,
        ]);
    }

    private function prepareDatasets(): void
    {
        $baseDatasets = $this->datasets;

        if (empty($baseDatasets)) {
            $baseDatasets = [
                [
                    'label' => $this->label,
                    'data' => $this->datas,
                    'backgroundColor' => $this->backgroundColor,
                    'borderColor' => $this->borderColor,
                    'negativeColor' => $this->negativeColor,
                ],
            ];
        }

        $labels = [];
        foreach ($baseDatasets as $set) {
            if (! isset($set['data']) || ! is_array($set['data'])) {
                continue;
            }
            $labels = array_merge($labels, array_keys($set['data']));
        }

        $this->labels = array_values(array_unique($labels));

        foreach ($baseDatasets as $index => $set) {
            $this->normalizedDatasets[] = [
                'label' => $set['label'] ?? null,
                'data' => array_map(fn ($label) => $set['data'][$label] ?? 0, $this->labels),
                'backgroundColor' => $set['backgroundColor'] ?? $this->backgroundColor,
                'borderColor' => $set['borderColor'] ?? $this->borderColor,
                'negativeColor' => $set['negativeColor'] ?? $this->negativeColor,
                'negativeBorderColor' => $set['negativeBorderColor'] ?? ($set['negativeColor'] ?? $this->negativeColor),
                'borderWidth' => $set['borderWidth'] ?? 2,
                'borderRadius' => $set['borderRadius'] ?? 0,
                'borderSkipped' => $set['borderSkipped'] ?? false,
            ];
        }

        $labeledDatasetCount = count(array_filter($this->normalizedDatasets, fn ($d) => ! empty($d['label'])));
        $this->resolvedShowLegend = is_null($this->showLegend) ? $labeledDatasetCount > 1 : (bool) $this->showLegend;
    }
}
