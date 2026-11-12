<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\CommentedPostResource\Pages;
use Baspa\FilamentCanary\Tests\Fixtures\Models\Post;
use Filament\Resources\Resource;

class CommentedPostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $slug = 'posts';

    public static function getPages(): array
    {
        return [
            'comments' => Pages\ManagePostComments::route('/{record}/comments'),
        ];
    }
}
