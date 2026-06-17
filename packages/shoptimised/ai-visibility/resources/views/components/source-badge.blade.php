@props(['source' => null])
{{-- Marks where a tested buyer question came from. Only AI-discovered FAQs
     (for feeds with no Q&A of their own) carry a visible badge; feed Q&A is
     the default and needs no label. --}}
@if ($source === 'discovered_faq')
    <span class="aiv-badge is-info" title="No Q&A in your feed — discovered from the GTIN + item group title and tested for you.">AI-discovered</span>
@endif
