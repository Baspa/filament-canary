<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\TeamPostResource\Pages;
use Baspa\FilamentCanary\Tests\Fixtures\Models\Post;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TeamPostResource extends Resource
{
    protected static ?string $model = Post::class;

    // Posts have no team column; the tenant only has to be in the URL.
    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeamPosts::route('/'),
            'edit' => Pages\EditTeamPost::route('/{record}/edit'),
        ];
    }
}
