<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\DeferredPostResource\Pages;
use Baspa\FilamentCanary\Tests\Fixtures\Models\Post;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeferredPostResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            // Unquoted on purpose: SQLite reads an unknown "quoted" column as a string literal.
            ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('missing_column = 1'))
            ->columns([
                TextColumn::make('title'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeferredPosts::route('/'),
        ];
    }
}
