<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\SecretResource\Pages;
use Baspa\FilamentCanary\Tests\Fixtures\Models\Post;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Denies access to everyone — the default factory user should get a 403, which the
 * sweep reports as needs-auth (configure acting_as), not as a hard failure.
 */
class SecretResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function canAccess(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
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
            'index' => Pages\ListSecrets::route('/'),
        ];
    }
}
