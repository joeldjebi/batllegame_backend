<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Rich text written in the back-office editor (Trix): only formatting tags survive,
 * scripts, styles, event handlers and unsafe links are stripped.
 */
final class RichText
{
    private const array ELEMENTS = ['p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 'del', 's', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code'];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim(strip_tags($html)) === '') {
            return null;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks(false)
            ->allowElement('a', ['href'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(50_000);

        foreach (self::ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        return (new HtmlSanitizer($config))->sanitize($html);
    }

    /**
     * Plain-text excerpt for cards and meta tags.
     */
    public static function excerpt(?string $html, int $limit = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '</div>', '<br>', '</li>'], ' ', (string) $html)))));

        return Str::limit($text, $limit);
    }
}
