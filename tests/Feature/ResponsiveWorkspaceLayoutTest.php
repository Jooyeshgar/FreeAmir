<?php

namespace Tests\Feature;

use Tests\TestCase;

class ResponsiveWorkspaceLayoutTest extends TestCase
{
    public function test_workspace_layout_contains_the_responsive_content_boundary(): void
    {
        $layout = file_get_contents(resource_path('views/components/app-layout.blade.php'));

        $this->assertStringContainsString('w-full min-w-0', $layout);
        $this->assertStringContainsString('workspace-content min-w-0', $layout);
    }

    public function test_responsive_workspace_styles_cover_tables_and_filter_forms(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.workspace-content .table', $css);
        $this->assertStringContainsString('overflow-x: auto', $css);
        $this->assertStringContainsString('form.flex, form.grid', $css);
        $this->assertStringContainsString('@media (max-width: 1023px)', $css);
        $this->assertStringContainsString('@media (max-width: 639px)', $css);
    }
}
