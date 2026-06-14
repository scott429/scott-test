<?php

namespace Shoptimised\AiVisibility\Enums;

enum Role: string
{
    case ShoptimisedAdmin = 'shoptimised_admin';
    case ShoptimisedAnalyst = 'shoptimised_analyst';
    case RetailerAdmin = 'retailer_admin';
    case RetailerViewer = 'retailer_viewer';

    public function isStaff(): bool
    {
        return in_array($this, [self::ShoptimisedAdmin, self::ShoptimisedAnalyst], true);
    }

    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
