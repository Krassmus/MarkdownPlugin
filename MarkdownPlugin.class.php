<?php

class MarkdownPlugin extends StudIPPlugin implements SystemPlugin {
    
    public function __construct() {
        parent::__construct();
        
        foreach (StudipFormat::getStudipMarkups() as $name => $rule) {
            StudipFormat::removeStudipMarkup($name);
        }
        StudipFormat::addStudipMarkup("html", '&lt;\/?([\w\d]+)(.*?)&gt;', '', 'MarkdownPlugin::format_html');
        StudipFormat::addStudipMarkup("header1", '^(.*?)\n((?:=+)|(?:\-+))(?:\n|$)', '', 'MarkdownPlugin::format_header1');
        StudipFormat::addStudipMarkup("header2", '^(#+)\s+(.*?)(?:\n|$)', '', 'MarkdownPlugin::format_header2');
        StudipFormat::addStudipMarkup("ulists", '(^[-\+\*]+\s+[^\n]+\n?)+', '', 'MarkdownPlugin::format_ulists');
        StudipFormat::addStudipMarkup("olists", '(^\d\.+\s+[^\n]+\n?)+', '', 'MarkdownPlugin::format_olists');
        StudipFormat::addStudipMarkup("verystrong", '\*\*\*(.+?)\*\*\*', '', 'MarkdownPlugin::format_verystrong');
        StudipFormat::addStudipMarkup("strong", '\*\*(.+?)\*\*', '', 'MarkdownPlugin::format_strong');
        StudipFormat::addStudipMarkup("emphasize", '\*(.+?)\*', '', 'MarkdownPlugin::format_em');
        StudipFormat::addStudipMarkup("images", '!\[(.*?)]\(([^\s]+?)(?:\s+&quot;(.*?)&quot;)?\)', '', 'MarkdownPlugin::format_images');
        StudipFormat::addStudipMarkup("links", '\[(.*?)]\(([^\s]+?)(?:\s+&quot;(.*?)&quot;)?\)', '', 'MarkdownPlugin::format_links');
        StudipFormat::addStudipMarkup("oldmedia", '\[(img|flash|audio|video)(.*?)\](.*?)(?=\s|$)', '', 'StudipFormat::markupMedia');
        StudipFormat::addStudipMarkup("oldmails", '(?<=\s|^|\>)(?:\[([^\n\f\]]+?)\])?([\w.!#%+-]+@([[:alnum:].-]+))(?=\s|$)', '', 'StudipFormat::markupEmails');
        StudipFormat::addStudipMarkup("oldlinks", '(?<=\s|^|\>)(?:(?:\[([^\n\f\]]+?)\])?)(\w+?:\/\/.+?)(?=\s|$)', '', 'MarkdownPlugin::format_oldlinks');
        StudipFormat::addStudipMarkup("quotes", '(^&gt;+\s+[^\n]+\n?)+', '', 'MarkdownPlugin::format_quotes');
        StudipFormat::addStudipMarkup("codeblock", '(^(?: {4}|\t)[^\n]+\n?)+', '', 'MarkdownPlugin::format_codeblock');
        StudipFormat::addStudipMarkup("code", '`([^\n]+?)`', '', 'MarkdownPlugin::format_code');
    }
    
    static public function format_code($markup, $matches) {
        return "<code>".$matches[1]."</code>";
    }
    
    static public function format_codeblock($markup, $matches) {
        $codelines = array();
        foreach (explode("\n", $matches[0]) as $line) {
            $codelines[] = $line[0] === " " ? substr($line, 4) : substr($line, 1);
        }
        return "<pre><code>".$markup->quote(implode("\n", $codelines))."</code></pre>";
    }
    
    static public function format_quotes($markup, $matches) {
        $quotes = array();
        foreach (explode("\n", rtrim($matches[0])) as $line) {
            $quotes[] = trim(substr($line, 4));
        }
        return "<blockquote class=\"quote\">".$markup->format(implode("\n", $quotes))."</blockquote>";
    }
    
    static public function format_olists($markup, $matches) {
        $rows = explode("\n", rtrim($matches[0]));
        $list = "<ol>";
        foreach ($rows as $row) {
            list($level, $text) = preg_split('/\s+/', $row, 2);
            $list .= "<li>".$markup->format($text)."</li>";
        }
        $list .= "</ol>";
        return $list;
    }
    
    static public function format_ulists($markup, $matches) {
        $rows = explode("\n", rtrim($matches[0]));
        $indent = 0;

        foreach ($rows as $row) {
            list($level, $text) = preg_split('/\s+/', $row, 2);
            $level = strlen($level);

            if ($indent < $level) {
                for (; $indent < $level; ++$indent) {
                    $type = $row[$indent] == '=' ? 'ol' : 'ul';
                    $result .= sprintf('<%s><li>', $type);
                    $types[] = $type;
                }
            } else {
                for (; $indent > $level; --$indent) {
                    $result .= sprintf('</li></%s>', array_pop($types));
                }

                $result .= '</li><li>';
            }

            $result .= $markup->format($text);
        }

        for (; $indent > 0; --$indent) {
            $result .= sprintf('</li></%s>', array_pop($types));
        }

        return $result;
    }
    
    static public function format_header1($markup, $matches) {
        $tag = $matches[2][0] === "=" ? "h1" : "h2";
        return "<$tag>".$markup->format($matches[1])."</$tag>";
    }
    
    static public function format_header2($markup, $matches) {
        $tag = "h".(strlen($matches[1]) <= 6 ? strlen($matches[1]) : 6);
        return "<$tag>".$markup->format($matches[2])."</$tag>";
    }
    
    static public function format_em($markup, $matches) {
        return "<em>".$markup->format($matches[1])."</em>";
    }

    static public function format_strong($markup, $matches) {
        return "<strong>".$markup->format($matches[1])."</strong>";
    }
    
    static public function format_verystrong($markup, $matches) {
        return "<strong><em>".$markup->format($matches[1])."</em></strong>";
    }
    
    static public function format_images($markup, $matches) {
        $alt_text = $matches[1];
        $url = $matches[2];
        $title = $matches[3] ? $matches[3] : $alt_text;
        return '<img src="'.$url.'" alt="'.$markup->quote($alt_text).'" title="'.$markup->quote($title).'">';
    }
    
    static public function format_links($markup, $matches) {
        $alt_text = $matches[1];
        $url = $matches[2];
        if (stripos($url, "javascript") === 0) {
            return $matches[0];
        }
        $title = $matches[3];
        
        $intern = isLinkIntern($url);
        $url = TransformInternalLinks($url);

        $linkmarkup = clone $markup;
        $linkmarkup->removeMarkup("links");
        
        return sprintf('<a title="%s" class="%s" href="%s"%s>%s</a>',
            $markup->quote($title),
            $intern ? "link-intern" : "link-extern",
            $url,
            $intern ? "" : ' target="_blank"',
            $linkmarkup->format($alt_text)
        );
    }
    
    static public function format_html($markup, $matches, $content) {
        if ($matches[1] === "script" or stripos($matches[0], "javascript:") !== false) {
            //ignore javascript
            return $matches[0];
        }
        preg_match_all("/\s+(\w+)=/", $matches[2], $attributes);
        foreach ($attributes[1] as $attribute) {
            if (stripos($attribute, "on") === 0) {
                //disallow event-listeners
                return $matches[0];
            }
        }
        return html_entity_decode($matches[0]);
    }
    
    static public function format_oldlinks($markup, $matches) 
    {
        $url = $matches[2];
        $title = $matches[1] ? $matches[1] : $url;
        
        $intern = isLinkIntern($url);
        
        $url = TransformInternalLinks($url);

        $linkmarkup = clone $markup;
        $linkmarkup->removeMarkup("oldlinks");
        
        return sprintf('<a class="%s" href="%s"%s>%s</a>',
            $intern ? "link-intern" : "link-extern",
            $url,
            $intern ? "" : ' target="_blank"',
            $linkmarkup->format($title)
        );
    }
    
}