<?php

namespace Shoptimised\AiVisibility\Enums;

enum AttributeType: string
{
    case QuestionAndAnswer = 'question_and_answer';
    case ItemGroupTitle = 'item_group_title';
    case VariantOption = 'variant_option';
    case RelatedProduct = 'related_product';
    case DocumentLink = 'document_link';
    case PopularityRank = 'popularity_rank';
}
