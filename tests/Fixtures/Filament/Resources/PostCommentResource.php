<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostCommentResource\Pages;
use Baspa\FilamentCanary\Tests\Fixtures\Models\PostComment;
use Filament\Resources\Resource;

class PostCommentResource extends Resource
{
    protected static ?string $model = PostComment::class;

    protected static ?string $slug = 'comments';

    protected static ?string $parentResource = CommentedPostResource::class;

    public static function getPages(): array
    {
        return [
            'create' => Pages\CreatePostComment::route('/create'),
            'edit' => Pages\EditPostComment::route('/{record}/edit'),
        ];
    }
}
