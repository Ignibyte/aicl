<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\View\Components\Accordion;
use Aicl\View\Components\AccordionItem;
use Aicl\View\Components\Avatar;
use Aicl\View\Components\Badge;
use Aicl\View\Components\Combobox;
use Aicl\View\Components\CommandPalette;
use Aicl\View\Components\DataTable;
use Aicl\View\Components\Drawer;
use Aicl\View\Components\Dropdown;
use Aicl\View\Components\Modal;
use Aicl\View\Components\Toast;
use Aicl\View\Components\Tooltip;
use Illuminate\View\Component;
use Illuminate\View\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tests all 12 new component PHP classes (Phase 1 components).
 */
class NewComponentClassTest extends TestCase
{
    // ─── Structural tests via data provider ────────────────────

    #[DataProvider('newComponentProvider')]
    public function test_component_extends_base_class(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, Component::class));
    }

    #[DataProvider('newComponentProvider')]
    public function test_component_has_render_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'render'));
    }

    public static function newComponentProvider(): array
    {
        return [
            'Modal' => [Modal::class],
            'Drawer' => [Drawer::class],
            'Dropdown' => [Dropdown::class],
            'Tooltip' => [Tooltip::class],
            'CommandPalette' => [CommandPalette::class],
            'Combobox' => [Combobox::class],
            'Accordion' => [Accordion::class],
            'AccordionItem' => [AccordionItem::class],
            'Toast' => [Toast::class],
            'DataTable' => [DataTable::class],
            'Avatar' => [Avatar::class],
            'Badge' => [Badge::class],
        ];
    }

    // ─── Modal ─────────────────────────────────────────────────

    public function test_modal_default_size_is_md(): void
    {
        $modal = new Modal;
        $this->assertEquals('md', $modal->size);
        $this->assertEquals('max-w-md', $modal->sizeClass());
    }

    public function test_modal_size_class_mapping(): void
    {
        $this->assertEquals('max-w-sm', (new Modal(size: 'sm'))->sizeClass());
        $this->assertEquals('max-w-lg', (new Modal(size: 'lg'))->sizeClass());
        $this->assertEquals('max-w-xl', (new Modal(size: 'xl'))->sizeClass());
        $this->assertEquals('max-w-4xl', (new Modal(size: 'full'))->sizeClass());
    }

    public function test_modal_closeable_defaults(): void
    {
        $modal = new Modal;
        $this->assertTrue($modal->closeable);
        $this->assertTrue($modal->closeOnEscape);
        $this->assertTrue($modal->closeOnClickOutside);
        $this->assertTrue($modal->trapFocus);
    }

    public function test_modal_renders_view(): void
    {
        $modal = new Modal;
        $view = $modal->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── Drawer ────────────────────────────────────────────────

    public function test_drawer_default_position_is_right(): void
    {
        $drawer = new Drawer;
        $this->assertEquals('right', $drawer->position);
    }

    public function test_drawer_default_width_is_md(): void
    {
        $drawer = new Drawer;
        $this->assertEquals('md', $drawer->width);
    }

    public function test_drawer_renders_view(): void
    {
        $drawer = new Drawer;
        $view = $drawer->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── Avatar ────────────────────────────────────────────────

    public function test_avatar_default_size_is_md(): void
    {
        $avatar = new Avatar;
        $this->assertEquals('md', $avatar->size);
    }

    public function test_avatar_generates_initials(): void
    {
        $avatar = new Avatar(name: 'John Doe');
        $this->assertEquals('JD', $avatar->initials());
    }

    public function test_avatar_single_name_returns_two_chars(): void
    {
        $avatar = new Avatar(name: 'Admin');
        $this->assertEquals('AD', $avatar->initials());
    }

    public function test_avatar_renders_view(): void
    {
        $avatar = new Avatar;
        $view = $avatar->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── Badge ─────────────────────────────────────────────────

    public function test_badge_default_color_is_gray(): void
    {
        $badge = new Badge(label: 'Test');
        $this->assertEquals('gray', $badge->color);
    }

    public function test_badge_default_variant_is_soft(): void
    {
        $badge = new Badge(label: 'Test');
        $this->assertEquals('soft', $badge->variant);
    }

    public function test_badge_renders_view(): void
    {
        $badge = new Badge(label: 'Test');
        $view = $badge->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── DataTable ─────────────────────────────────────────────

    public function test_data_table_default_props(): void
    {
        $table = new DataTable;
        $this->assertTrue($table->sortable);
        $this->assertTrue($table->filterable);
        $this->assertTrue($table->paginated);
        $this->assertEquals(10, $table->perPage);
    }

    public function test_data_table_renders_view(): void
    {
        $table = new DataTable;
        $view = $table->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── Tooltip ───────────────────────────────────────────────

    public function test_tooltip_default_position_is_top(): void
    {
        $tooltip = new Tooltip(content: 'Help');
        $this->assertEquals('top', $tooltip->position);
    }

    public function test_tooltip_renders_view(): void
    {
        $tooltip = new Tooltip(content: 'Help');
        $view = $tooltip->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── Dropdown ──────────────────────────────────────────────

    public function test_dropdown_default_alignment(): void
    {
        $dropdown = new Dropdown;
        $this->assertEquals('bottom-start', $dropdown->align);
    }

    public function test_dropdown_renders_view(): void
    {
        $dropdown = new Dropdown;
        $view = $dropdown->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── Toast ─────────────────────────────────────────────────

    public function test_toast_default_position(): void
    {
        $toast = new Toast;
        $this->assertEquals('top-right', $toast->position);
    }

    public function test_toast_renders_view(): void
    {
        $toast = new Toast;
        $view = $toast->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── Accordion ─────────────────────────────────────────────

    public function test_accordion_default_allows_single(): void
    {
        $accordion = new Accordion;
        $this->assertFalse($accordion->allowMultiple);
    }

    public function test_accordion_renders_view(): void
    {
        $accordion = new Accordion;
        $view = $accordion->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── AccordionItem ─────────────────────────────────────────

    public function test_accordion_item_renders_view(): void
    {
        $item = new AccordionItem(name: 'section-1', label: 'Section 1');
        $view = $item->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── Combobox ──────────────────────────────────────────────

    public function test_combobox_renders_view(): void
    {
        $combobox = new Combobox(name: 'category');
        $view = $combobox->render();
        $this->assertInstanceOf(View::class, $view);
    }

    // ─── CommandPalette ────────────────────────────────────────

    public function test_command_palette_renders_view(): void
    {
        $palette = new CommandPalette;
        $view = $palette->render();
        $this->assertInstanceOf(View::class, $view);
    }
}
