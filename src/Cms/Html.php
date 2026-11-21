<?php
namespace App\Cms;
final class Html
{
    public static function safeUrl(string $url): bool
    {
        return $url==='' || (preg_match('~^/(?!/)[^\\\\\x00-\x20]*$~u',$url)===1) || (filter_var($url,FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true));
    }
    public static function sanitize(string $html): string
    {
        $doc=new \DOMDocument();
        $previous=libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="content">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET);
        libxml_clear_errors();libxml_use_internal_errors($previous);
        $allowed=['p','br','strong','b','em','i','ul','ol','li','h2','h3','h4','blockquote','a'];
        $clean=function(\DOMNode $node) use (&$clean,$allowed): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof \DOMComment) { $node->removeChild($child); continue; }
                if (!$child instanceof \DOMElement) continue;
                $tag=strtolower($child->tagName);
                if (in_array($tag,['script','style','iframe','object','svg','math','form','input','button','template'])) { $node->removeChild($child);continue; }
                $clean($child);
                if (!in_array($tag,$allowed,true)) { while ($child->firstChild) $node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue; }
                $href=$child->getAttribute('href');
                foreach (iterator_to_array($child->attributes) as $attr) $child->removeAttribute($attr->name);
                if ($tag==='a' && $href!=='' && self::safeUrl($href)) { $child->setAttribute('href',$href);$child->setAttribute('rel','noopener noreferrer'); }
            }
        };
        $root=$doc->getElementById('content');
        if (!$root) return htmlspecialchars(strip_tags($html),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $clean($root);$result='';foreach ($root->childNodes as $child) $result.=$doc->saveHTML($child);
        return $result;
    }
}
