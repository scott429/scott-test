<?php

namespace Shoptimised\AiVisibility\Enums;

enum EvidenceType: string
{
    case RawResponse = 'raw_response';
    case Screenshot = 'screenshot';
    case Citation = 'citation';
    case ParsedResult = 'parsed_result';
}
