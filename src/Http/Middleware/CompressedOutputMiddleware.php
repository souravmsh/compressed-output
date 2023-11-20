<?php

namespace Souravmsh\CompressedOutput\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompressedOutputMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (config('compressed-output.enable')) {
            $response = $this->optimizeConent($response);
        }

        return $response;
    }


    private function optimizeConent($response)
    {
        if ($this->isResponseObject($response) && $this->isHtmlResponse($response)) {
            $replace = [
                '/\>[^\S ]+/s'                                                      => '>',
                '/[^\S ]+\</s'                                                      => '<',
                '/([\t ])+/s'                                                       => ' ',
                '/^([\t ])+/m'                                                      => '',
                '/([\t ])+$/m'                                                      => '',
                '~//[a-zA-Z0-9 ]+$~m'                                               => '',
                '/[\r\n]+([\t ]?[\r\n]+)+/s'                                        => "\n",
                '/\>[\r\n\t ]+\</s'                                                 => '><',
                '/}[\r\n\t ]+/s'                                                    => '}',
                '/}[\r\n\t ]+,[\r\n\t ]+/s'                                         => '},',
                '/\)[\r\n\t ]?{[\r\n\t ]+/s'                                        => '){',
                '/,[\r\n\t ]?{[\r\n\t ]+/s'                                         => ',{',
                '/\),[\r\n\t ]+/s'                                                  => '),',
                '~([\r\n\t ])?([a-zA-Z0-9]+)=\"([a-zA-Z0-9_\\-]+)\"([\r\n\t ])?~s'  => '$1$2=$3$4',
                // Minify inline CSS
                '/\s*([{}|:;,])\s*/'                                                => '$1',
                '/;\s*(?=\})/'                                                      => '',
                // Minify inline JS (excluding JSON)
                '/\s*([=+\-\/*%!&|^(){}\[\]:;,.<>?])\s*/'                           => '$1',
            ];

            $responseContent = $response->getContent();

            // Minify inline CSS
            $responseContent = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function ($matches) use ($replace) {
                return '<style>' . preg_replace(array_keys($replace), array_values($replace), $matches[1]) . '</style>';
            }, $responseContent);

            // Minify inline JS (excluding JSON)
            $responseContent = preg_replace_callback('/<script[^>]*>(.*?)<\/script>/is', function ($matches) use ($replace) {
                return '<script>' . preg_replace(array_keys($replace), array_values($replace), $matches[1]) . '</script>';
            }, $responseContent);

            $response->setContent(preg_replace(array_keys($replace), array_values($replace), $responseContent));
        }


        return $response;
    }

    private function isResponseObject($response)
    {
        return is_object($response) && $response instanceof Response;
    }

    private function isHtmlResponse(Response $response)
    {
        return strtolower(strtok($response->headers->get('Content-Type'), ';')) === 'text/html';
    }
}
