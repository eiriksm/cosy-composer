<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\TokenChooser;
use PHPUnit\Framework\TestCase;

class TokenChooserTest extends TestCase
{
    /**
     * Test the chooser.
     *
     * @dataProvider tokenChooserProvider
     */
    public function testTokenChooser($main_url, $main_token, $check_url, $extra_tokens, $expected)
    {
        $chooser = new TokenChooser($main_url);
        // Let's test using both setters, and the constructor.
        if ($main_token) {
            $chooser->setUserToken($main_token);
        }
        $chooser->addTokens($extra_tokens);
        $result = $chooser->getChosenToken($check_url);
        self::assertEquals($expected, $result);
        // Now directly with constructor.
        $chooser = new TokenChooser($main_url, $main_token, $extra_tokens);
        self::assertEquals($expected, $chooser->getChosenToken($check_url));
    }

    public static function tokenChooserProvider()
    {
        return [
            [
                'https://example.com/user/repo',
                'main_token',
                'https://example.com/user2/repo2',
                [],
                'main_token',
            ],
            [
                'https://example.com/user/repo',
                'main_token',
                'https://example2.com/user/repo',
                [
                    'example2.com' => 'extra_token',
                ],
                'extra_token',
            ],
            [
                'https://example.com/user/repo',
                null,
                'https://example2.com/user/repo',
                [],
                null,
            ],
            [
                'https://www.bitbucket.org/user/repo',
                'main_token',
                'https://bitbucket.org/user/repo',
                [
                    'bitbucket.org' => 'extra_token',
                ],
                'main_token',
            ],
            [
                // Invalid hostname, should return main token.
                'www.github.com/user/repo',
                'main_token',
                'https://bitbucket.org/user/repo',
                [
                    'bitbucket.org' => 'extra_token',
                ],
                'main_token',
            ],
            [
                // Invalid hostname, should return main token if it exists (in
                // this case it does not).
                'www.bitbucket.org/user/repo',
                null,
                'https://bitbucket.org/user/repo',
                [
                    'bitbucket.org' => 'extra_token',
                ],
                null,
            ],
            [
                // No matches, should return main token or null.
                'https://www.bitbucket.org/user/repo',
                'main_token',
                'https://bitbucketnotatall.org/user/repo',
                [
                    'bitbucket.org' => 'extra_token',
                ],
                'main_token',
            ],
            [
                // No matches, should return main token or null.
                'https://www.bitbucket.org/user/repo',
                null,
                'https://bitbucketnotatall.org/user/repo',
                [
                    'bitbucket.org' => 'extra_token',
                ],
                null,
            ],
        ];
    }
}
