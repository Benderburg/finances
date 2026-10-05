<?php

namespace Noros\Cms\Filament;

use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

abstract class CmsResource extends Resource
{
    public static function feature(): string
    {
        return match (class_basename(static::getModel())) {
            'Post', 'Category', 'Tag' => 'blog',
            'Comment' => 'comments',
            'PostRating', 'Rating' => 'ratings',
            'PortfolioProject', 'PortfolioWidget' => 'portfolio',
            'Menu', 'MenuItem' => 'menus',
            default => 'pages',
        };
    }

    public static function getAuthorizationResponse(string|\UnitEnum $action, ?Model $record = null): Response
    {
        $feature = static::feature();
        if (! config('noros-cms.features.'.$feature)) {
            return Response::deny();
        }

        return Gate::inspect($feature === 'comments' ? 'moderate_comments' : 'manage_'.$feature);
    }
}
