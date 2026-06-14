<?php

namespace Shoptimised\AiVisibility\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Shoptimised\AiVisibility\Filament\Resources\AiVisibilityBatchResource\Pages;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;

/**
 * Read-only oversight of every retailer's visibility batches (staff panel).
 */
class AiVisibilityBatchResource extends Resource
{
    protected static ?string $model = AiVisibilityBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'AI visibility';

    protected static ?string $modelLabel = 'visibility batch';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->limit(40),
                TextColumn::make('retailer.name')->label('Retailer')->sortable()->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('total_item_groups')->label('Groups'),
                TextColumn::make('completed_prompts')->label('Done'),
                TextColumn::make('total_prompts')->label('Runs'),
                TextColumn::make('failed_prompts')->label('Failed'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiVisibilityBatches::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
