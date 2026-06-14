<?php

namespace Shoptimised\AiVisibility\Enums;

enum MatchType: string
{
    case ExactItemGroupAndUrl = 'exact_item_group_and_url';
    case ProductUrl = 'product_url';
    case RetailerDomain = 'retailer_domain';
    case ExactItemGroup = 'exact_item_group';
    case SemanticProductFamily = 'semantic_product_family';
    case CategoryOnly = 'category_only';
    case None = 'none';
}
