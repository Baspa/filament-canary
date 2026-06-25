<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\TagResource\Pages;
use Baspa\FilamentCanary\Tests\Fixtures\Models\Tag;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Backed by a model WITHOUT a factory: index/create should pass, while the
 * record-bound edit/view pages should be skipped with a visible reason.
 */
class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTags::route('/'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
