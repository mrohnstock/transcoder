<?php

namespace Ddeboer\Transcoder;

use Ddeboer\Transcoder\Exception\ExtensionMissingException;
use Ddeboer\Transcoder\Exception\UnsupportedEncodingException;

class Transcoder implements TranscoderInterface
{
    private static $chain;

    /**
     * @var TranscoderInterface[]
     */
    private $transcoders = [];

    public function __construct(array $transcoders)
    {
        $this->transcoders = $transcoders;
    }

    /**
     * {@inheritdoc}
     */
    public function transcode($string, $from = null, $to = null)
    {
        if (function_exists('mb_detect_encoding')) {
            $_from = mb_detect_encoding($string, "UTF-8, UTF-7, ASCII, ISO-8859-1, ISO-8859-2, ISO-8859-3, ISO-8859-4, ISO-8859-5, ISO-8859-6, ISO-8859-7, ISO-8859-8, ISO-8859-9, ISO-8859-10, ISO-8859-13, ISO-8859-14, ISO-8859-15, ISO-8859-16, Windows-1251, Windows-1252, Windows-1254");
            $from = ($_from != "" ? $_from : $from);
        }

        foreach ($this->transcoders as $transcoder) {
            try {
                return $transcoder->transcode($string, $from, $to);
            } catch (UnsupportedEncodingException $e) {
                // Ignore as long as the fallback transcoder is all right
            }
        }

        throw $e;
    }

    /**
     * Create a transcoder
     *
     * @param string $defaultEncoding
     *
     * @return TranscoderInterface
     *
     * @throws ExtensionMissingException
     */
    public static function create($defaultEncoding = 'UTF-8')
    {
        if (isset(self::$chain[$defaultEncoding])) {
            return self::$chain[$defaultEncoding];
        }

        $transcoders = [];

        try {
            $transcoders[] = new MbTranscoder($defaultEncoding);
        } catch (ExtensionMissingException $mb) {
            // Ignore missing mbstring extension; fall back to iconv
        }

        try {
            $transcoders[] = new IconvTranscoder($defaultEncoding);
        } catch (ExtensionMissingException $iconv) {
            // Neither mbstring nor iconv
            throw $iconv;
        }

        self::$chain[$defaultEncoding] = new self($transcoders);

        return self::$chain[$defaultEncoding];
    }
}
