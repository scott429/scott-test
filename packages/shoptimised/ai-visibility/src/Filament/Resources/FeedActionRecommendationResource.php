<?php

namespace Shoptimised\AiVisibility\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Shoptimised\AiVisibility\Enums\RecommendationStatus;
use Shoptimised\AiVisibility\Filament\Resources\FeedActionRecommendationResource\Pages;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;

/**
 * Recommendation triage for staff: review and move the status through the
 * workflow. Approving/completing is gated by the FeedActionRecommendationPolicy.
 */
class FeedActionRecommendationResource extends Resource
{
    protected static ?string $model = FeedActionRecommendation::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationGroup = 'AI visibility';

    protected static ?string $modelLabel = 'recommendation';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')
                ->options(collect(RecommendationStatus::cases())
                    ->mapWithKeys(fn ($s) => [$s->value => str_replace('_', ' ', $s->value)])
                    ->all())
                ->required(),
            Textarea::make('reason')->disabled()->columnSpanFull(),
            Textarea::make('evidence_summary')->disabled()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action_type')->badge()->label('Action'),
                TextColumn::make('itemGroup.item_group_title')->label('Item group')->limit(30),
                TextColumn::make('retailer.name')->label('Retailer')->sortable(),
                TextColumn::make('priority')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedActionRecommendations::route('/'),
            'edit' => Pages\EditFeedActionRecommendation::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
