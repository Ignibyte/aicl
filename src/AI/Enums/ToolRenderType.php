<?php

namespace Aicl\AI\Enums;

enum ToolRenderType: string
{
    case Text = 'text';
    case Table = 'table';
    case KeyValue = 'key-value';
    case Status = 'status';
}
