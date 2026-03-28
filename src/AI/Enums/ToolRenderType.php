<?php

declare(strict_types=1);

namespace Aicl\AI\Enums;

/**
 * ToolRenderType.
 */
enum ToolRenderType: string
{
    case Text = 'text';
    case Table = 'table';
    case KeyValue = 'key-value';
    case Status = 'status';
}
