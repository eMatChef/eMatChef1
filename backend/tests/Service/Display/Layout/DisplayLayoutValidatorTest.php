<?php

declare(strict_types=1);

namespace App\Tests\Service\Display\Layout;

use App\Service\Display\Layout\DisplayLayoutTemplateCatalog;
use App\Service\Display\Layout\DisplayLayoutValidator;
use App\Service\Display\Layout\DisplayWidgetRegistry;
use PHPUnit\Framework\TestCase;

final class DisplayLayoutValidatorTest extends TestCase
{
    private DisplayLayoutValidator $validator;
    private DisplayLayoutTemplateCatalog $templates;

    protected function setUp(): void
    {
        $this->validator = new DisplayLayoutValidator(new DisplayWidgetRegistry());
        $this->templates = new DisplayLayoutTemplateCatalog();
    }

    /** @return array<string, mixed> */
    private function doc(array $items = [], ?array $root = null): array
    {
        return [
            'schema_version' => 1,
            'default_variant_id' => 'v1',
            'variants' => [['id' => 'v1', 'name' => 'Standard', 'aspect_ratio' => '16:9', 'root' => $root ?? ['type' => 'page', 'page_id' => 'p1']]],
            'pages' => [['id' => 'p1', 'name' => 'Seite', 'rows' => 12, 'items' => $items]],
        ];
    }

    private function widgetItem(string $id, string $widget, array $config = [], int $x = 0, int $y = 0, int $w = 6, int $h = 4): array
    {
        return ['id' => $id, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'content' => ['type' => 'widget', 'widget' => $widget, 'widget_version' => 1, 'config' => $config]];
    }

    /** @return list<string> */
    private function codes(array $result): array
    {
        return array_column($result['errors'], 'code');
    }

    public function testAllOfficialTemplatesAreValidForTheirContextEvenStrict(): void
    {
        foreach ($this->templates->all() as $template) {
            if ($template['id'] === DisplayLayoutTemplateCatalog::BLANK) {
                continue;
            }
            foreach ($template['contexts'] as $context) {
                $result = $this->validator->validate($template['layout'], $context, true);
                self::assertSame([], $result['errors'], $template['id']);
            }
        }
    }

    public function testBlankTemplateIsSavableButNotPublishable(): void
    {
        $blank = $this->templates->find(DisplayLayoutTemplateCatalog::BLANK)['layout'];
        self::assertSame([], $this->validator->validate($blank, 'grossanlass', false)['errors']);
        self::assertContains('variant_empty', $this->codes($this->validator->validate($blank, 'grossanlass', true)));
    }

    public function testGaTemplatesAreNotOfferedInDepartmentContext(): void
    {
        $ids = array_column($this->templates->forContext('department'), 'id');
        self::assertSame([DisplayLayoutTemplateCatalog::BLANK], $ids);
    }

    public function testTemplateCopiesShareNothingWithTheCatalog(): void
    {
        $a = $this->templates->find('ga_materiallager')['layout'];
        $a['variants'][0]['name'] = 'Geändert';
        $a['pages'][0]['items'][] = ['x' => 1];
        $b = $this->templates->find('ga_materiallager')['layout'];
        self::assertSame('Standard 16:9', $b['variants'][0]['name']);
        self::assertNotContains(['x' => 1], $b['pages'][0]['items']);
    }

    public function testGrossanlassWidgetsAreRejectedInDepartmentContextButGeneralOnesAreAllowed(): void
    {
        $layout = $this->doc([$this->widgetItem('a', 'ga_projects', [], 0, 0, 6, 6), $this->widgetItem('b', 'text', ['text' => 'Hallo'], 6)]);
        self::assertSame([], $this->validator->validate($layout, 'grossanlass', true)['errors']);
        $result = $this->validator->validate($layout, 'department', true);
        self::assertSame(['widget_not_allowed'], $this->codes($result));
    }

    public function testWidgetConfigIsStrictlyValidated(): void
    {
        $result = $this->validator->validate($this->doc([$this->widgetItem('a', 'text', ['text' => 'x', 'html' => '<b>'])]), 'grossanlass', false);
        self::assertContains('config_unknown_field', $this->codes($result));

        $result = $this->validator->validate($this->doc([$this->widgetItem('a', 'image', ['source' => 'url', 'url' => 'javascript:alert(1)'])]), 'grossanlass', false);
        self::assertContains('config_invalid_url', $this->codes($result));
        $result = $this->validator->validate($this->doc([$this->widgetItem('a', 'image', ['source' => 'url', 'url' => 'data:text/html;base64,AAAA'])]), 'grossanlass', false);
        self::assertContains('config_invalid_url', $this->codes($result));
        $result = $this->validator->validate($this->doc([$this->widgetItem('a', 'image', ['source' => 'url', 'url' => 'http://example.org/a.png'])]), 'grossanlass', false);
        self::assertContains('config_invalid_url', $this->codes($result));

        $result = $this->validator->validate($this->doc([$this->widgetItem('a', 'progress', ['value' => 140])]), 'grossanlass', false);
        self::assertContains('config_out_of_range', $this->codes($result));

        $result = $this->validator->validate($this->doc([$this->widgetItem('a', 'nope')]), 'grossanlass', false);
        self::assertContains('widget_unknown', $this->codes($result));
    }

    public function testDefaultsAreFilledIn(): void
    {
        $result = $this->validator->validate($this->doc([$this->widgetItem('a', 'clock')]), 'grossanlass', false);
        self::assertSame(['format' => 'time', 'align' => 'right'], $result['layout']['pages'][0]['items'][0]['content']['config']);
    }

    public function testUnknownKeysAndBadStructureAreRejected(): void
    {
        $layout = $this->doc();
        $layout['script'] = 'x';
        self::assertContains('unknown_key', $this->codes($this->validator->validate($layout, 'grossanlass', false)));

        $layout = $this->doc();
        $layout['schema_version'] = 2;
        self::assertContains('schema_version_unsupported', $this->codes($this->validator->validate($layout, 'grossanlass', false)));

        self::assertContains('layout_invalid', $this->codes($this->validator->validate('x', 'grossanlass', false)));
    }

    public function testGridBoundsOverlapAndMinimumSize(): void
    {
        $outside = $this->doc([$this->widgetItem('a', 'text', [], 8, 0, 6, 2)]);
        self::assertContains('out_of_bounds', $this->codes($this->validator->validate($outside, 'grossanlass', false)));

        $overlap = $this->doc([$this->widgetItem('a', 'text', [], 0, 0, 6, 4), $this->widgetItem('b', 'text', [], 5, 3, 6, 4)]);
        self::assertSame([], $this->validator->validate($overlap, 'grossanlass', false)['errors']);
        self::assertContains('items_overlap', $this->codes($this->validator->validate($overlap, 'grossanlass', true)));

        $small = $this->doc([$this->widgetItem('a', 'ga_projects', [], 0, 0, 2, 2)]);
        self::assertContains('widget_too_small', $this->codes($this->validator->validate($small, 'grossanlass', true)));
    }

    public function testPageCyclesAreRejected(): void
    {
        $layout = $this->doc([['id' => 'i1', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'content' => ['type' => 'page', 'page_id' => 'p2']]]);
        $layout['pages'][] = ['id' => 'p2', 'name' => 'B', 'rows' => 12, 'items' => [['id' => 'i2', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'content' => ['type' => 'page', 'page_id' => 'p1']]]];
        self::assertContains('page_cycle', $this->codes($this->validator->validate($layout, 'grossanlass', false)));

        $self = $this->doc([['id' => 'i1', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'content' => ['type' => 'page', 'page_id' => 'p1']]]);
        self::assertContains('page_cycle', $this->codes($this->validator->validate($self, 'grossanlass', false)));
    }

    public function testUnknownPageReferenceAndNestingDepth(): void
    {
        $unknown = $this->doc([], ['type' => 'page', 'page_id' => 'missing']);
        self::assertContains('page_ref_unknown', $this->codes($this->validator->validate($unknown, 'grossanlass', false)));

        $content = ['type' => 'widget', 'widget' => 'text', 'widget_version' => 1, 'config' => ['text' => 'x']];
        for ($i = 0; $i < 8; ++$i) {
            $content = ['type' => 'group', 'rows' => 12, 'items' => [['id' => 'g', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'content' => $content]]];
        }
        $deep = $this->doc([['id' => 'i1', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'content' => $content]]);
        self::assertContains('too_deep', $this->codes($this->validator->validate($deep, 'grossanlass', false)));
    }

    public function testNestedPageChainBeyondDepthLimitIsRejected(): void
    {
        $layout = ['schema_version' => 1, 'default_variant_id' => 'v1', 'variants' => [['id' => 'v1', 'name' => 'S', 'aspect_ratio' => '16:9', 'root' => ['type' => 'page', 'page_id' => 'p0']]], 'pages' => []];
        for ($i = 0; $i < 8; ++$i) {
            $items = $i < 7
                ? [['id' => 'r', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 12, 'content' => ['type' => 'page', 'page_id' => 'p' . ($i + 1)]]]
                : [];
            $layout['pages'][] = ['id' => 'p' . $i, 'name' => 'P', 'rows' => 12, 'items' => $items];
        }
        self::assertContains('too_deep', $this->codes($this->validator->validate($layout, 'grossanlass', false)));
    }

    public function testRotationAndCompositionAreAccepted(): void
    {
        $rotation = [
            'type' => 'rotation', 'enabled' => true, 'interval_sec' => 10,
            'steps' => [
                ['duration_sec' => null, 'content' => ['type' => 'widget', 'widget' => 'ga_projects', 'widget_version' => 1, 'config' => []]],
                ['duration_sec' => 30, 'content' => ['type' => 'page', 'page_id' => 'p2']],
            ],
        ];
        $layout = $this->doc([['id' => 'i1', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'content' => $rotation]], ['type' => 'rotation', 'enabled' => false, 'interval_sec' => 20, 'steps' => [['duration_sec' => null, 'content' => ['type' => 'page', 'page_id' => 'p1']]]]);
        $layout['pages'][] = ['id' => 'p2', 'name' => 'B', 'rows' => 12, 'items' => [$this->widgetItem('w', 'text', ['text' => 'Hallo'])]];
        $result = $this->validator->validate($layout, 'grossanlass', true);
        self::assertSame([], $result['errors']);

        $layout['pages'][0]['items'][0]['content']['interval_sec'] = 1;
        self::assertContains('interval_invalid', $this->codes($this->validator->validate($layout, 'grossanlass', false)));
    }

    public function testDefaultVariantMustExist(): void
    {
        $layout = $this->doc();
        $layout['default_variant_id'] = 'zzz';
        self::assertContains('default_variant_missing', $this->codes($this->validator->validate($layout, 'grossanlass', false)));
    }
}
