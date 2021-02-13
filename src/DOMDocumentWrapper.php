<?php

namespace PhpQuery;

class DOMDocumentWrapper
{
    public $document;
    public $id;
    public $contentType = '';
    public $xpath;
    public $data = array();

    public static $defaultCharset = 'UTF-8';
    public static $defaultDoctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">';

    public $root;
    public $isDocumentFragment;
    public $isXML = false;
    public $isXHTML = false;
    public $isHTML = false;
    public $charset;

    public function __construct($markup, $contentType = null, $newDocumentID = null)
    {
        $this->load($markup, $contentType, $newDocumentID);
    }

    public function load($markup, $contentType = null, $newDocumentID = null)
    {
        $this->contentType = strtolower($contentType);
        $loaded = $this->loadMarkup($markup);


        if ( $loaded ) {
            $this->document->preserveWhiteSpace = true;
            $this->xpath = new \DOMXPath($this->document);
            $this->afterMarkupLoad();
            return true;
        }

        return false;
    }

    protected function afterMarkupLoad()
    {
        if ( $this->isXHTML ) {
            $this->xpath->registerNamespace("html", "http://www.w3.org/1999/xhtml");
        }
    }

    protected function loadMarkup($markup)
    {
        $loaded = false;
        if ( $this->contentType ) {
            self::debug("Load markup for content type {$this->contentType}");
            // content determined by contentType
            list($contentType, $charset) = $this->contentTypeToArray($this->contentType);
            switch ( $contentType ) {
                case 'text/html':
                    // phpQuery::debug("Loading HTML, content type '{$this->contentType}'");
                    $loaded = $this->loadMarkupHTML($markup, $charset);
                    break;
                case 'text/xml':
                case 'application/xhtml+xml':
                    // phpQuery::debug("Loading XML, content type '{$this->contentType}'");
                    $loaded = $this->loadMarkupXML($markup, $charset);
                    break;
                default:
                    // for feeds or anything that sometimes doesn't use text/xml
                    if ( strpos('xml', $this->contentType) !== false ) {
                        // phpQuery::debug("Loading XML, content type '{$this->contentType}'");
                        $loaded = $this->loadMarkupXML($markup, $charset);
                    }
                //else
                // phpQuery::debug("Could not determine document type from content type '{$this->contentType}'");
            }
        } else {
            $loaded = $this->loadMarkupHTML($markup);
        }
        return $loaded;
    }

    protected function loadMarkupReset()
    {
        $this->isXML = $this->isXHTML = $this->isHTML = false;
    }

    protected function documentCreate($charset, $version = '1.0')
    {
        if ( ! $version )
            $version = '1.0';
        $this->document = new \DOMDocument($version, $charset);
        $this->charset = $this->document->encoding;
//		$this->document->encoding = $charset;
        $this->document->formatOutput = true;
        $this->document->preserveWhiteSpace = true;
    }

    protected function loadMarkupHTML($markup, $requestedCharset = null)
    {
        $this->loadMarkupReset();
        $this->isHTML = true;
        if ( ! isset($this->isDocumentFragment) )
            $this->isDocumentFragment = self::isDocumentFragmentHTML($markup);
        $charset = null;
        $documentCharset = $this->charsetFromHTML($markup);
        $addDocumentCharset = false;
        if ( $documentCharset ) {
            $charset = $documentCharset;
            $markup = $this->charsetFixHTML($markup);
        } else if ( $requestedCharset ) {
            $charset = $requestedCharset;
        }
        if ( ! $charset )
            $charset = self::$defaultCharset;
        // HTTP 1.1 says that the default charset is ISO-8859-1
        // @see http://www.w3.org/International/O-HTTP-charset
        if ( ! $documentCharset ) {
            $documentCharset = 'ISO-8859-1';
            $addDocumentCharset = true;
        }
        // Should be careful here, still need 'magic encoding detection' since lots of pages have other 'default encoding'
        // Worse, some pages can have mixed encodings... we'll try not to worry about that
        $requestedCharset = strtoupper($requestedCharset);
        $documentCharset = strtoupper($documentCharset);
        if ( $requestedCharset && $documentCharset && $requestedCharset !== $documentCharset ) {
            // Document Encoding Conversion
            // http://code.google.com/p/phpquery/issues/detail?id=86
            if ( function_exists('mb_detect_encoding') ) {
                $possibleCharsets = array( $documentCharset, $requestedCharset, 'AUTO' );
                $docEncoding = mb_detect_encoding($markup, implode(', ', $possibleCharsets));
                if ( ! $docEncoding )
                    $docEncoding = $documentCharset; // ok trust the document
                // phpQuery::debug("DETECTED '$docEncoding'");
                // Detected does not match what document says...
                if ( $docEncoding !== $documentCharset ) {
                    // Tricky..
                }
                if ( $docEncoding !== $requestedCharset ) {
                    // phpQuery::debug("CONVERT $docEncoding => $requestedCharset");
                    $markup = mb_convert_encoding($markup, $requestedCharset, $docEncoding);
                    $markup = $this->charsetAppendToHTML($markup, $requestedCharset);
                    $charset = $requestedCharset;
                }
            } else {
                // phpQuery::debug("TODO: charset conversion without mbstring...");
            }
        }
        $return = false;
        if ( $this->isDocumentFragment ) {
            // phpQuery::debug("Full markup load (HTML), DocumentFragment detected, using charset '$charset'");
            $return = $this->documentFragmentLoadMarkup($this, $charset, $markup);
        } else {
            if ( $addDocumentCharset ) {
                // phpQuery::debug("Full markup load (HTML), appending charset: '$charset'");
                $markup = $this->charsetAppendToHTML($markup, $charset);
            }
            // phpQuery::debug("Full markup load (HTML), documentCreate('$charset')");
            $this->documentCreate($charset);
            $this->document->loadHTML($markup);
            if ( $return )
                $this->root = $this->document;
        }
        if ( $return && ! $this->contentType )
            $this->contentType = 'text/html';
        return $return;
    }

    protected function loadMarkupXML($markup, $requestedCharset = null)
    {
        if ( phpQuery::$debug )
            // phpQuery::debug('Full markup load (XML): ' . substr($markup, 0, 250));
            $this->loadMarkupReset();
        $this->isXML = true;
        // check agains XHTML in contentType or markup
        $isContentTypeXHTML = $this->isXHTML();
        $isMarkupXHTML = $this->isXHTML($markup);
        if ( $isContentTypeXHTML || $isMarkupXHTML ) {
            self::debug('Full markup load (XML), XHTML detected');
            $this->isXHTML = true;
        }
        // determine document fragment
        if ( ! isset($this->isDocumentFragment) )
            $this->isDocumentFragment = $this->isXHTML
                ? self::isDocumentFragmentXHTML($markup)
                : self::isDocumentFragmentXML($markup);
        // this charset will be used
        $charset = null;
        // charset from XML declaration @var string
        $documentCharset = $this->charsetFromXML($markup);
        if ( ! $documentCharset ) {
            if ( $this->isXHTML ) {
                // this is XHTML, try to get charset from content-type meta header
                $documentCharset = $this->charsetFromHTML($markup);
                if ( $documentCharset ) {
                    // phpQuery::debug("Full markup load (XML), appending XHTML charset '$documentCharset'");
                    $this->charsetAppendToXML($markup, $documentCharset);
                    $charset = $documentCharset;
                }
            }
            if ( ! $documentCharset ) {
                // if still no document charset...
                $charset = $requestedCharset;
            }
        } else if ( $requestedCharset ) {
            $charset = $requestedCharset;
        }
        if ( ! $charset ) {
            $charset = phpQuery::$defaultCharset;
        }
        if ( $requestedCharset && $documentCharset && $requestedCharset != $documentCharset ) {
            // TODO place for charset conversion
//			$charset = $requestedCharset;
        }
        $return = false;
        if ( $this->isDocumentFragment ) {
            // phpQuery::debug("Full markup load (XML), DocumentFragment detected, using charset '$charset'");
            $return = $this->documentFragmentLoadMarkup($this, $charset, $markup);
        } else {
            // FIXME ???
            if ( $isContentTypeXHTML && ! $isMarkupXHTML )
                if ( ! $documentCharset ) {
                    // phpQuery::debug("Full markup load (XML), appending charset '$charset'");
                    $markup = $this->charsetAppendToXML($markup, $charset);
                }
            // see http://pl2.php.net/manual/en/book.dom.php#78929
            // LIBXML_DTDLOAD (>= PHP 5.1)
            // does XML ctalogues works with LIBXML_NONET
            //		$this->document->resolveExternals = true;
            // TODO test LIBXML_COMPACT for performance improvement
            // create document
            $this->documentCreate($charset);
            if ( phpversion() < 5.1 ) {
                $this->document->resolveExternals = true;
                $return = phpQuery::$debug === 2
                    ? $this->document->loadXML($markup)
                    : @$this->document->loadXML($markup);
            } else {
                /** @link http://pl2.php.net/manual/en/libxml.constants.php */
                $libxmlStatic = phpQuery::$debug === 2
                    ? LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_NONET
                    : LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR;
                $return = $this->document->loadXML($markup, $libxmlStatic);
// 				if (! $return)
// 					$return = $this->document->loadHTML($markup);
            }
            if ( $return )
                $this->root = $this->document;
        }
        if ( $return ) {
            if ( ! $this->contentType ) {
                if ( $this->isXHTML )
                    $this->contentType = 'application/xhtml+xml';
                else
                    $this->contentType = 'text/xml';
            }
            return $return;
        } else {
            throw new Exception("Error loading XML markup");
        }
    }

    protected function contentTypeToArray($contentType)
    {
        $test = null;
        $test =
        $matches = explode(';', trim(strtolower($contentType)));
        if ( isset($matches[1]) ) {
            $matches[1] = explode('=', $matches[1]);
            // strip 'charset='
            $matches[1] = isset($matches[1][1]) && trim($matches[1][1])
                ? $matches[1][1]
                : $matches[1][0];
        } else
            $matches[1] = null;
        return $matches;
    }

    /**
     *
     * @param $markup
     * @return array contentType, charset
     */
    protected function contentTypeFromHTML($markup)
    {
        $matches = array();
        // find meta tag
        preg_match('@<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i',
            $markup, $matches
        );
        if ( ! isset($matches[0]) )
            return array( null, null );
        // get attr 'content'
        preg_match('@content\\s*=\\s*(["|\'])(.+?)\\1@', $matches[0], $matches);
        if ( ! isset($matches[0]) )
            return array( null, null );
        return $this->contentTypeToArray($matches[2]);
    }

    protected function charsetFromHTML($markup)
    {
        $contentType = $this->contentTypeFromHTML($markup);
        return $contentType[1];
    }

    protected function charsetFromXML($markup)
    {
        $matches;
        // find declaration
        preg_match('@<' . '?xml[^>]+encoding\\s*=\\s*(["|\'])(.*?)\\1@i',
            $markup, $matches
        );
        return isset($matches[2])
            ? strtolower($matches[2])
            : null;
    }

    /**
     * Repositions meta[type=charset] at the start of head. Bypasses DOMDocument bug.
     *
     * @link http://code.google.com/p/phpquery/issues/detail?id=80
     * @param $html
     */
    protected function charsetFixHTML($markup)
    {
        $matches = array();
        // find meta tag
        preg_match('@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i',
            $markup, $matches, PREG_OFFSET_CAPTURE
        );
        if ( ! isset($matches[0]) )
            return;
        $metaContentType = $matches[0][0];
        $markup = substr($markup, 0, $matches[0][1])
            . substr($markup, $matches[0][1] + strlen($metaContentType));
        $headStart = stripos($markup, '<head>');
        $markup = substr($markup, 0, $headStart + 6) . $metaContentType
            . substr($markup, $headStart + 6);
        return $markup;
    }

    protected function charsetAppendToHTML($html, $charset, $xhtml = false)
    {
        // remove existing meta[type=content-type]
        $html = preg_replace('@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i', '', $html);
        $meta = '<meta http-equiv="Content-Type" content="text/html;charset='
            . $charset . '" '
            . ($xhtml ? '/' : '')
            . '>';
        if ( strpos($html, '<head') === false ) {
            if ( strpos($html, '<html') === false ) {
                return $meta . $html;
            } else {
                return preg_replace(
                    '@<html(.*?)(?(?<!\?)>)@s',
                    "<html\\1><head>{$meta}</head>",
                    $html
                );
            }
        } else {
            return preg_replace(
                '@<head(.*?)(?(?<!\?)>)@s',
                '<head\\1>' . $meta,
                $html
            );
        }
    }

    protected function charsetAppendToXML($markup, $charset)
    {
        $declaration = '<' . '?xml version="1.0" encoding="' . $charset . '"?' . '>';
        return $declaration . $markup;
    }

    public static function isDocumentFragmentHTML($markup)
    {
        return stripos($markup, '<html') === false && stripos($markup, '<!doctype') === false;
    }

    public static function isDocumentFragmentXML($markup)
    {
        return stripos($markup, '<' . '?xml') === false;
    }

    public static function isDocumentFragmentXHTML($markup)
    {
        return self::isDocumentFragmentHTML($markup);
    }


    /**
     *
     * @param $source
     * @param $target
     * @param $sourceCharset
     * @return array Array of imported nodes.
     */
    public function import($source, $sourceCharset = null)
    {
        // TODO charset conversions
        $return = array();
        if ( $source instanceof DOMNODE && ! ($source instanceof DOMNODELIST) )
            $source = array( $source );
        if ( is_array($source) || $source instanceof DOMNODELIST ) {
            foreach ( $source as $node )
                $return[] = $this->document->importNode($node, true);
        } else {
            // string markup
            $fake = $this->documentFragmentCreate($source, $sourceCharset);
            if ( $fake === false )
                throw new Exception("Error loading documentFragment markup");
            else
                return $this->import($fake->root->childNodes);
        }
        return $return;
    }


    /**
     *
     * @param $document DOMDocumentWrapper
     * @param $markup
     * @return $document
     */
    private function documentFragmentLoadMarkup($fragment, $charset, $markup = null)
    {
        // TODO error handling
        // TODO copy doctype
        // tempolary turn off
        $fragment->isDocumentFragment = false;
        if ( $fragment->isXML ) {
            if ( $fragment->isXHTML ) {
                // add FAKE element to set default namespace
                $fragment->loadMarkupXML('<?xml version="1.0" encoding="' . $charset . '"?>'
                    . '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" '
                    . '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
                    . '<fake xmlns="http://www.w3.org/1999/xhtml">' . $markup . '</fake>');
                $fragment->root = $fragment->document->firstChild->nextSibling;
            } else {
                $fragment->loadMarkupXML('<?xml version="1.0" encoding="' . $charset . '"?><fake>' . $markup . '</fake>');
                $fragment->root = $fragment->document->firstChild;
            }
        } else {
            $markup2 = self::$defaultDoctype . '<html><head><meta http-equiv="Content-Type" content="text/html;charset='
                . $charset . '"></head>';
            $noBody = strpos($markup, '<body') === false;
            if ( $noBody )
                $markup2 .= '<body>';
            $markup2 .= $markup;
            if ( $noBody )
                $markup2 .= '</body>';
            $markup2 .= '</html>';
            $fragment->loadMarkupHTML($markup2);
            // TODO resolv body tag merging issue
            $fragment->root = $noBody
                ? $fragment->document->firstChild->nextSibling->firstChild->nextSibling
                : $fragment->document->firstChild->nextSibling->firstChild->nextSibling;
        }
        if ( ! $fragment->root )
            return false;
        $fragment->isDocumentFragment = true;
        return true;
    }
}