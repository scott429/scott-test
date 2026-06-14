<?php

namespace Shoptimised\AiVisibility\Enums;

enum PromptType: string
{
    case GenericDiscovery = 'generic_discovery';
    case PriceLed = 'price_led';
    case AttributeLed = 'attribute_led';
    case Comparison = 'comparison';
    case UseCase = 'use_case';
    case ProblemLed = 'problem_led';
    case VariantLed = 'variant_led';
    case CommercialIntent = 'commercial_intent';
}
