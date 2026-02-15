<?php

namespace Aicl\Filament\Resources\PreventionRules;

use Aicl\Filament\Resources\PreventionRules\Pages\CreatePreventionRule;
use Aicl\Filament\Resources\PreventionRules\Pages\EditPreventionRule;
use Aicl\Filament\Resources\PreventionRules\Pages\ListPreventionRules;
use Aicl\Filament\Resources\PreventionRules\Pages\ViewPreventionRule;
use Aicl\Filament\Resources\PreventionRules\Schemas\PreventionRuleForm;
use Aicl\Filament\Resources\PreventionRules\Tables\PreventionRulesTable;
use Aicl\Models\PreventionRule;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PreventionRuleResource extends Resource
{
    protected static ?string $model = PreventionRule::class;

    protected static string|UnitEnum|null $navigationGroup = 'RLM Hub';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'rule_text';

    public static function form(Schema $schema): Schema
    {
        return PreventionRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PreventionRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreventionRules::route('/'),
            'create' => CreatePreventionRule::route('/create'),
            'view' => ViewPreventionRule::route('/{record}'),
            'edit' => EditPreventionRule::route('/{record}/edit'),
        ];
    }
}
