<?php

namespace Shoptimised\AiVisibility\Enums;

enum ActionType: string
{
    case AddQna = 'add_qna';
    case ImproveQna = 'improve_qna';
    case AddVariantOption = 'add_variant_option';
    case ImproveItemGroupTitle = 'improve_item_group_title';
    case AddRelatedProduct = 'add_related_product';
    case AddDocumentLink = 'add_document_link';
    case AddPopularityRank = 'add_popularity_rank';
    case ImproveProductTitle = 'improve_product_title';
    case ImproveDescription = 'improve_description';
    case ReviewPricing = 'review_pricing';
    case ReviewLandingPage = 'review_landing_page';
}
